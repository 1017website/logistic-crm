<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\RequestOrder;
use App\Models\DeliveryOrder;
use App\Models\OrderAssignment;
use App\Models\Vendor;
use App\Models\VendorService;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REQUEST DO — tahap 1 alur fulfillment.
 *
 *   Sales (store, draft->verifikasi)
 *   -> Sales Admin (verify: approve->dispatch / reject)
 *   -> Transport Planner (dispatch: simpan penugasan -> approval)
 *   -> Sales Manager/Admin (approve: terbitkan DO final / reject)
 */
class RequestOrderController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $status    = $request->get('status');         // status legacy (Done/In Progress)
        $flow      = $request->get('flow');            // request_status
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = RequestOrder::with(['customer', 'vendor', 'lead', 'items', 'salesUser', 'verifier'])
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($status && $status !== 'all') $query->where('status', $status);
        if ($flow && $flow !== 'all')     $query->where('request_status', $flow);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('do_number', 'like', "%$search%")
                ->orWhere('tracking_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%"))
                ->orWhereHas('items', fn($q) => $q->where('service_name', 'like', "%$search%")));
        }

        $dos = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString();

        // KPI dari request yang sudah Done
        $allDone = RequestOrder::with('items')
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('status', 'Done')->where('currency', 'IDR')->get();

        $revenue     = $allDone->sum(fn($so) => $so->total_revenue);
        $totalCost   = $allDone->sum(fn($so) => $so->total_cost);
        $grossProfit = $revenue - $totalCost;
        $volumeDo    = $allDone->count();

        $customers = Customer::where('status', 'Existing')
            ->orderBy('company_name')->get(['id', 'company_name', 'user_id']);
        $vendors   = Vendor::where('status', 'Active')->orderBy('vendor_name')
            ->get(['id', 'vendor_name', 'vendor_type', 'service_type']);
        $salesUsers = User::orderBy('name')->get(['id', 'name']);
        $leads = Lead::where(function ($q) {
            $q->whereIn('pipeline_stage', ['Won', 'Maintaining'])->orWhereNotNull('customer_id');
        })->orderBy('company_name')->get(['id', 'company_name', 'lead_code', 'customer_id']);

        $vendorServices = VendorService::with('vendor')
            ->orderBy('service_name')->get(['id', 'vendor_id', 'service_name', 'unit', 'tariff', 'tariff_unit']);

        $pendingDeletionDoIds = \App\Models\DeletionRequest::pendingIdsFor(RequestOrder::class);

        $flowOptions = RequestOrder::FLOW;

        return view('request_orders.index', compact(
            'dos', 'revenue', 'grossProfit', 'volumeDo', 'totalCost',
            'customers', 'vendors', 'leads', 'vendorServices', 'salesUsers',
            'search', 'status', 'flow', 'flowOptions', 'startDate', 'endDate', 'pendingDeletionDoIds'
        ));
    }

    public function show(RequestOrder $requestOrder)
    {
        $requestOrder->load([
            'items', 'customer', 'vendor', 'lead', 'salesUser', 'verifier',
            'assignment.vendor', 'assignment.planner', 'assignment.approver',
            'statusLogs.user', 'deliveryOrder',
        ]);
        return view('request_orders.show', compact('requestOrder'));
    }

    public function store(Request $request)
    {
        $request->validate($this->rules());

        $customer = Customer::findOrFail($request->customer_id);
        if ($customer->status !== 'Existing') {
            return back()->withInput()->withErrors(['customer_id' => 'Customer harus berstatus Existing/Won sebelum dibuatkan Request DO.']);
        }

        $ro = DB::transaction(function () use ($request) {
            $userId = $request->lead_id ? (Lead::find($request->lead_id)?->user_id) : null;
            $userId = $request->user_id ?: ($userId ?? auth()->id());

            $ro = RequestOrder::create([
                'do_number'      => RequestOrder::generateDoNumber(),
                'customer_id'    => $request->customer_id,
                'vendor_id'      => $request->vendor_id,
                'lead_id'        => $request->lead_id,
                'user_id'        => $userId,
                'currency'       => $request->currency,
                'status'         => $request->status ?? 'In Progress',
                'request_status' => 'verifikasi', // langsung masuk antrian verifikasi sales admin
                'order_date'     => $request->order_date,
                'delivery_type'  => $request->delivery_type ? ucwords(strtolower(trim($request->delivery_type))) : null,
                'origin'         => $request->origin,
                'destination'    => $request->destination,
                'tracking_number'=> $request->tracking_number,
                'estimated_arrival' => $request->estimated_arrival,
                'pickup_date'    => $request->pickup_date,
                'notes'          => $request->notes,
            ] + $this->operationalFields($request));

            \App\Models\OrderStatusLog::record($ro, null, 'verifikasi', auth()->id(), 'Request DO dibuat dan masuk antrian verifikasi.');

            return $ro;
        });

        // Sales Admin hanya mengisi data request. Accounting/Finance melengkapi
        // item layanan dan harga dari halaman detail Request DO.
        if (auth()->user()?->isSalesAdmin()) {
            User::where('role', 'Finance')->where('status', 'Active')->each(function (User $accounting) use ($ro) {
                Notification::send(
                    $accounting->id,
                    'request_do_pricing',
                    'Request DO perlu dilengkapi',
                    $ro->do_number . ' telah dibuat. Silakan lengkapi item layanan dan harga.',
                    route('request-orders.show', $ro)
                );
            });
        }

        return redirect()->route('request-orders.index')->with('success', 'Request DO berhasil dibuat & masuk antrian verifikasi.');
    }

    public function edit(RequestOrder $requestOrder)
    {
        $requestOrder->load(['items', 'customer', 'vendor', 'lead']);
        $data = $requestOrder->toArray();
        $data['order_date']        = $requestOrder->order_date?->format('Y-m-d');
        $data['estimated_arrival'] = $requestOrder->estimated_arrival?->format('Y-m-d');
        $data['pickup_date']       = $requestOrder->pickup_date?->format('Y-m-d');
        return response()->json($data);
    }

    public function update(Request $request, RequestOrder $requestOrder)
    {
        // Hanya boleh edit jika belum disetujui (belum terbit DO).
        if (in_array($requestOrder->request_status, ['assigned'])) {
            return back()->withErrors(['general' => 'Request DO sudah disetujui & DO terbit, tidak bisa diedit.']);
        }

        $request->validate($this->rules());

        DB::transaction(function () use ($request, $requestOrder) {
            $requestOrder->update([
                'customer_id'    => $request->customer_id,
                'vendor_id'      => $request->vendor_id,
                'lead_id'        => $request->lead_id,
                'user_id'        => $request->user_id,
                'currency'       => $request->currency,
                'status'         => $request->status ?? $requestOrder->status,
                'order_date'     => $request->order_date,
                'delivery_type'  => $request->delivery_type ? ucwords(strtolower(trim($request->delivery_type))) : null,
                'origin'         => $request->origin,
                'destination'    => $request->destination,
                'tracking_number'=> $request->tracking_number,
                'estimated_arrival' => $request->estimated_arrival,
                'pickup_date'    => $request->pickup_date,
                'notes'          => $request->notes,
            ] + $this->operationalFields($request));

        });

        return redirect()->route('request-orders.index')->with('success', 'Request DO berhasil diperbarui.');
    }

    // ─────────────────── VERIFIKASI (Sales Admin) ───────────────────
    public function verify(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string|max:1000',
        ]);

        if ($requestOrder->request_status !== 'verifikasi') {
            return back()->withErrors(['general' => 'Request DO tidak berada di tahap verifikasi.']);
        }

        if ($request->action === 'approve') {
            $requestOrder->update([
                'verified_by' => auth()->id(),
                'verified_at' => now(),
                'verify_note' => $request->note,
            ]);
            $requestOrder->transition('dispatch', $request->note ?: 'Data terverifikasi.', auth()->id());
            $msg = 'Verifikasi disetujui. Diteruskan ke Transport Planner.';
        } else {
            $requestOrder->update(['verify_note' => $request->note]);
            $requestOrder->transition('rejected', $request->note ?: 'Verifikasi ditolak.', auth()->id());
            $msg = 'Request DO ditolak pada tahap verifikasi.';
        }

        return back()->with('success', $msg);
    }

    // ─────────────────── DISPATCH (Transport Planner) ───────────────────
    public function dispatchAssign(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'assignment_type' => 'required|in:internal,external',
            'vendor_id'       => 'required_if:assignment_type,external|nullable|exists:vendors,id',
            'fleet_info'      => 'nullable|string|max:255',
            'driver_name'     => 'nullable|string|max:255',
            'driver_phone'    => 'nullable|string|max:50',
            'estimated_cost'  => 'nullable|numeric|min:0',
            'notes'           => 'nullable|string|max:1000',
        ]);

        if ($requestOrder->request_status !== 'dispatch') {
            return back()->withErrors(['general' => 'Request DO tidak berada di tahap dispatch.']);
        }

        DB::transaction(function () use ($request, $requestOrder) {
            OrderAssignment::create([
                'request_order_id' => $requestOrder->id,
                'assignment_type'  => $request->assignment_type,
                'vendor_id'        => $request->assignment_type === 'external' ? $request->vendor_id : null,
                'fleet_info'       => $request->fleet_info,
                'driver_name'      => $request->driver_name,
                'driver_phone'     => $request->driver_phone,
                'estimated_cost'   => $request->estimated_cost ?? 0,
                'planned_by'       => auth()->id(),
                'approval_status'  => 'pending',
                'notes'            => $request->notes,
            ]);

            // sinkronkan vendor_id di request order bila eksternal
            if ($request->assignment_type === 'external' && $request->vendor_id) {
                $requestOrder->update(['vendor_id' => $request->vendor_id]);
            }

            $requestOrder->transition(
                'approval',
                'Penugasan diajukan oleh ' . auth()->user()->role . '.',
                auth()->id()
            );
        });

        return back()->with('success', 'Penugasan diajukan & menunggu approval.');
    }

    // ─────────────────── APPROVAL PENUGASAN (Manager/Admin) ───────────────────
    public function approveAssign(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'action' => 'required|in:approve,reject',
            'note'   => 'nullable|string|max:1000',
        ]);

        if ($requestOrder->request_status !== 'approval') {
            return back()->withErrors(['general' => 'Request DO tidak berada di tahap approval.']);
        }

        $assignment = $requestOrder->assignment()->where('approval_status', 'pending')->first();
        if (!$assignment) {
            return back()->withErrors(['general' => 'Tidak ada penugasan pending untuk disetujui.']);
        }

        if ($request->action === 'reject') {
            $assignment->update([
                'approval_status' => 'rejected',
                'approved_by'     => auth()->id(),
                'approved_at'     => now(),
                'approval_note'   => $request->note,
            ]);
            // kembalikan ke dispatch agar planner bisa atur ulang
            $requestOrder->transition('dispatch', $request->note ?: 'Penugasan ditolak, atur ulang.', auth()->id());
            return back()->with('success', 'Penugasan ditolak. Dikembalikan ke Transport Planner.');
        }

        // APPROVE → terbitkan DO final otomatis
        DB::transaction(function () use ($requestOrder, $assignment, $request) {
            $assignment->update([
                'approval_status' => 'approved',
                'approved_by'     => auth()->id(),
                'approved_at'     => now(),
                'approval_note'   => $request->note,
            ]);

            $requestOrder->transition('assigned', $request->note ?: 'Penugasan disetujui.', auth()->id());

            $do = DeliveryOrder::create([
                'do_number'        => DeliveryOrder::generateDoNumber(),
                'request_order_id' => $requestOrder->id,
                'customer_id'      => $requestOrder->customer_id,
                'vendor_id'        => $assignment->vendor_id,
                'user_id'          => $requestOrder->user_id,
                'status'           => 'surat_jalan',
                'assignment_type'  => $assignment->assignment_type,
                'fleet_info'       => $assignment->isExternal()
                                        ? ($assignment->vendor?->vendor_name ?? $assignment->fleet_info)
                                        : $assignment->fleet_info,
                'driver_name'      => $assignment->driver_name,
                'driver_phone'     => $assignment->driver_phone,
                'origin'           => $requestOrder->origin,
                'destination'      => $requestOrder->destination,
                'do_date'          => now()->toDateString(),
                'pickup_date'      => $requestOrder->pickup_date,
                'actual_cost'      => $assignment->estimated_cost ?? 0,
            ]);

            \App\Models\OrderStatusLog::record($do, null, 'surat_jalan', auth()->id(), 'DO terbit otomatis dari approval penugasan ' . $requestOrder->do_number . '.');

            // Lead Won/Closing -> Maintaining
            if ($requestOrder->lead_id) {
                $lead = Lead::find($requestOrder->lead_id);
                if ($lead && in_array($lead->pipeline_stage, ['Won', 'Closing'])) {
                    $lead->update(['pipeline_stage' => 'Maintaining']);
                }
            } else {
                Lead::where('customer_id', $requestOrder->customer_id)
                    ->whereIn('pipeline_stage', ['Won', 'Closing'])
                    ->update(['pipeline_stage' => 'Maintaining']);
            }
        });

        return back()->with('success', 'Penugasan disetujui & Delivery Order otomatis diterbitkan.');
    }

    // ─────────────────── APPROVAL DO (bandingkan Jual vs HPP) ───────────────────
    public function approveDo(Request $request, RequestOrder $requestOrder)
    {
        $request->validate([
            'action' => 'required|in:approve,unapprove',
            'note'   => 'nullable|string|max:1000',
        ]);

        $requestOrder->loadMissing('jobDetails', 'items');

        if ($request->action === 'approve') {
            $requestOrder->update(['do_approved' => true]);
            \App\Models\OrderStatusLog::record(
                $requestOrder, null, 'do_approved', auth()->id(),
                $request->note ?: 'DO disetujui. Jual ' . number_format($requestOrder->total_revenue) . ' / HPP ' . number_format($requestOrder->total_cost) . '.'
            );
            $msg = 'DO disetujui & siap diinvoice.';
        } else {
            $requestOrder->update(['do_approved' => false]);
            $msg = 'Approval DO dibatalkan.';
        }

        return back()->with('success', $msg);
    }

    public function destroy(RequestOrder $requestOrder)
    {
        $no = $requestOrder->do_number;
        $requestOrder->delete();
        return redirect()->route('request-orders.index')->with('success', 'Request DO ' . $no . ' berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $sos = RequestOrder::with(['customer', 'vendor', 'items'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->orderByDesc('order_date')->get();

        $headers = ['Request DO', 'Customer', 'Vendor', 'Flow', 'Delivery Type', 'Origin', 'Destination', 'Tracking', 'Service', 'Unit', 'Tonase', 'Qty', 'Buy Price', 'Sell Price', 'Subtotal Revenue', 'Subtotal HPP', 'Gross Profit', 'Currency', 'Status', 'Tgl Order', 'ETA'];

        $rows = [];
        foreach ($sos as $so) {
            foreach ($so->items as $item) {
                $rows[] = [
                    $so->do_number,
                    $so->customer?->company_name ?? '-',
                    $so->vendor?->vendor_name ?? '-',
                    $so->flow_label,
                    $so->delivery_type, $so->origin, $so->destination, $so->tracking_number,
                    $item->service_name, $item->unit,
                    $item->tonnage !== null ? (float) $item->tonnage : null,
                    (float) $item->qty, (float) $item->buy_price, (float) $item->sell_price,
                    (float) $item->subtotal_revenue, (float) $item->subtotal_cost, (float) $item->gross_profit,
                    $so->currency, $so->status,
                    $so->order_date?->format('Y-m-d'), $so->estimated_arrival?->format('Y-m-d'),
                ];
            }
        }

        return \App\Helpers\ExcelExport::download(
            'request-orders-' . $startDate . '-sd-' . $endDate, $headers, $rows, 'Request DO'
        );
    }

    private function rules(): array
    {
        return [
            'customer_id' => 'required|exists:customers,id',
            'vendor_id'   => 'nullable|exists:vendors,id',
            'lead_id'     => 'nullable|exists:leads,id',
            'user_id'     => 'required|exists:users,id',
            'currency'    => 'required|in:IDR,USD,SGD',
            'status'      => 'nullable|in:Done,In Progress,Cancelled',
            'order_date'  => 'required|date',
            'delivery_type'     => 'nullable|string|max:100',
            'origin'            => 'nullable|string|max:255',
            'destination'       => 'nullable|string|max:255',
            'tracking_number'   => 'nullable|string|max:100',
            'estimated_arrival' => 'nullable|date',
            'pickup_date'       => 'nullable|date',
            'notes'             => 'nullable|string',
        ];
    }

    /** Field operasional muatan (opsional) — dipakai bersama oleh store() & update(). */
    private function operationalFields(Request $request): array
    {
        $keys = [
            'checker', 'jenis_truck', 'no_pol', 'komoditi', 'depo', 'muat', 'tgl_muat',
            'bongkar', 'tgl_bongkar', 'tujuan', 'no_container', 'no_seal', 'grade', 'sektor',
            'supir', 'hp_supir', 'kota', 'empty_full', 'bongkar_empty_full',
            'kecamatan', 'kelurahan', 'keterangan',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $request->input($k);
        }
        return $out;
    }
}
