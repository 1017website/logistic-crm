<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\DeliveryOrder;
use App\Models\Notification;
use App\Models\Vendor;
use App\Models\User;
use App\Services\DeliveryOrderTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
    public function returnToRequest(Request $request, DeliveryOrder $deliveryOrder)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $order = DB::transaction(function () use ($deliveryOrder, $data) {
            $order = \App\Models\RequestOrder::lockForUpdate()->findOrFail($deliveryOrder->request_order_id);
            $do = DeliveryOrder::lockForUpdate()->findOrFail($deliveryOrder->id);
            $do->setRelation('requestOrder', $order);
            if (!$do->can_return_to_request) {
                throw ValidationException::withMessages(['general' => 'Hanya DO tahap Surat Jalan yang belum ditagihkan dapat dikembalikan ke RDO.']);
            }

            $note = 'DO dikembalikan ke RDO oleh ' . auth()->user()->name . '. Alasan: ' . $data['reason'];
            $do->transition('returned_to_rdo', $note, auth()->id());
            $do->delete();
            $order->update(['do_approved' => false, 'price_correction_open' => false]);
            $order->transition('finance', $note, auth()->id());

            return $order;
        });

        return redirect()->route('request-orders.show', $order)
            ->with('success', 'DO dikembalikan ke RDO untuk perbaikan harga/biaya oleh Finance dan approval ulang.');
    }

    public function index(Request $request)
    {
        $search    = $request->get('search');
        $status    = $request->get('status');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = DeliveryOrder::with(['customer', 'vendor', 'salesUser', 'requestOrder.items', 'requestOrder.jobDetails'])
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
        $closed = DeliveryOrder::with(['requestOrder.items', 'requestOrder.jobDetails'])
            ->whereBetween('do_date', [$startDate, $endDate])
            ->whereIn('status', ['closed', 'invoiced', 'paid'])->get();

        $revenue     = $closed->sum(fn($d) => $d->total_revenue);
        $totalCost   = $closed->sum(fn($d) => $d->total_cost);
        $grossProfit = $revenue - $totalCost;
        // Volume DO = jumlah record Delivery Order yang benar-benar dibuat pada periode.
        $volumeDo    = DeliveryOrder::whereBetween('do_date', [$startDate, $endDate])->count();

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
            'requestOrder.items', 'requestOrder.jobDetails', 'requestOrder.salesUser',
            'invoiceItems.invoice',
            'statusLogs.user',
        ]);
        $vendors = Vendor::where('status', 'Active')->orderBy('vendor_name')->get();
        return view('delivery_orders.show', compact('deliveryOrder', 'vendors'));
    }

    public function completeVendor(Request $request, DeliveryOrder $deliveryOrder)
    {
        $data = $request->validate([
            'vendor_id' => ['required', \Illuminate\Validation\Rule::exists('vendors', 'id')
                ->where('status', 'Active')->whereNull('deleted_at')],
        ]);

        DB::transaction(function () use ($data, $deliveryOrder) {
            $order = \App\Models\RequestOrder::whereKey($deliveryOrder->request_order_id)->lockForUpdate()->firstOrFail();
            $do = DeliveryOrder::whereKey($deliveryOrder->id)->lockForUpdate()->firstOrFail();
            if ($do->vendor_id) {
                throw ValidationException::withMessages(['vendor_id' => 'Vendor DO sudah terisi. Muat ulang halaman.']);
            }
            if ($order->vendor_id && (int) $order->vendor_id !== (int) $data['vendor_id']) {
                throw ValidationException::withMessages(['vendor_id' => 'Pilih vendor yang sama dengan Request DO.']);
            }

            $vendor = Vendor::findOrFail($data['vendor_id']);
            $do->update(['vendor_id' => $vendor->id, 'assignment_type' => strtolower($vendor->vendor_type)]);
            $order->update(['vendor_id' => $vendor->id]);
            $note = 'Vendor armada dilengkapi: ' . $vendor->vendor_name . ' (' . $vendor->vendor_type . ').';
            \App\Models\OrderStatusLog::record($do, $do->status, $do->status, auth()->id(), $note);
            \App\Models\OrderStatusLog::record($order, $order->request_status, $order->request_status, auth()->id(), $note);
        });

        return back()->with('success', 'Vendor DO dan Request DO berhasil dilengkapi.');
    }

    /** Halaman publik yang dibuka saat QR Surat Jalan dipindai. */
    public function track(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->load([
            'customer',
            'requestOrder',
            'statusLogs',
        ]);
        $companyName = \App\Models\Setting::get('company_name', 'PT Firman Tangguh Logistik');

        return view('delivery_orders.track', compact('deliveryOrder', 'companyName'));
    }

    // ─────────────────── SURAT JALAN (cetak internal / upload eksternal -> pickup) ───────────────────
    public function uploadSuratJalan(Request $request, DeliveryOrder $deliveryOrder)
    {
        if (!$deliveryOrder->vendor_id || !in_array($deliveryOrder->assignment_type, ['internal', 'external'], true)) {
            return back()->withErrors(['general' => 'Lengkapi vendor armada sebelum menerbitkan surat jalan.']);
        }
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
                $request->note ?: 'POD diunggah. Menunggu verifikasi dan penutupan DO.',
                auth()->id()
            );
        });

        return back()->with('success', 'POD diunggah. Verifikasi POD dan tutup DO agar siap invoice.');
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
            Customer::query()->lockForUpdate()->findOrFail($deliveryOrder->customer_id);
            $locked = DeliveryOrder::query()->lockForUpdate()->findOrFail($deliveryOrder->id);
            if ($locked->status !== 'verifikasi_pod') {
                abort(422, 'DO belum siap ditutup atau sudah pernah ditutup.');
            }
            if (!$locked->pod_at) {
                throw ValidationException::withMessages(['general' => 'POD harus diunggah sebelum DO diverifikasi dan ditutup.']);
            }
            $locked->load('requestOrder');
            if (!$locked->requestOrder?->do_approved) {
                throw ValidationException::withMessages([
                    'general' => 'Harga DO belum disetujui. Minta Sales Manager menyetujui harga di halaman DO ini sebelum menutup DO.',
                ]);
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

        User::where('role', 'Finance')->where('status', 'Active')->each(function (User $finance) use ($deliveryOrder) {
            Notification::send(
                $finance->id,
                'delivery_order_ready_invoice',
                'DO siap invoice',
                'DO ' . $deliveryOrder->do_number . ' telah ditutup. Pilih DO untuk ditambahkan ke draft invoice.',
                route('invoices.index', ['tab' => 'ready', 'customer_id' => $deliveryOrder->customer_id])
            );
        });

        return back()->with('success', 'DO ditutup. DO tersedia di tab DO Siap Invoice untuk ditambahkan ke draft.');
    }

    public function approvePrice(Request $request, DeliveryOrder $deliveryOrder)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        DB::transaction(function () use ($deliveryOrder, $data) {
            $order = \App\Models\RequestOrder::lockForUpdate()->findOrFail($deliveryOrder->request_order_id);
            $do = DeliveryOrder::lockForUpdate()->findOrFail($deliveryOrder->id);
            abort_unless(in_array($do->status, ['surat_jalan', 'pickup', 'in_delivery', 'pod', 'verifikasi_pod'], true), 422);
            $order->update(['do_approved' => true, 'price_correction_open' => false]);
            $note = $data['note'] ?? 'Harga disetujui Sales Manager melalui Delivery Order.';
            \App\Models\OrderStatusLog::record($order, null, 'do_approved', auth()->id(), $note);
            \App\Models\OrderStatusLog::record($do, $do->status, $do->status, auth()->id(), $note);
        });
        return back()->with('success', 'Harga DO disetujui. Proses dapat dilanjutkan di Delivery Order.');
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

    // ─────────────────── CETAK SURAT JALAN INTERNAL (HTML + QR tracking) ───────────────────
    public function printSuratJalan(DeliveryOrder $deliveryOrder, DeliveryOrderTrackingService $trackingService)
    {
        abort_unless($deliveryOrder->vendor_id && $deliveryOrder->assignment_type === 'internal', 422, 'Pilih vendor armada internal sebelum mencetak surat jalan.');
        $deliveryOrder->load(['customer', 'vendor', 'salesUser', 'requestOrder.items']);

        $companyName = \App\Models\Setting::get('company_name', 'Perusahaan');
        $company = [
            'name'    => $companyName,
            'address' => \App\Models\Setting::get('company_address', ''),
            'phone'   => \App\Models\Setting::get('company_phone', ''),
            'email'   => \App\Models\Setting::get('company_email', ''),
            'website' => \App\Models\Setting::get('company_website', ''),
            'logo'    => \App\Models\Setting::get('company_doc_logo') ?: \App\Models\Setting::get('company_logo', ''),
        ];
        $tracking = $trackingService->make($deliveryOrder);

        return view('delivery_orders.surat_jalan_print', compact('deliveryOrder', 'company', 'tracking'));
    }

    public function destroy(DeliveryOrder $deliveryOrder)
    {
        // DO yang komponennya sudah masuk invoice tidak boleh dihapus: invoicenya
        // akan menggantung tanpa DO dan nomor DO hilang dari cetakan.
        if ($deliveryOrder->invoiceItems()->exists()) {
            return back()->withErrors([
                'general' => 'DO ' . $deliveryOrder->do_number . ' sudah masuk invoice dan tidak dapat dihapus. Hapus invoicenya lebih dulu.',
            ]);
        }

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

        $headers = ['DO Number', 'Tgl DO', 'Request DO', 'Customer', 'Armada/Vendor', 'Tipe', 'Origin', 'Destination', 'Flow', 'Pickup', 'Delivery', 'Revenue', 'Actual Cost', 'Other Cost', 'Gross Profit', 'POD At', 'Closed At'];

        $rows = [];
        foreach ($dos as $d) {
            $rows[] = [
                $d->do_number,
                $d->do_date?->format('Y-m-d'),
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
