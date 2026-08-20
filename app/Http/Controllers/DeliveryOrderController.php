<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Vendor;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * DELIVERY ORDER (DO final) — tahap 2 alur fulfillment.
 *
 *   surat_jalan (cetak internal / upload eksternal -> pickup)
 *   -> pickup -> in_delivery
 *   -> pod (upload foto POD)
 *   -> verifikasi_pod (Sales Admin)
 *   -> closed (input biaya aktual, tutup DO)
 *   -> invoiced (Finance: invoice customer / tagihan vendor)
 *   -> paid
 */
class DeliveryOrderController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $status    = $request->get('status');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = DeliveryOrder::with(['customer', 'vendor', 'salesUser', 'requestOrder.items'])
            ->whereBetween('do_date', [$startDate, $endDate]);

        if ($status && $status !== 'all') $query->where('status', $status);
        if ($search) {
            $query->where(fn($q) => $q
                ->where('do_number', 'like', "%$search%")
                ->orWhere('fleet_info', 'like', "%$search%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%")));
        }

        $dos = $query->orderByDesc('do_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // KPI
        $closed = DeliveryOrder::with('requestOrder.items')
            ->whereBetween('do_date', [$startDate, $endDate])
            ->whereIn('status', ['closed', 'invoiced', 'paid'])->get();

        $revenue     = $closed->sum(fn($d) => $d->total_revenue);
        $totalCost   = $closed->sum(fn($d) => $d->total_cost);
        $grossProfit = $revenue - $totalCost;
        $volumeDo    = $dos->total();

        $pendingDeletionDoIds = \App\Models\DeletionRequest::pendingIdsFor(DeliveryOrder::class);
        $flowOptions = DeliveryOrder::FLOW;

        return view('delivery_orders.index', compact(
            'dos', 'revenue', 'grossProfit', 'volumeDo', 'totalCost',
            'search', 'status', 'flowOptions', 'startDate', 'endDate', 'pendingDeletionDoIds'
        ));
    }

    public function show(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load([
            'customer', 'vendor', 'salesUser', 'podVerifier', 'closer',
            'requestOrder.items', 'requestOrder.salesUser',
            'invoiceItems.invoice',
            'statusLogs.user',
        ]);
        return view('delivery_orders.show', compact('deliveryOrder'));
    }

    // ─────────────────── SURAT JALAN (cetak internal / upload eksternal -> pickup) ───────────────────
    public function uploadSuratJalan(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'surat_jalan') {
            return back()->withErrors(['general' => 'DO tidak berada di tahap surat jalan.']);
        }

        $isInternal = $deliveryOrder->assignment_type === 'internal';
        $request->validate([
            'surat_jalan_file' => ($isInternal ? 'nullable' : 'required') . '|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'note'             => 'nullable|string|max:1000',
        ]);

        if ($isInternal) {
            $deliveryOrder->transition(
                'pickup',
                $request->note ?: 'Surat jalan internal diterbitkan dari sistem.',
                auth()->id()
            );

            return back()->with('success', 'Surat jalan internal dikonfirmasi. DO siap pickup.');
        }

        $path = $request->file('surat_jalan_file')->store('delivery-orders/surat-jalan', 'public');
        $deliveryOrder->update(['surat_jalan_file' => $path]);
        $deliveryOrder->transition('pickup', $request->note ?: 'Surat jalan diterbitkan.', auth()->id());

        return back()->with('success', 'Surat jalan diunggah. DO siap pickup.');
    }

    // ─────────────────── PICKUP -> DELIVERY ───────────────────
    public function markPickup(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'pickup') {
            return back()->withErrors(['general' => 'DO tidak berada di tahap pickup.']);
        }
        $deliveryOrder->update(['pickup_date' => $request->pickup_date ?: now()->toDateString()]);
        $deliveryOrder->transition('in_delivery', $request->note ?: 'Barang sudah dipickup, dalam perjalanan.', auth()->id());
        return back()->with('success', 'Status diperbarui: dalam pengiriman.');
    }

    // ─────────────────── DELIVERY -> POD ───────────────────
    public function markDelivered(Request $request, DeliveryOrder $deliveryOrder)
    {
        if ($deliveryOrder->status !== 'in_delivery') {
            return back()->withErrors(['general' => 'DO tidak berada di tahap delivery.']);
        }
        $deliveryOrder->update(['delivery_date' => $request->delivery_date ?: now()->toDateString()]);
        $deliveryOrder->transition('pod', $request->note ?: 'Barang sampai tujuan, menunggu POD.', auth()->id());
        return back()->with('success', 'Status diperbarui: menunggu POD.');
    }

    // ─────────────────── POD (upload foto bukti terima) ───────────────────
    public function uploadPod(Request $request, DeliveryOrder $deliveryOrder)
    {
        $request->validate([
            'pod_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'note'     => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $deliveryOrder) {
            $locked = DeliveryOrder::query()->lockForUpdate()->findOrFail($deliveryOrder->id);
            if ($locked->status !== 'pod') {
                abort(422, 'DO tidak berada di tahap POD atau POD sudah pernah diunggah.');
            }

            $path = $request->file('pod_file')->store('delivery-orders/pod', 'public');
            $locked->update(['pod_file' => $path, 'pod_at' => now()]);
            $locked->transition(
                'verifikasi_pod',
                $request->note ?: 'POD diunggah. DO siap dipilih untuk invoice.',
                auth()->id()
            );
        });

        return back()->with('success', 'POD diunggah. DO sudah tersedia di menu Invoice sambil menunggu verifikasi.');
    }

    // ─────────────────── VERIFIKASI POD + INPUT BIAYA + TUTUP DO (Sales Admin) ───────────────────
    public function closeDo(Request $request, DeliveryOrder $deliveryOrder)
    {
        $request->validate([
            'actual_cost' => 'required|numeric|min:0',
            'other_cost'  => 'nullable|numeric|min:0',
            'note'        => 'nullable|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $deliveryOrder) {
            $locked = DeliveryOrder::query()->lockForUpdate()->findOrFail($deliveryOrder->id);
            if ($locked->status !== 'verifikasi_pod') {
                abort(422, 'DO belum siap ditutup atau sudah pernah ditutup.');
            }

            $locked->update([
                'pod_verified_by' => auth()->id(),
                'pod_verified_at' => now(),
                'actual_cost'     => $request->actual_cost,
                'other_cost'      => $request->other_cost ?? 0,
                'closed_by'       => auth()->id(),
                'closed_at'       => now(),
            ]);
            $targetStatus = match ($locked->invoice_status) {
                'paid' => 'paid',
                'invoiced' => 'invoiced',
                default => 'closed',
            };
            $locked->transition(
                $targetStatus,
                $request->note ?: 'POD terverifikasi, biaya diinput, DO ditutup.',
                auth()->id()
            );

            // Tandai request order terkait sebagai Done.
            $locked->requestOrder?->update(['status' => 'Done']);
        });

        return back()->with('success', 'DO ditutup. Siap diteruskan ke Finance.');
    }

    // ─────────────────── FINANCE: INVOICE ───────────────────
    public function invoice(Request $request, DeliveryOrder $deliveryOrder)
    {
        return redirect()->route('invoices.index')
            ->with('warning', 'Pembuatan invoice dipusatkan di menu Invoice agar multi-DO dan tipe layanan tidak tercatat ganda.');
    }

    // ─────────────────── FINANCE: PAYMENT ───────────────────
    public function pay(Request $request, DeliveryOrder $deliveryOrder)
    {
        return redirect()->route('invoices.index', ['tab' => 'invoice'])
            ->with('warning', 'Pelunasan DO mengikuti pembayaran invoice terkait dan tidak dapat dilakukan terpisah.');
    }

    // ─────────────────── CETAK SURAT JALAN INTERNAL (HTML + barcode) ───────────────────
    public function printSuratJalan(DeliveryOrder $deliveryOrder, \App\Services\DocumentSignatureService $documentSignature)
    {
        $deliveryOrder->load(['customer', 'vendor', 'salesUser', 'requestOrder.items']);

        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $company = [
            'name'    => $companyName,
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone'   => \App\Models\Setting::get('company_phone', ''),
            'email'   => \App\Models\Setting::get('company_email', ''),
            'website' => \App\Models\Setting::get('company_website', ''),
            'logo'    => \App\Models\Setting::get('company_doc_logo') ?: \App\Models\Setting::get('company_logo', ''),
            'signatory_name' => \App\Models\Setting::get('company_signatory_name') ?: $companyName,
            'signatory_title' => \App\Models\Setting::get('company_signatory_title') ?: 'Direktur',
        ];
        $signature = $documentSignature->make('delivery_order', $deliveryOrder->getKey());

        return view('delivery_orders.surat_jalan_print', compact('deliveryOrder', 'company', 'signature'));
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        $no = $deliveryOrder->do_number;
        // bersihkan file
        foreach (['surat_jalan_file', 'pod_file'] as $col) {
            if ($deliveryOrder->$col) Storage::disk('public')->delete($deliveryOrder->$col);
        }
        $deliveryOrder->delete();
        return redirect()->route('delivery-orders.index')->with('success', 'DO ' . $no . ' berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $dos = DeliveryOrder::with(['customer', 'vendor', 'requestOrder.items'])
            ->whereBetween('do_date', [$startDate, $endDate])
            ->orderByDesc('do_date')->get();

        $headers = ['DO Number', 'Request DO', 'Customer', 'Armada/Vendor', 'Tipe', 'Origin', 'Destination', 'Flow', 'Pickup', 'Delivery', 'Revenue', 'Actual Cost', 'Other Cost', 'Gross Profit', 'POD At', 'Closed At'];

        $rows = [];
        foreach ($dos as $d) {
            $rows[] = [
                $d->do_number,
                $d->requestOrder?->do_number ?? '-',
                $d->customer?->company_name ?? '-',
                $d->fleet_info ?? ($d->vendor?->vendor_name ?? '-'),
                $d->assignment_type,
                $d->origin, $d->destination,
                $d->flow_label,
                $d->pickup_date?->format('Y-m-d'), $d->delivery_date?->format('Y-m-d'),
                (float) $d->total_revenue, (float) $d->actual_cost, (float) $d->other_cost,
                (float) $d->gross_profit,
                $d->pod_at?->format('Y-m-d H:i'), $d->closed_at?->format('Y-m-d H:i'),
            ];
        }

        return \App\Helpers\ExcelExport::download(
            'delivery-orders-' . $startDate . '-sd-' . $endDate, $headers, $rows, 'Delivery Orders'
        );
    }
}
