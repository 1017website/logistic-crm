<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\RequestOrder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /**
     * Batasi Laporan DO berdasarkan status pelaksanaan.
     *
     * Default 'aktif' mengeluarkan DO yang dibatalkan operasional — DO batal
     * sebelumnya ikut menaikkan total HPP/Jual/Laba padahal pekerjaannya tidak
     * pernah jalan. Pilihan 'all' dan 'cancelled' disediakan agar data batal
     * tetap dapat ditelusuri, bukan disembunyikan diam-diam.
     */
    private function applyOperationalFilter($query, string $operasional): void
    {
        if ($operasional === 'cancelled') {
            $query->where('operational_status', 'cancelled');
            return;
        }

        if ($operasional !== 'all') {
            $query->where(fn($q) => $q
                ->whereNull('operational_status')
                ->orWhere('operational_status', '!=', 'cancelled'));
        }
    }

    // ─────────────────── LAPORAN DO ───────────────────
    public function do(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');   // all|invoiced|uninvoiced|paid
        $month      = $request->get('month');           // format Y-m
        $search     = $request->get('search');
        $operasional = $request->get('operasional', 'aktif'); // aktif|all|cancelled

        // deliveryOrder dimuat untuk HPP realisasi (biaya aktual saat DO ditutup).
        $query = RequestOrder::with(['customer', 'vendor', 'jobDetails', 'deliveryOrder'])
            ->where('request_status', 'assigned'); // hanya DO yang sudah terbit

        $this->applyOperationalFilter($query, $operasional);

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

        return view('logistic_reports.do', compact('dos', 'customers', 'customerId', 'jenis', 'month', 'search', 'operasional'));
    }

    public function doExport(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');
        $month      = $request->get('month');

        $query = RequestOrder::with(['customer', 'vendor', 'jobDetails', 'deliveryOrder'])
            ->where('request_status', 'assigned');
        $this->applyOperationalFilter($query, $request->get('operasional', 'aktif'));
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

        $headers = ['DO', 'Tanggal', 'Client', 'Container', 'No Seal', 'Kota', 'Depo', 'Muat', 'Bongkar', 'Truck/Nopol', 'Tujuan', 'Tgl Muat', 'Tgl Bongkar', 'Sopir', 'Checker', 'Komoditi', 'HPP Rencana', 'HPP Aktual', 'Selisih HPP', 'Jual', 'Laba Rencana', 'Laba Aktual', 'Status Tagih', 'Status Pelaksanaan'];
        $rows = [];
        foreach ($dos as $do) {
            $rows[] = [
                $do->do_number, $do->order_date?->format('Y-m-d'),
                $do->customer?->company_name ?? '-',
                $do->no_container, $do->no_seal, $do->kota, $do->depo, $do->muat, $do->bongkar,
                $do->no_pol, $do->tujuan,
                $do->tgl_muat?->format('Y-m-d'), $do->tgl_bongkar?->format('Y-m-d'),
                $do->supir, $do->checker, $do->komoditi,
                (float) $do->total_cost,
                $do->actual_total_cost,
                $do->cost_variance,
                (float) $do->total_revenue,
                (float) $do->gross_profit,
                $do->actual_gross_profit,
                $do->invoice_status,
                $do->operational_status_label,
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

        $query = Invoice::with(['customer', 'payments'])->whereIn('status', ['invoice', 'termin', 'paid']);

        if ($customerId) $query->where('customer_id', $customerId);
        if ($jenis === 'unpaid') $query->whereIn('status', ['invoice', 'termin']);
        if ($jenis === 'paid')   $query->where('status', 'paid');
        if ($sdTanggal)          $query->whereDate('tgl_buat', '<=', $sdTanggal);

        // Ringkasan harus mencakup seluruh hasil filter, bukan hanya 25 baris
        // yang sedang terlihat pada halaman paginator.
        $summaryInvoices = (clone $query)->get();
        $invoiceCount = $summaryInvoices->count();
        $sumJual = (float) $summaryInvoices->sum(fn (Invoice $invoice) => (float) $invoice->total_jual);
        $sumHpp = (float) $summaryInvoices->sum(fn (Invoice $invoice) => (float) $invoice->total_hpp);
        $sumLaba = $sumJual - $sumHpp;
        $sumPaid = (float) $summaryInvoices->sum(fn (Invoice $invoice) => $invoice->total_paid);
        $sumOutstanding = (float) $summaryInvoices->sum(fn (Invoice $invoice) => $invoice->outstanding);

        $invoices = $query->orderByDesc('tgl_buat')->paginate(25)->withQueryString();

        $customers = Customer::orderBy('company_name')->get(['id', 'company_name']);

        return view('logistic_reports.invoice', compact(
            'invoices', 'customers', 'customerId', 'jenis', 'sdTanggal',
            'invoiceCount', 'sumJual', 'sumHpp', 'sumLaba', 'sumPaid', 'sumOutstanding'
        ));
    }

    public function invoiceExport(Request $request)
    {
        $customerId = $request->get('customer_id');
        $jenis      = $request->get('jenis', 'all');
        $sdTanggal  = $request->get('sd_tanggal');

        $query = Invoice::with(['customer', 'payments'])->whereIn('status', ['invoice', 'termin', 'paid']);
        if ($customerId) $query->where('customer_id', $customerId);
        if ($jenis === 'unpaid') $query->whereIn('status', ['invoice', 'termin']);
        if ($jenis === 'paid')   $query->where('status', 'paid');
        if ($sdTanggal)          $query->whereDate('tgl_buat', '<=', $sdTanggal);

        $invoices = $query->orderByDesc('tgl_buat')->get();

        $headers = ['No Urut Inv', 'Submit', 'No Invoice', 'Customer', 'HPP', 'Harga Jual', 'Laba', 'Terbayar', 'Outstanding Belum Terbayar', 'Due Date', 'Umur (hari)', 'Status', 'Tgl Cair'];
        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                $inv->invoice_id, $inv->tgl_buat?->format('Y-m-d'), $inv->invoice_number,
                $inv->customer?->company_name ?? '-',
                (float) $inv->total_hpp, (float) $inv->total_jual, (float) $inv->laba,
                (float) $inv->total_paid, (float) $inv->outstanding,
                $inv->tgl_tempo?->format('Y-m-d'), $inv->umur_hari,
                $inv->status_label, $inv->tgl_pencairan?->format('Y-m-d'),
            ];
        }

        return \App\Helpers\ExcelExport::download('laporan-invoice-' . date('Ymd'), $headers, $rows, 'Laporan Invoice');
    }

    // ─────────────────── LAPORAN OUTSTANDING / PIUTANG ───────────────────

    /** Bucket umur piutang: key => label. */
    public const AGING_BUCKETS = [
        'current' => 'Belum Jatuh Tempo',
        '1_30'    => 'Lewat 1-30 Hari',
        '31_60'   => 'Lewat 31-60 Hari',
        '61_90'   => 'Lewat 61-90 Hari',
        '90_plus' => 'Lewat > 90 Hari',
    ];

    public function outstanding(Request $request)
    {
        $data = $this->outstandingDataset($request);

        $perPage = 25;
        $page    = LengthAwarePaginator::resolveCurrentPage();
        $rows    = new LengthAwarePaginator(
            $data['rows']->forPage($page, $perPage)->values(),
            $data['rows']->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('logistic_reports.outstanding', array_merge($data, [
            'rows'      => $rows,
            'customers' => Customer::orderBy('company_name')->get(['id', 'company_name']),
            'buckets'   => self::AGING_BUCKETS,
        ]));
    }

    public function outstandingExport(Request $request)
    {
        $data = $this->outstandingDataset($request);

        $headers = ['No Urut Inv', 'No Invoice', 'Customer', 'Tgl Invoice', 'Due Date', 'Lewat Tempo (hari)', 'Kategori Umur', 'Grand Total', 'Terbayar', 'Outstanding', 'Status'];
        $rows = [];
        foreach ($data['rows'] as $r) {
            /** @var Invoice $inv */
            $inv = $r['invoice'];
            $rows[] = [
                $inv->invoice_id,
                $inv->invoice_number,
                $inv->customer?->company_name ?? '-',
                $inv->tgl_buat?->format('Y-m-d'),
                $inv->tgl_tempo?->format('Y-m-d'),
                $r['days_overdue'],
                self::AGING_BUCKETS[$r['bucket']] ?? '-',
                $r['grand_total'],
                $r['paid'],
                $r['outstanding'],
                $inv->status_label,
            ];
        }

        return \App\Helpers\ExcelExport::download('laporan-outstanding-' . date('Ymd'), $headers, $rows, 'Outstanding Piutang');
    }

    /**
     * Kumpulkan piutang belum terbayar: invoice yang sudah terkirim
     * (status invoice/termin) dan masih menyisakan outstanding > 0.
     *
     * Ringkasan bucket umur dihitung SEBELUM filter aging diterapkan, supaya
     * kartu bucket tetap bisa dipakai sebagai drill-down.
     */
    private function outstandingDataset(Request $request): array
    {
        $customerId = $request->get('customer_id');
        $aging      = $request->get('aging', 'all');  // all|overdue|current|1_30|31_60|61_90|90_plus
        $sdTanggal  = $request->get('sd_tanggal');      // s/d tanggal invoice dibuat
        $search     = $request->get('search');

        // Filter yang sama dipakai untuk piutang terbit maupun ringkasan draft.
        $applyFilters = function ($query) use ($customerId, $sdTanggal, $search) {
            if ($customerId) $query->where('customer_id', $customerId);
            if ($sdTanggal)  $query->whereDate('tgl_buat', '<=', $sdTanggal);
            if ($search) {
                $query->where(fn($q) => $q
                    ->where('invoice_number', 'like', "%$search%")
                    ->orWhere('invoice_id', 'like', "%$search%")
                    ->orWhereHas('customer', fn($c) => $c->where('company_name', 'like', "%$search%")));
            }

            return $query;
        };

        $query = $applyFilters(
            Invoice::with(['customer', 'payments'])
                ->whereIn('status', ['invoice', 'termin']) // draft belum ditagih, paid sudah lunas
        );

        // Draft = pekerjaan selesai yang tagihannya belum diterbitkan. Sengaja
        // dipisah dari piutang resmi supaya tidak tercampur ke aging, tetapi
        // tetap terlihat karena ini nilai yang paling mudah terlupakan.
        $draftInvoices = $applyFilters(
            Invoice::with('customer')->where('status', 'draft')
        )->get();
        $draftSummary = [
            'count'   => $draftInvoices->count(),
            'clients' => $draftInvoices->pluck('customer_id')->unique()->count(),
            'amount'  => (float) $draftInvoices->sum(
                fn(Invoice $invoice) => (float) ($invoice->grand_total ?: $invoice->total_jual)
            ),
        ];

        $today = now()->startOfDay();

        $all = $query->get()
            ->map(function (Invoice $inv) use ($today) {
                $due         = $inv->tgl_tempo?->copy()->startOfDay();
                $daysOverdue = $due ? (int) $due->diffInDays($today, false) : null;

                return [
                    'invoice'      => $inv,
                    'grand_total'  => (float) ($inv->grand_total ?: $inv->total_jual),
                    'paid'         => (float) $inv->total_paid,
                    'outstanding'  => (float) $inv->outstanding,
                    'days_overdue' => $daysOverdue,
                    'bucket'       => $this->agingBucket($daysOverdue),
                ];
            })
            ->filter(fn(array $r) => $r['outstanding'] > 0)
            ->sortByDesc(fn(array $r) => $r['days_overdue'] ?? PHP_INT_MIN)
            ->values();

        // Ringkasan per bucket — belum kena filter aging.
        $bucketSummary = [];
        foreach (array_keys(self::AGING_BUCKETS) as $key) {
            $inBucket = $all->where('bucket', $key);
            $bucketSummary[$key] = [
                'count'  => $inBucket->count(),
                'amount' => (float) $inBucket->sum('outstanding'),
            ];
        }

        $rows = match (true) {
            $aging === 'overdue' => $all->where('bucket', '!=', 'current')->values(),
            array_key_exists($aging, self::AGING_BUCKETS) => $all->where('bucket', $aging)->values(),
            default => $all,
        };

        // Rekap per client mengikuti seluruh filter (termasuk aging).
        $perCustomer = $rows
            ->groupBy(fn(array $r) => $r['invoice']->customer?->company_name ?? '-')
            ->map(fn($group, $name) => [
                'customer'    => $name,
                'count'       => $group->count(),
                'grand_total' => (float) $group->sum('grand_total'),
                'paid'        => (float) $group->sum('paid'),
                'outstanding' => (float) $group->sum('outstanding'),
                'overdue'     => (float) $group->where('bucket', '!=', 'current')->sum('outstanding'),
                'oldest'      => $group->max(fn(array $r) => $r['days_overdue'] ?? PHP_INT_MIN),
            ])
            ->sortByDesc('outstanding')
            ->values();

        return [
            'rows'             => $rows,
            'bucketSummary'    => $bucketSummary,
            'draftSummary'     => $draftSummary,
            'perCustomer'      => $perCustomer,
            'customerId'       => $customerId,
            'aging'            => $aging,
            'sdTanggal'        => $sdTanggal,
            'search'           => $search,
            'totalCount'       => $rows->count(),
            'totalClient'      => $perCustomer->count(),
            'totalTagihan'     => (float) $rows->sum('grand_total'),
            'totalPaid'        => (float) $rows->sum('paid'),
            'totalOutstanding' => (float) $rows->sum('outstanding'),
            'totalOverdue'     => (float) $rows->where('bucket', '!=', 'current')->sum('outstanding'),
        ];
    }

    private function agingBucket(?int $daysOverdue): string
    {
        if ($daysOverdue === null || $daysOverdue <= 0) return 'current';
        if ($daysOverdue <= 30) return '1_30';
        if ($daysOverdue <= 60) return '31_60';
        if ($daysOverdue <= 90) return '61_90';
        return '90_plus';
    }
}
