<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\RequestOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * INVOICE — penagihan multi-DO per customer.
 *
 * Alur status: draft -> invoice (submit) -> paid (pencairan).
 * Penomoran invoice_number per customer JALAN TERUS (tidak reset):
 *   {seq}/{invoice_code customer}/FTINV/{romawi bulan}/{tahun}
 */
class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $tab    = $request->get('tab', 'draft');     // draft|invoice|paid
        $search = $request->get('search');
        $customerId = $request->get('customer_id');
        $jenis = $request->get('jenis', 'all');

        $statusMap = ['draft' => 'draft', 'invoice' => 'invoice', 'paid' => 'paid'];
        $status = $statusMap[$tab] ?? 'draft';

        $query = Invoice::with(['customer', 'operator', 'items'])
            ->where('status', $status);

        if ($customerId) $query->where('customer_id', $customerId);
        if (array_key_exists($jenis, Invoice::TYPES)) $query->where('jenis', $jenis);
        if ($search) {
            $query->where(fn($q) => $q
                ->where('invoice_id', 'like', "%$search%")
                ->orWhere('invoice_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%")));
        }

        $invoices = $query->orderByDesc('id')->paginate(25)->withQueryString();

        $customers = Customer::orderBy('company_name')->get(['id', 'company_name', 'customer_code', 'invoice_code']);

        $pendingDeletionIds = \App\Models\DeletionRequest::pendingIdsFor(Invoice::class);

        return view('invoices.index', compact(
            'invoices', 'tab', 'status', 'search', 'customers', 'customerId', 'jenis', 'pendingDeletionIds'
        ));
    }

    /** Ambil DO yang siap ditagih untuk customer tertentu (approved & belum diinvoice). */
    public function availableDos(Request $request)
    {
        $customerId = $request->get('customer_id');
        if (!$customerId) return response()->json([]);

        $dos = RequestOrder::with('jobDetails')
            ->where('customer_id', $customerId)
            ->where('do_approved', true)
            ->where('invoice_status', 'uninvoiced')
            ->orderByDesc('order_date')
            ->get()
            ->map(fn($do) => [
                'id'          => $do->id,
                'do_number'   => $do->do_number,
                'order_date'  => $do->order_date?->format('d M Y'),
                'origin'      => $do->origin,
                'destination' => $do->destination,
                'tujuan'      => $do->tujuan,
                'no_container'=> $do->no_container,
                'hpp'         => (float) $do->total_cost,
                'jual'        => (float) $do->total_revenue,
            ]);

        return response()->json($dos);
    }

    public function store(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'tgl_buat'    => 'required|date',
            'tgl_tempo'   => 'nullable|date',
            'do_ids'      => 'required|array|min:1',
            'do_ids.*'    => 'exists:request_orders,id',
            'jenis'       => 'required|in:TR,NTR',
            'ppn_mode'    => 'required|in:ppn,non_ppn',
            'ppn_persen'  => 'required_if:ppn_mode,ppn|nullable|numeric|min:0.01|max:100',
            'notes'       => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($request) {
            $customer = Customer::findOrFail($request->customer_id);

            $dos = RequestOrder::with('jobDetails')
                ->whereIn('id', $request->do_ids)
                ->where('customer_id', $customer->id)
                ->where('do_approved', true)
                ->where('invoice_status', 'uninvoiced')
                ->get();

            $requestedDoCount = collect($request->do_ids)->unique()->count();
            if ($dos->count() !== $requestedDoCount) {
                abort(422, 'Ada DO yang belum di-approve, sudah masuk invoice lain, atau bukan milik customer terpilih.');
            }

            $seq = Invoice::nextCustomerSeq($customer->id);

            $invoice = Invoice::create([
                'invoice_id'     => Invoice::generateInvoiceId(),
                'invoice_number' => Invoice::buildInvoiceNumber($seq, $customer->invoice_number_code, \Carbon\Carbon::parse($request->tgl_buat)),
                'customer_seq'   => $seq,
                'customer_id'    => $customer->id,
                'status'         => 'draft',
                'tgl_buat'       => $request->tgl_buat,
                'tgl_tempo'      => $request->tgl_tempo,
                'jenis'          => $request->jenis,
                'operator_id'    => auth()->id(),
                'notes'          => $request->notes,
            ]);

            $totalHpp = 0; $totalJual = 0;
            foreach ($dos as $do) {
                $hpp = (float) $do->total_cost;
                $jual = (float) $do->total_revenue;
                $invoice->items()->create([
                    'request_order_id' => $do->id,
                    'hpp'  => $hpp,
                    'jual' => $jual,
                ]);
                $do->update(['invoice_status' => 'invoiced']);
                $totalHpp += $hpp; $totalJual += $jual;
            }

            $ppnPersen = $request->ppn_mode === 'ppn' ? (float) $request->ppn_persen : 0;
            $this->recalcTotals($invoice, $totalHpp, $totalJual, $ppnPersen);
        });

        return redirect()->route('invoices.index', ['tab' => 'draft'])
            ->with('success', 'Invoice draft dibuat.');
    }

    /** Submit draft -> invoice (terbit resmi). */
    public function submit(Request $request, Invoice $invoice)
    {
        if ($invoice->status !== 'draft') {
            return back()->withErrors(['general' => 'Hanya draft yang bisa di-submit.']);
        }
        $invoice->update(['status' => 'invoice']);
        return back()->with('success', 'Invoice resmi diterbitkan.');
    }

    /** Batalkan submit: invoice -> draft. */
    public function unsubmit(Invoice $invoice)
    {
        if ($invoice->status !== 'invoice') {
            return back()->withErrors(['general' => 'Hanya invoice yang bisa dibatalkan ke draft.']);
        }
        $invoice->update(['status' => 'draft']);
        return back()->with('success', 'Invoice dikembalikan ke draft.');
    }

    /** Pencairan: invoice -> paid. */
    public function pay(Request $request, Invoice $invoice)
    {
        $request->validate(['tgl_pencairan' => 'required|date']);
        if ($invoice->status !== 'invoice') {
            return back()->withErrors(['general' => 'Hanya invoice terbit yang bisa dicairkan.']);
        }
        DB::transaction(function () use ($invoice, $request) {
            $invoice->update(['status' => 'paid', 'tgl_pencairan' => $request->tgl_pencairan]);
            // tandai DO terkait lunas
            $doIds = $invoice->items()->pluck('request_order_id')->filter();
            RequestOrder::whereIn('id', $doIds)->update(['invoice_status' => 'paid']);
        });
        return back()->with('success', 'Invoice ditandai lunas (cair).');
    }

    public function updateNumber(Request $request, Invoice $invoice)
    {
        $request->validate(['invoice_number' => 'required|string|max:100']);
        $invoice->update(['invoice_number' => $request->invoice_number]);
        return back()->with('success', 'Nomor invoice diperbarui.');
    }

    public function updatePpn(Request $request, Invoice $invoice)
    {
        $request->validate(['ppn_persen' => 'required|numeric|min:0|max:100']);
        $this->recalcTotals($invoice, (float) $invoice->total_hpp, (float) $invoice->total_jual, (float) $request->ppn_persen);
        return back()->with('success', 'PPN diperbarui.');
    }

    public function show(Invoice $invoice)
    {
        $invoice->load(['customer', 'operator', 'items.requestOrder.jobDetails']);
        return view('invoices.show', compact('invoice'));
    }

    public function destroy(Invoice $invoice)
    {
        DB::transaction(function () use ($invoice) {
            // lepaskan DO agar bisa ditagih ulang
            $doIds = $invoice->items()->pluck('request_order_id')->filter();
            RequestOrder::whereIn('id', $doIds)->update(['invoice_status' => 'uninvoiced']);
            $invoice->items()->delete();
            $invoice->delete();
        });
        return back()->with('success', 'Invoice dihapus & DO dilepas untuk ditagih ulang.');
    }

    /** Cetak Pro Forma Invoice (HTML, siap print). */
    public function print(Request $request, Invoice $invoice)
    {
        $invoice->load(['customer', 'items.requestOrder']);
        $ppnPersen = $request->get('ppn', $invoice->ppn_persen);

        $company = [
            'name'    => \App\Models\Setting::get('company_name', 'Perusahaan'),
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone'   => \App\Models\Setting::get('company_phone', ''),
            'email'   => \App\Models\Setting::get('company_email', ''),
            'website' => \App\Models\Setting::get('company_website', ''),
            'logo'    => \App\Models\Setting::get('company_doc_logo') ?: \App\Models\Setting::get('company_logo', ''),
        ];

        return view('invoices.print', compact('invoice', 'ppnPersen', 'company'));
    }

    public function export(Request $request)
    {
        $status = $request->get('status');
        $customerId = $request->get('customer_id');
        $jenis = $request->get('jenis');

        $query = Invoice::with(['customer']);
        if ($status && $status !== 'all') $query->where('status', $status);
        if ($customerId) $query->where('customer_id', $customerId);
        if (array_key_exists((string) $jenis, Invoice::TYPES)) $query->where('jenis', $jenis);

        $invoices = $query->orderByDesc('tgl_buat')->get();

        $headers = ['Invoice ID', 'No Invoice', 'Customer', 'Tipe', 'Status', 'Tgl Buat', 'Tgl Tempo', 'HPP', 'Jual', 'Laba', 'PPN', 'Grand Total', 'Tgl Cair', 'Umur (hari)'];
        $rows = [];
        foreach ($invoices as $inv) {
            $rows[] = [
                $inv->invoice_id, $inv->invoice_number,
                $inv->customer?->company_name ?? '-',
                $inv->jenis_label,
                $inv->status_label,
                $inv->tgl_buat?->format('Y-m-d'), $inv->tgl_tempo?->format('Y-m-d'),
                (float) $inv->total_hpp, (float) $inv->total_jual, (float) $inv->laba,
                (float) $inv->ppn_nominal, (float) $inv->grand_total,
                $inv->tgl_pencairan?->format('Y-m-d'),
                $inv->umur_hari,
            ];
        }

        return \App\Helpers\ExcelExport::download('invoices-' . date('Ymd'), $headers, $rows, 'Invoices');
    }

    private function recalcTotals(Invoice $invoice, float $hpp, float $jual, float $ppnPersen): void
    {
        $ppnNominal = round($jual * $ppnPersen / 100);
        $invoice->update([
            'total_hpp'   => $hpp,
            'total_jual'  => $jual,
            'ppn_persen'  => $ppnPersen,
            'ppn_nominal' => $ppnNominal,
            'grand_total' => $jual + $ppnNominal,
        ]);
    }
}
