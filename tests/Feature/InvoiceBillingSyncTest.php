<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\User;
use App\Services\InvoiceBillingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tahap alur DO final harus selalu mengikuti kondisi invoice yang sebenarnya.
 * Sebelumnya invoice yang dikembalikan ke draft atau dihapus meninggalkan DO
 * pada tahap "Invoice Terbit", sehingga DO hilang dari daftar pilihan invoice
 * dan tidak dapat ditagih ulang lewat aplikasi.
 */
class InvoiceBillingSyncTest extends TestCase
{
    use DatabaseTransactions;

    public function test_deleting_issued_invoice_reopens_do_for_billing(): void
    {
        [$user, $customer, $do] = $this->makeClosedDo();
        $invoices = $this->createAndSubmitInvoices($user, $customer, $do);

        $this->assertSame('invoiced', $do->fresh()->status);

        foreach ($invoices as $invoice) {
            $this->actingAs($user)
                ->delete(route('invoices.destroy', $invoice))
                ->assertSessionHasNoErrors();
        }

        $fresh = $do->fresh();
        $this->assertSame('closed', $fresh->status, 'DO harus kembali ke tahap Ditutup agar bisa ditagih ulang.');
        $this->assertSame('uninvoiced', $fresh->invoice_status);
        $this->assertSame('uninvoiced', $do->requestOrder->fresh()->invoice_status);

        $available = $this->actingAs($user)
            ->getJson(route('invoices.available-dos', ['customer_id' => $customer->id]))
            ->assertOk()
            ->json();
        $this->assertSame([$do->id], array_column($available, 'id'));

        // Timeline mencatat alasan DO dibuka kembali.
        $this->assertDatabaseHas('order_status_logs', [
            'loggable_type' => $do->getMorphClass(),
            'loggable_id' => $do->id,
            'from_status' => 'invoiced',
            'to_status' => 'closed',
        ]);
    }

    public function test_unsubmitting_invoice_returns_do_to_closed(): void
    {
        [$user, $customer, $do] = $this->makeClosedDo();
        $invoices = $this->createAndSubmitInvoices($user, $customer, $do);

        // Satu invoice kembali ke draft: DO belum boleh dianggap tertagih penuh,
        // tetapi masih ada satu invoice terbit sehingga tahapnya tetap invoiced.
        $this->actingAs($user)
            ->post(route('invoices.unsubmit', $invoices->first()))
            ->assertSessionHasNoErrors();
        $this->assertSame('invoiced', $do->fresh()->status);

        // Seluruh invoice kembali ke draft: tidak ada tagihan terbit lagi.
        $this->actingAs($user)
            ->post(route('invoices.unsubmit', $invoices->last()))
            ->assertSessionHasNoErrors();

        $this->assertSame('closed', $do->fresh()->status);
    }

    public function test_full_payment_still_marks_do_and_request_as_paid(): void
    {
        [$user, $customer, $do] = $this->makeClosedDo();
        $invoices = $this->createAndSubmitInvoices($user, $customer, $do);

        foreach ($invoices as $invoice) {
            $this->actingAs($user)->post(route('invoices.pay', $invoice), [
                'payment_type' => 'pelunasan',
                'tgl_pencairan' => '2026-08-15',
            ])->assertSessionHasNoErrors();
        }

        $fresh = $do->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertSame('paid', $fresh->invoice_status);
        $this->assertSame('paid', $do->requestOrder->fresh()->invoice_status);
        $this->assertTrue($invoices->every(fn(Invoice $inv) => $inv->fresh()->status === 'paid'));
    }

    public function test_partial_payment_keeps_do_at_invoiced(): void
    {
        [$user, $customer, $do] = $this->makeClosedDo();
        $invoices = $this->createAndSubmitInvoices($user, $customer, $do);

        $this->actingAs($user)->post(route('invoices.pay', $invoices->first()), [
            'payment_type' => 'termin',
            'tgl_pencairan' => '2026-08-15',
            'amount' => 100000,
        ])->assertSessionHasNoErrors();

        $this->assertSame('termin', $invoices->first()->fresh()->status);
        $this->assertSame('invoiced', $do->fresh()->status);
    }

    public function test_sync_never_touches_do_that_is_still_in_the_field(): void
    {
        [, , $do] = $this->makeClosedDo();
        $do->update(['status' => 'in_delivery']);

        app(InvoiceBillingService::class)->sync([$do->id]);

        $this->assertSame('in_delivery', $do->fresh()->status, 'DO yang masih berjalan tidak boleh dipindahkan oleh sinkronisasi tagihan.');
    }

    public function test_manual_invoice_without_due_date_defaults_to_thirty_days(): void
    {
        [$user, $customer, $do] = $this->makeClosedDo();

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-07-30',
            'selections' => [$do->id . ':TR'],
            'ppn_mode' => 'non_ppn',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-08-29', $invoice->tgl_tempo?->toDateString());
        $this->assertNotNull($invoice->umur_hari);
    }

    public function test_manual_invoice_keeps_explicit_due_date(): void
    {
        [$user, $customer, $do] = $this->makeClosedDo();

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-07-30',
            'tgl_tempo' => '2026-09-15',
            'selections' => [$do->id . ':TR'],
            'ppn_mode' => 'non_ppn',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-09-15', $invoice->tgl_tempo?->toDateString());
    }

    public function test_assignment_approval_warns_when_price_is_not_approved_yet(): void
    {
        $manager = User::create([
            'name' => 'Manager Warning Test',
            'email' => 'manager-warn-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Sales Manager',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-WR-' . uniqid(),
            'company_name' => 'Customer Warning',
            'pic_name' => 'PIC',
            'phone' => '0800000004',
            'user_id' => $manager->id,
        ]);
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-WR-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $manager->id,
            'status' => 'In Progress',
            'request_status' => 'approval',
            'order_date' => '2026-08-01',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_approved' => false,
        ]);

        $this->actingAs($manager)
            ->post(route('request-orders.approve', $requestOrder), ['action' => 'approve'])
            ->assertSessionHas('success')
            ->assertSessionHas('warning', fn(string $warning) => str_contains($warning, 'Harga DO belum disetujui'));

        $this->assertSame('assigned', $requestOrder->fresh()->request_status);
    }

    /** @return \Illuminate\Support\Collection<int, Invoice> */
    private function createAndSubmitInvoices(User $user, Customer $customer, DeliveryOrder $do)
    {
        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-07-30',
            'tgl_tempo' => '2026-08-29',
            'selections' => [$do->id . ':TR', $do->id . ':NTR'],
            'ppn_mode' => 'non_ppn',
        ])->assertSessionHas('success');

        $invoices = Invoice::where('customer_id', $customer->id)->orderBy('id')->get();
        $this->assertCount(2, $invoices);

        foreach ($invoices as $invoice) {
            $this->actingAs($user)
                ->post(route('invoices.submit', $invoice))
                ->assertSessionHasNoErrors();
        }

        return $invoices;
    }

    /** @return array{0:User,1:Customer,2:DeliveryOrder} */
    private function makeClosedDo(): array
    {
        $user = User::create([
            'name' => 'Billing Sync Admin',
            'email' => 'billing-sync-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-BS-' . uniqid(),
            'invoice_code' => 'BS' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Billing Sync',
            'pic_name' => 'PIC',
            'phone' => '0800000005',
            'user_id' => $user->id,
        ]);
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-BS-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'In Progress',
            'request_status' => 'assigned',
            'order_date' => '2026-07-29',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_approved' => true,
            'invoice_status' => 'uninvoiced',
        ]);
        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Trucking', 'job_code' => 'TR',
            'riil_biaya' => 700000, 'riil_jual' => 1000000,
        ]);
        OrderJobDetail::create([
            'request_order_id' => $requestOrder->id,
            'job_name' => 'Jasa Bongkar', 'job_code' => 'NTR',
            'riil_biaya' => 100000, 'riil_jual' => 250000,
        ]);
        $do = DeliveryOrder::create([
            'do_number' => $requestOrder->do_number,
            'request_order_id' => $requestOrder->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'closed',
            'invoice_status' => 'uninvoiced',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_date' => '2026-07-29',
            'pod_at' => '2026-07-30 09:00:00',
            'closed_at' => '2026-07-30 10:00:00',
        ]);

        return [$user, $customer, $do];
    }
}
