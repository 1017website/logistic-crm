<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardFinancialSummaryTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_summarizes_only_issued_invoices_and_subtracts_partial_payments(): void
    {
        $operator = User::create([
            'name' => 'Dashboard Finance Test',
            'email' => 'dashboard-finance-' . uniqid() . '@example.test',
            'password' => 'password',
            'role' => 'Admin',
            'status' => 'Active',
        ]);

        // Isolasi data invoice yang sudah ada; transaksi test akan mengembalikannya.
        Invoice::query()->update(['status' => 'draft']);

        $this->makeInvoice($operator, 'draft', 1000, 1100);
        $this->makeInvoice($operator, 'invoice', 2000, 2200);
        $termin = $this->makeInvoice($operator, 'termin', 3000, 3300);
        $paid = $this->makeInvoice($operator, 'paid', 4000, 4400);

        InvoicePayment::create([
            'invoice_id' => $termin->id,
            'payment_date' => '2026-08-30',
            'amount' => 800,
            'payment_type' => 'termin',
            'recorded_by' => $operator->id,
        ]);
        InvoicePayment::create([
            'invoice_id' => $paid->id,
            'payment_date' => '2026-08-30',
            'amount' => 4400,
            'payment_type' => 'pelunasan',
            'recorded_by' => $operator->id,
        ]);

        $this->actingAs($operator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalTurnover', fn ($value) => (float) $value === 9000.0)
            ->assertViewHas('totalReceivables', fn ($value) => (float) $value === 4700.0)
            ->assertSee('Omzet Keseluruhan')
            ->assertSee('Total Piutang Keseluruhan');
    }

    private function makeInvoice(User $operator, string $status, int $totalJual, int $grandTotal): Invoice
    {
        return Invoice::create([
            'invoice_id' => 'IV-DASH-' . uniqid(),
            'invoice_number' => strtoupper($status) . '/DASH/VIII/2026',
            'status' => $status,
            'tgl_buat' => '2026-08-30',
            'total_hpp' => 0,
            'total_jual' => $totalJual,
            'grand_total' => $grandTotal,
            'jenis' => 'TR',
            'operator_id' => $operator->id,
        ]);
    }
}
