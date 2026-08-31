<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OutstandingReportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_outstanding_report_only_lists_unpaid_balance_and_buckets_by_aging(): void
    {
        [$user, $customer] = $this->makeActor();

        // Isolasi data invoice lain; transaksi test akan mengembalikannya.
        Invoice::query()->update(['status' => 'draft']);

        $belumTempo = $this->makeInvoice($user, $customer, 'invoice', 1_000_000, now()->addDays(10));
        $lewat15    = $this->makeInvoice($user, $customer, 'termin', 2_000_000, now()->subDays(15));
        $lewat100   = $this->makeInvoice($user, $customer, 'invoice', 3_000_000, now()->subDays(100));
        $lunas      = $this->makeInvoice($user, $customer, 'paid', 4_000_000, now()->subDays(5));
        $this->makeInvoice($user, $customer, 'draft', 9_000_000, now()->subDays(5));
        $terbayarPenuh = $this->makeInvoice($user, $customer, 'invoice', 500_000, now()->subDays(40));

        $this->pay($user, $lewat15, 500_000);
        $this->pay($user, $lunas, 4_000_000);
        $this->pay($user, $terbayarPenuh, 500_000);

        $response = $this->actingAs($user)->get(route('logistic-reports.outstanding'));

        $response->assertOk()
            ->assertViewHas('totalOutstanding', 5_500_000.0)   // 1.000.000 + 1.500.000 + 3.000.000
            ->assertViewHas('totalOverdue', 4_500_000.0)       // hanya yang lewat tempo
            ->assertViewHas('totalTagihan', 6_000_000.0)
            ->assertViewHas('totalPaid', 500_000.0)
            ->assertViewHas('totalCount', 3)
            ->assertViewHas('totalClient', 1);

        $ids = collect($response->viewData('rows')->items())
            ->map(fn (array $r) => $r['invoice']->id)
            ->all();

        // Urut dari tunggakan terlama.
        $this->assertSame([$lewat100->id, $lewat15->id, $belumTempo->id], $ids);

        $buckets = $response->viewData('bucketSummary');
        $this->assertSame(1, $buckets['current']['count']);
        $this->assertSame(1_000_000.0, $buckets['current']['amount']);
        $this->assertSame(1, $buckets['1_30']['count']);
        $this->assertSame(1_500_000.0, $buckets['1_30']['amount']);
        $this->assertSame(0, $buckets['31_60']['count']);
        $this->assertSame(0, $buckets['61_90']['count']);
        $this->assertSame(1, $buckets['90_plus']['count']);
        $this->assertSame(3_000_000.0, $buckets['90_plus']['amount']);
    }

    public function test_aging_filter_narrows_rows_but_keeps_full_bucket_breakdown(): void
    {
        [$user, $customer] = $this->makeActor();
        Invoice::query()->update(['status' => 'draft']);

        $this->makeInvoice($user, $customer, 'invoice', 1_000_000, now()->addDays(10));
        $lewat100 = $this->makeInvoice($user, $customer, 'invoice', 3_000_000, now()->subDays(100));

        $response = $this->actingAs($user)->get(route('logistic-reports.outstanding', ['aging' => '90_plus']));

        $response->assertOk()
            ->assertViewHas('totalCount', 1)
            ->assertViewHas('totalOutstanding', 3_000_000.0);

        $rows = collect($response->viewData('rows')->items());
        $this->assertSame([$lewat100->id], $rows->map(fn (array $r) => $r['invoice']->id)->all());
        $this->assertSame(100, $rows->first()['days_overdue']);

        // Kartu bucket tetap menampilkan seluruh umur agar bisa dipakai drill-down.
        $this->assertSame(1, $response->viewData('bucketSummary')['current']['count']);
    }

    public function test_outstanding_export_downloads_filtered_rows(): void
    {
        [$user, $customer] = $this->makeActor();
        Invoice::query()->update(['status' => 'draft']);
        $this->makeInvoice($user, $customer, 'invoice', 3_000_000, now()->subDays(100));

        $response = $this->actingAs($user)->get(route('logistic-reports.outstanding.export'));

        $response->assertOk();
        $this->assertStringContainsString('laporan-outstanding-', $response->headers->get('content-disposition'));
    }

    public function test_report_hub_links_to_outstanding_report(): void
    {
        [$user] = $this->makeActor();

        $this->actingAs($user)
            ->get(route('logistic-reports.index'))
            ->assertOk()
            ->assertSee(route('logistic-reports.outstanding'), false)
            ->assertSee('Laporan Outstanding');
    }

    /** @return array{0: User, 1: Customer} */
    private function makeActor(): array
    {
        $user = User::create([
            'name' => 'Outstanding Report Test',
            'email' => 'outstanding-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Finance',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-OS-' . uniqid(),
            'invoice_code' => 'OS' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Outstanding Test',
            'pic_name' => 'PIC',
            'phone' => '0800000002',
            'user_id' => $user->id,
        ]);

        return [$user, $customer];
    }

    private function makeInvoice(User $operator, Customer $customer, string $status, int $grandTotal, $tglTempo): Invoice
    {
        return Invoice::create([
            'invoice_id' => 'IV-OS-' . uniqid(),
            'invoice_number' => strtoupper($status) . '/OS/VIII/2026',
            'customer_id' => $customer->id,
            'status' => $status,
            'tgl_buat' => now()->subDays(120)->toDateString(),
            'tgl_tempo' => $tglTempo,
            'total_hpp' => 0,
            'total_jual' => $grandTotal,
            'grand_total' => $grandTotal,
            'jenis' => 'TR',
            'operator_id' => $operator->id,
        ]);
    }

    private function pay(User $operator, Invoice $invoice, int $amount): void
    {
        InvoicePayment::create([
            'invoice_id' => $invoice->id,
            'payment_date' => now()->toDateString(),
            'amount' => $amount,
            'payment_type' => 'termin',
            'recorded_by' => $operator->id,
        ]);
    }
}
