<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RequestOrder;
use Illuminate\Http\Request;

/**
 * Laporan logistik: DO & Invoice.
 * - Laporan DO: filter client, jenis (sudah/belum tagih), bulan.
 * - Laporan Invoice: filter client, jenis (belum bayar/sudah cair), trucking, s/d tanggal.
 *   Menampilkan Due Date & umur hari (selisih ke jatuh tempo).
 */
class LogisticReportController extends Controller
{
    public function index()
    {
        return view('logistic_reports.index');
    }

    // ─────────────────── LAPORAN DO ───────────────────
    public function do(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');   // all|invoiced|uninvoiced|paid
        $month      = $request->get('month');           // format Y-m
        $search     = $request->get('search');

        $query = RequestOrder::with(['customer', 'vendor', 'jobDetails'])
            ->where('request_status', 'assigned'); // hanya DO yang sudah terbit

        if ($customerId) $query->where('customer_id', $customerId);
        if ($jenis === 'invoiced')   $query->whereIn('invoice_status', ['partial', 'invoiced']);
        if ($jenis === 'partial')    $query->where('invoice_status', 'partial');
        if ($jenis === 'uninvoiced') $query->where('invoice_status', 'uninvoiced');
        if ($jenis === 'paid')       $query->where('invoice_status', 'paid');
        if ($month) {
            [$y, $m] = array_pad(explode('-', $month), 2, null);
            if ($y && $m) $query->whereYear('order_date', $y)->whereMonth('order_date', $m);
        }
        if ($search) {
            $query->where(fn($q) => $q
                ->where('do_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($c) => $c->where('company_name', 'like', "%$search%")));
        }

        $dos = $query->orderByDesc('order_date')->paginate(25)->withQueryString();

        $customers = Customer::orderBy('company_name')->get(['id', 'company_name']);

        return view('logistic_reports.do', compact('dos', 'customers', 'customerId', 'jenis', 'month', 'search'));
    }

    public function doExport(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');
        $month      = $request->get('month');

        $query = RequestOrder::with(['customer', 'vendor', 'jobDetails'])
            ->where('request_status', 'assigned');
        if ($customerId) $query->where('customer_id', $customerId);
        if ($jenis === 'invoiced')   $query->whereIn('invoice_status', ['partial', 'invoiced']);
        if ($jenis === 'partial')    $query->where('invoice_status', 'partial');
        if ($jenis === 'uninvoiced') $query->where('invoice_status', 'uninvoiced');
        if ($jenis === 'paid')       $query->where('invoice_status', 'paid');
        if ($month) {
            [$y, $m] = array_pad(explode('-', $month), 2, null);
            if ($y && $m) $query->whereYear('order_date', $y)->whereMonth('order_date', $m);
        }

        $dos = $query->orderByDesc('order_date')->get();

        $headers = ['DO', 'Tanggal', 'Client', 'Container', 'No Seal', 'Kota', 'Depo', 'Muat', 'Bongkar', 'Truck/Nopol', 'Tujuan', 'Tgl Muat', 'Tgl Bongkar', 'Sopir', 'Checker', 'Komoditi', 'HPP', 'Jual', 'Laba', 'Status Tagih'];
        $rows = [];
        foreach ($dos as $do) {
            $rows[] = [
                $do->do_number, $do->order_date?->format('Y-m-d'),
                $do->customer?->company_name ?? '-',
                $do->no_container, $do->no_seal, $do->kota, $do->depo, $do->muat, $do->bongkar,
                $do->no_pol, $do->tujuan,
                $do->tgl_muat?->format('Y-m-d'), $do->tgl_bongkar?->format('Y-m-d'),
                $do->supir, $do->checker, $do->komoditi,
                (float) $do->total_cost, (float) $do->total_revenue,
                (float) $do->total_revenue - (float) $do->total_cost,
                $do->invoice_status,
            ];
        }

        return \App\Helpers\ExcelExport::download('laporan-do-' . date('Ymd'), $headers, $rows, 'Laporan DO');
    }

    // ─────────────────── LAPORAN INVOICE ───────────────────
    public function invoice(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');     // all|unpaid|paid
        $sdTanggal  = $request->get('sd_tanggal');         // s/d tanggal kirim invoice

        $query = Invoice::with('customer')->whereIn('status', ['invoice', 'paid']);

        if ($customerId) $query->where('customer_id', $customerId);
        if ($jenis === 'unpaid') $query->where('status', 'invoice');
        if ($jenis === 'paid')   $query->where('status', 'paid');
        if ($sdTanggal)          $query->whereDate('tgl_buat', '<=', $sdTanggal);

        $invoices = $query->orderByDesc('tgl_buat')->paginate(25)->withQueryString();

        $customers = Customer::orderBy('company_name')->get(['id', 'company_name']);

        return view('logistic_reports.invoice', compact('invoices', 'customers', 'customerId', 'jenis', 'sdTanggal'));
    }

    public function invoiceExport(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');
        $sdTanggal  = $request->get('sd_tanggal');

        $query = Invoice::with('customer')->whereIn('status', ['invoice', 'paid']);
        if ($customerId) $query->where('customer_id', $customerId);
        if ($jenis === 'unpaid') $query->where('status', 'invoice');
        if ($jenis === 'paid')   $query->where('status', 'paid');
        if ($sdTanggal)          $query->whereDate('tgl_buat', '<=', $sdTanggal);

        $invoices = $query->orderByDesc('tgl_buat')->get();

        $headers = ['No Urut Inv', 'Submit', 'No Invoice', 'Customer', 'Harga Jual', 'HPP', 'Laba', 'Due Date', 'Umur (hari)', 'Status', 'Tgl Cair'];
        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                $inv->invoice_id, $inv->tgl_buat?->format('Y-m-d'), $inv->invoice_number,
                $inv->customer?->company_name ?? '-',
                (float) $inv->total_jual, (float) $inv->total_hpp, (float) $inv->laba,
                $inv->tgl_tempo?->format('Y-m-d'), $inv->umur_hari,
                $inv->status_label, $inv->tgl_pencairan?->format('Y-m-d'),
            ];
        }

        return \App\Helpers\ExcelExport::download('laporan-invoice-' . date('Ymd'), $headers, $rows, 'Laporan Invoice');
    }
}
