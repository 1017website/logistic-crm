<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Invoice;
use App\Models\OrderJobDetail;
use App\Models\RequestOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Laporan DO harus menampilkan HPP Rencana (rincian pekerjaan yang disetujui,
 * dipakai invoice) berdampingan dengan HPP Aktual (biaya saat DO ditutup),
 * sehingga selisihnya terlihat alih-alih saling menimpa antar menu.
 */
class LogisticReportCostBasisTest extends TestCase
{
    use DatabaseTransactions;

    public function test_do_report_shows_planned_and_actual_cost_side_by_side(): void
    {
        [$user, , $requestOrder] = $this->makeOrder(actualCost: 950000, otherCost: 50000);

        // HPP rencana 800.000 (700k TR + 100k NTR), realisasi 1.000.000.
        $this->assertSame(800000.0, $requestOrder->total_cost);
        $this->assertSame(1000000.0, $requestOrder->fresh()->actual_total_cost);
        $this->assertSame(200000.0, $requestOrder->fresh()->cost_variance);
        $this->assertSame(250000.0, $requestOrder->fresh()->actual_gross_profit);
        $this->assertSame(450000.0, $requestOrder->fresh()->gross_profit);

        $this->actingAs($user)
            ->get(route('logistic-reports.do'))
            ->assertOk()
            ->assertSee('HPP Rencana')
            ->assertSee('HPP Aktual')
            ->assertSee('Selisih')
            ->assertSee('Rp 800.000')
            ->assertSee('Rp 1.000.000');
    }

    public function test_actual_cost_is_null_until_the_do_is_closed(): void
    {
        [, , $requestOrder] = $this->makeOrder(actualCost: 0, otherCost: 0);

        $fresh = $requestOrder->fresh();
        $this->assertNull($fresh->actual_total_cost);
        $this->assertNull($fresh->cost_variance);
        $this->assertNull($fresh->actual_gross_profit);
    }

    public function test_do_report_export_carries_both_cost_columns(): void
    {
        [$user] = $this->makeOrder(actualCost: 950000, otherCost: 50000);

        $response = $this->actingAs($user)->get(route('logistic-reports.do.export'));

        $response->assertOk();
        $this->assertStringContainsString('laporan-do-', $response->headers->get('content-disposition'));
    }

    public function test_outstanding_report_separates_undrafted_billing_from_receivables(): void
    {
        [$user, $customer, $requestOrder, $deliveryOrder] = $this->makeOrder(
            actualCost: 800000,
            otherCost: 0,
            returnDo: true
        );
        // Isolasi invoice yang sudah ada ke status lunas — satu-satunya status yang
        // tidak masuk baris piutang maupun kartu draft. Transaksi test mengembalikannya.
        Invoice::query()->update(['status' => 'paid']);

        // Satu invoice terbit (piutang) dan satu draft (belum ditagih).
        $issued = $this->makeInvoice($user, $customer, 'invoice', 1_500_000);
        $this->makeInvoice($user, $customer, 'draft', 2_000_000);

        $response = $this->actingAs($user)->get(route('logistic-reports.outstanding'));

        $response->assertOk()
            ->assertViewHas('totalOutstanding', 1_500_000.0)
            ->assertSee('Belum Ditagih (Draft Invoice)')
            ->assertSee('Rp 2.000.000');

        $draft = $response->viewData('draftSummary');
        $this->assertSame(1, $draft['count']);
        $this->assertSame(2_000_000.0, $draft['amount']);
        $this->assertSame(1, $draft['clients']);

        // Draft tidak boleh ikut ke dalam baris piutang maupun bucket aging.
        $ids = collect($response->viewData('rows')->items())->map(fn(array $r) => $r['invoice']->id)->all();
        $this->assertSame([$issued->id], $ids);
        $this->assertSame(1_500_000.0, collect($response->viewData('bucketSummary'))->sum('amount'));
    }

    private function makeInvoice(User $operator, Customer $customer, string $status, int $grandTotal): Invoice
    {
        return Invoice::create([
            'invoice_id' => 'IV-CB-' . uniqid(),
            'invoice_number' => strtoupper($status) . '/CB/VIII/2026',
            'customer_id' => $customer->id,
            'status' => $status,
            'tgl_buat' => '2026-08-01',
            'tgl_tempo' => '2026-08-31',
            'total_hpp' => 0,
            'total_jual' => $grandTotal,
            'grand_total' => $grandTotal,
            'jenis' => 'TR',
            'operator_id' => $operator->id,
        ]);
    }

    /** @return array{0:User,1:Customer,2:RequestOrder,3?:DeliveryOrder} */
    private function makeOrder(int $actualCost, int $otherCost, bool $returnDo = false): array
    {
        $user = User::create([
            'name' => 'Cost Basis Admin',
            'email' => 'cost-basis-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);
        $customer = Customer::create([
            'customer_code' => 'CUST-CB-' . uniqid(),
            'invoice_code' => 'CB' . strtoupper(substr(uniqid(), -5)),
            'company_name' => 'Customer Cost Basis',
            'pic_name' => 'PIC',
            'phone' => '0800000007',
            'user_id' => $user->id,
        ]);
        $requestOrder = RequestOrder::create([
            'do_number' => 'RDO-CB-' . uniqid(),
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'In Progress',
            'request_status' => 'assigned',
            'order_date' => now()->toDateString(),
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
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => $requestOrder->do_number,
            'request_order_id' => $requestOrder->id,
            'customer_id' => $customer->id,
            'user_id' => $user->id,
            'status' => 'closed',
            'invoice_status' => 'uninvoiced',
            'origin' => 'Surabaya',
            'destination' => 'Jakarta',
            'do_date' => now()->toDateString(),
            'pod_at' => now()->subDay(),
            'actual_cost' => $actualCost,
            'other_cost' => $otherCost,
        ]);

        return $returnDo
            ? [$user, $customer, $requestOrder, $deliveryOrder]
            : [$user, $customer, $requestOrder];
    }
}
