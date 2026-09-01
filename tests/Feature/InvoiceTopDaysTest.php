<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\AutomaticInvoiceDraftService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * TOP (term of payment) ditentukan per customer, dengan default global yang
 * dapat diatur di menu Pengaturan. Tidak ada lagi angka 30 hari yang hardcoded.
 */
class InvoiceTopDaysTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Cache::forget('setting_invoice_default_top_days');
        parent::tearDown();
    }

    public function test_customer_top_wins_over_global_default(): void
    {
        $this->setDefaultTop(30);
        [, $customer] = $this->makeUserAndCustomer(topDays: 45);

        $this->assertSame(45, $customer->effective_top_days);
        $this->assertSame('2026-09-15', $customer->dueDateFrom('2026-08-01'));
    }

    public function test_customer_without_top_follows_global_default(): void
    {
        $this->setDefaultTop(14);
        [, $customer] = $this->makeUserAndCustomer(topDays: null);

        $this->assertSame(14, $customer->effective_top_days);
        $this->assertSame('2026-08-15', $customer->dueDateFrom('2026-08-01'));
    }

    public function test_falls_back_to_thirty_days_when_setting_is_absent_or_invalid(): void
    {
        Setting::set('invoice_default_top_days', '0');
        $this->assertSame(30, Customer::defaultTopDays());

        Setting::set('invoice_default_top_days', '');
        Cache::forget('setting_invoice_default_top_days');
        $this->assertSame(30, Customer::defaultTopDays());
    }

    public function test_manual_invoice_due_date_uses_customer_top(): void
    {
        $this->setDefaultTop(30);
        [$user, $customer, $do] = $this->makeClosedDo(topDays: 60);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-08-01',
            'selections' => [$do->id . ':TR'],
            'ppn_mode' => 'non_ppn',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-09-30', $invoice->tgl_tempo?->toDateString());
    }

    public function test_automatic_draft_due_date_uses_customer_top(): void
    {
        $this->setDefaultTop(30);
        [$user, $customer, $do] = $this->makeClosedDo(topDays: 7);
        $do->update(['closed_at' => '2026-08-01 10:00:00']);

        $drafts = app(AutomaticInvoiceDraftService::class)
            ->createForClosedDeliveryOrder($do->fresh(), $user->id);

        $this->assertTrue($drafts->isNotEmpty());
        $this->assertTrue($drafts->every(
            fn(Invoice $invoice) => $invoice->tgl_tempo?->toDateString() === '2026-08-08'
        ));
    }

    public function test_explicit_due_date_still_overrides_top(): void
    {
        $this->setDefaultTop(30);
        [$user, $customer, $do] = $this->makeClosedDo(topDays: 60);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-08-01',
            'tgl_tempo' => '2026-08-10',
            'selections' => [$do->id . ':TR'],
            'ppn_mode' => 'non_ppn',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-08-10', $invoice->tgl_tempo?->toDateString());
    }

    public function test_invoice_period_can_use_previous_month_without_changing_top_dates(): void
    {
        Carbon::setTestNow('2026-09-10 10:00:00');
        $this->setDefaultTop(30);
        [$user, $customer, $do] = $this->makeClosedDo(topDays: 30);

        $this->actingAs($user)->post(route('invoices.store'), [
            'customer_id' => $customer->id,
            'tgl_buat' => '2026-09-02',
            'periode_invoice' => '2026-09',
            'selections' => [$do->id . ':TR'],
            'ppn_mode' => 'non_ppn',
        ])->assertSessionHas('success');

        $invoice = Invoice::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-09-02', $invoice->tgl_buat?->toDateString());
        $this->assertSame('2026-10-02', $invoice->tgl_tempo?->toDateString());
        $this->assertSame('2026-09-01', $invoice->periode_invoice?->toDateString());

        $this->actingAs($user)->post(route('invoices.submit', $invoice), [
            'periode_invoice' => '2026-08',
        ])->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame('invoice', $invoice->status);
        $this->assertSame('2026-08-01', $invoice->periode_invoice?->toDateString());
        $this->assertSame('2026-09-02', $invoice->tgl_buat?->toDateString());
        $this->assertSame('2026-09-10 10:00:00', $invoice->submitted_at?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-10', $invoice->tgl_tempo?->toDateString());

        $this->actingAs($user)
            ->get(route('logistic-reports.invoice', [
                'customer_id' => $customer->id,
                'periode' => '2026-08',
            ]))
            ->assertOk()
            ->assertViewHas('invoiceCount', 1)
            ->assertSee($invoice->invoice_number);

        $this->actingAs($user)
            ->get(route('logistic-reports.invoice', [
                'customer_id' => $customer->id,
                'periode' => '2026-09',
            ]))
            ->assertOk()
            ->assertViewHas('invoiceCount', 0)
            ->assertDontSee($invoice->invoice_number);

    }

    public function test_top_can_be_saved_from_customer_form_and_global_default_from_settings(): void
    {
        $admin = User::create([
            'name' => 'Admin TOP',
            'email' => 'admin-top-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)->post(route('customers.store'), [
            'company_name' => 'Customer TOP Form ' . uniqid(),
            'pic_name' => 'PIC',
            'phone' => '0800000010',
            'status' => 'Existing',
            'user_id' => $admin->id,
            'top_days' => 21,
        ])->assertSessionHasNoErrors();

        $this->assertSame(21, (int) Customer::latest('id')->first()->top_days);

        // Di luar rentang wajar harus ditolak.
        $this->actingAs($admin)
            ->from(route('customers.index'))
            ->post(route('customers.store'), [
                'company_name' => 'Customer TOP Invalid ' . uniqid(),
                'pic_name' => 'PIC',
                'phone' => '0800000011',
                'status' => 'Existing',
                'user_id' => $admin->id,
                'top_days' => 400,
            ])
            ->assertSessionHasErrors('top_days');
    }

    public function test_global_default_top_can_be_saved_from_settings(): void
    {
        $admin = User::create([
            'name' => 'Admin Setting TOP',
            'email' => 'admin-set-top-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        $this->actingAs($admin)->put(route('settings.update'), [
            'company_name' => Setting::get('company_name', 'Perusahaan'),
            'invoice_default_top_days' => 45,
        ])->assertSessionHasNoErrors();

        Cache::forget('setting_invoice_default_top_days');
        $this->assertSame(45, Customer::defaultTopDays());
    }

    private function setDefaultTop(int $days): void
    {
        Setting::set('invoice_default_top_days', (string) $days);
        Cache::forget('setting_invoice_default_top_days');
    }

    /** @return array{0:User,1:Customer} */
    private function makeUserAndCustomer(?int $topDays): array
    {
        $user = User::create([
            'name' => 'TOP Admin',
            'email' => 'top-admin-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-TOP-' . uniqid(),
            'invoice_code' => 'TP' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer TOP',
            'pic_name' => 'PIC',
            'phone' => '0800000012',
            'user_id' => $user->id,
            'top_days' => $topDays,
        ]);

        return [$user, $customer];
    }

    /** @return array{0:User,1:Customer,2:DeliveryOrder} */
    private function makeClosedDo(?int $topDays): array
    {
        [$user, $customer] = $this->makeUserAndCustomer($topDays);

        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-TOP-' . uniqid(),
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
        ]);

        return [$user, $customer, $do];
    }
}
