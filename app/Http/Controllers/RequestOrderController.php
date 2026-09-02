<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\RequestOrder;
use App\Models\DeliveryOrder;
use App\Models\OrderAssignment;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * REQUEST DO — tahap 1 alur fulfillment.
 *
 *   Sales (store, draft->verifikasi)
 *   -> Sales Admin (verify: approve->finance / reject)
 *   -> Finance (review harga & DP -> approval / kembali ke Sales Admin)
 *   -> Sales Manager/Admin (approve: terbitkan DO final / reject)
 */
class RequestOrderController extends Controller
{
    public function index(Request $request)
    {
        $search    = $request->get('search');
        $status    = $request->get('status');         // status legacy (Done/In Progress)
        $flow      = $request->get('flow');            // request_status
        $operationalStatus = $request->get('operational_status');
        $dpStatus  = $request->get('dp_status');
        $tab       = $request->get('tab', 'active');
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate   = $request->get('end_date', now()->endOfMonth()->format('Y-m-d'));

        $query = RequestOrder::with(['customer', 'lead', 'items', 'jobDetails', 'salesUser', 'verifier', 'operationalStatusChanger', 'dpReviewer'])
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($tab === 'cancelled') {
            $query->where('request_status', 'cancelled');
        } else {
            // Request yang sudah disetujui berpindah ke menu Delivery Orders.
            $query->whereNotIn('request_status', ['assigned', 'cancelled']);
        }

        if ($status && $status !== 'all') $query->where('status', $status);
        if ($flow && $flow !== 'all')     $query->where('request_status', $flow);
        if ($operationalStatus && $operationalStatus !== 'all') {
            $query->where('operational_status', $operationalStatus);
        }
        if ($dpStatus && $dpStatus !== 'all') $query->where('dp_status', $dpStatus);

        if ($search) {
            $query->where(fn($q) => $q
                ->where('do_number', 'like', "%$search%")
                ->orWhere('tracking_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($q) => $q->where('company_name', 'like', "%$search%"))
                ->orWhereHas('items', fn($q) => $q->where('service_name', 'like', "%$search%")));
        }

        $dos = $query->orderByDesc('order_date')->orderByDesc('id')->paginate(15)->withQueryString();
        $operationalStatusData = $dos->getCollection()->mapWithKeys(fn(RequestOrder $do) => [
            $do->id => [
                'id' => $do->id,
                'number' => $do->do_number,
                'status' => $do->operational_status ?? 'running',
                'note' => $do->operational_note,
                'rescheduled_for' => $do->rescheduled_for?->format('Y-m-d'),
            ],
        ])->all();

        // KPI pendapatan dari request yang sudah menjadi DO dan tidak dibatalkan.
        $allDone = RequestOrder::with(['items', 'jobDetails'])
            ->whereBetween('order_date', [$startDate, $endDate])
            ->where('request_status', 'assigned')
            ->where('operational_status', '!=', 'cancelled')
            ->where('currency', 'IDR')->get();

        $revenue     = $allDone->sum(fn($so) => $so->total_revenue);
        $totalCost   = $allDone->sum(fn($so) => $so->total_cost);
        $grossProfit = $revenue - $totalCost;
        // Volume DO selalu bersumber dari jumlah Delivery Order yang benar-benar dibuat.
        $volumeDo = DeliveryOrder::whereBetween('do_date', [$startDate, $endDate])->count();
        $issuedDoCount = RequestOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('request_status', 'assigned')->count();
        $cancelledRequestCount = RequestOrder::whereBetween('order_date', [$startDate, $endDate])
            ->where('request_status', 'cancelled')->count();
        $activeRequestCount = RequestOrder::whereBetween('order_date', [$startDate, $endDate])
            ->whereNotIn('request_status', ['assigned', 'cancelled'])->count();

        $dpRows = RequestOrder::whereBetween('order_date', [$startDate, $endDate])
            ->whereIn('dp_status', ['taken', 'not_taken'])
            ->get(['dp_status', 'dp_amount']);
        $dpTakenCount     = $dpRows->where('dp_status', 'taken')->count();
        $dpTakenAmount    = (float) $dpRows->where('dp_status', 'taken')->sum('dp_amount');
        $dpNotTakenCount  = $dpRows->where('dp_status', 'not_taken')->count();
        $dpNotTakenAmount = (float) $dpRows->where('dp_status', 'not_taken')->sum('dp_amount');

        $customers = Customer::where('status', 'Existing')
            ->orderBy('company_name')->get(['id', 'company_name', 'user_id']);
        $salesUsers = User::orderBy('name')->get(['id', 'name']);
        $leads = Lead::where(function ($q) {
            $q->whereIn('pipeline_stage', ['Won', 'Maintaining'])->orWhereNotNull('customer_id');
        })->orderBy('company_name')->get(['id', 'company_name', 'lead_code', 'customer_id']);

        $pendingDeletionDoIds = \App\Models\DeletionRequest::pendingIdsFor(RequestOrder::class);

        $flowOptions = RequestOrder::FLOW;
        $operationalStatusOptions = RequestOrder::OPERATIONAL_STATUSES;
        $dpStatusOptions = RequestOrder::DP_STATUSES;

        return view('request_orders.index', compact(
            'dos', 'revenue', 'grossProfit', 'volumeDo', 'totalCost',
            'customers', 'leads', 'salesUsers',
            'search', 'status', 'flow', 'flowOptions', 'operationalStatus', 'operationalStatusOptions',
            'dpStatus', 'dpStatusOptions', 'dpTakenCount', 'dpTakenAmount', 'dpNotTakenCount', 'dpNotTakenAmount',
            'operationalStatusData', 'startDate', 'endDate', 'pendingDeletionDoIds',
            'tab', 'issuedDoCount', 'cancelledRequestCount', 'activeRequestCount'
        ));
    }

    public function show(RequestOrder $requestOrder)
    {
        $requestOrder->load([
            'items', 'customer', 'vendor', 'lead', 'salesUser', 'verifier',
            'assignment.vendor', 'assignment.planner', 'assignment.approver',
            'statusLogs.user', 'deliveryOrder', 'operationalStatusChanger', 'dpReviewer',
            'jobDetails.vendor', 'jobDetails.pekerjaan', 'jobDetails.creator', 'jobDetails.updater',
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

        return redirect()->route('request-orders.index')->with('success', 'Request DO berhasil dibuat & masuk antrian verifikasi.');
    }

    public function edit(RequestOrder $requestOrder)
    {
        $requestOrder->load(['items', 'customer', 'lead']);
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

        $wasResubmitted = DB::transaction(function () use ($request, $requestOrder) {
            $requestOrder->update([
                'customer_id'    => $request->customer_id,
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

            return $requestOrder->resubmitManagerApproval(
                'Data utama Request DO diperbarui oleh ' . auth()->user()->name . ' dan diajukan ulang ke Sales Manager.',
                auth()->id()
            );
        });

        return redirect()->route('request-orders.index')->with(
            'success',
            $wasResubmitted
                ? 'Request DO berhasil diperbarui & diajukan ulang ke Sales Manager.'
                : 'Request DO berhasil diperbarui.'
        );
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
            $requestOrder->transition('finance', $request->note ?: 'Data Sales Admin terverifikasi.', auth()->id());
            User::where('role', 'Finance')->where('status', 'Active')->each(function (User $finance) use ($requestOrder) {
                Notification::send(
                    $finance->id,
                    'request_do_finance_review',
                    'Request DO menunggu review Finance',
                    $requestOrder->do_number . ' perlu diperiksa harga dan status DP.',
                    route('request-orders.show', $requestOrder)
                );
            });
            $msg = 'Verifikasi disetujui. Diteruskan ke Finance untuk review harga & DP.';
        } else {
            $requestOrder->update(['verify_note' => $request->note]);
            $requestOrder->transition('rejected', $request->note ?: 'Verifikasi ditolak.', auth()->id());
            $msg = 'Request DO ditolak pada tahap verifikasi.';
        }

        return back()->with('success', $msg);
    }

    // ─────────────────── REVIEW FINANCE & DP ───────────────────
    public function financeReview(Request $request, RequestOrder $requestOrder)
    {
        $validated = $request->validate([
            'action'    => 'required|in:approve,reject',
            'dp_status' => 'nullable|in:taken,not_taken',
            'dp_amount' => 'nullable|numeric|min:0',
            'dp_note'   => 'nullable|string|max:1000',
        ]);

        if (($validated['action'] ?? null) === 'approve' && $requestOrder->dp_request_active) {
            $dpErrors = [];
            if (empty($validated['dp_status'])) $dpErrors['dp_status'] = 'Status DP wajib dipilih.';
            if (!array_key_exists('dp_amount', $validated)) $dpErrors['dp_amount'] = 'Nominal DP wajib diisi.';
            if ($dpErrors !== []) return back()->withInput()->withErrors($dpErrors);
        }

        if (($validated['action'] ?? null) === 'approve'
            && ($validated['dp_status'] ?? null) === 'taken'
            && (float) ($validated['dp_amount'] ?? 0) <= 0) {
            return back()->withInput()->withErrors(['dp_amount' => 'Nominal DP terambil harus lebih dari 0.']);
        }

        if ($requestOrder->request_status !== 'finance') {
            return back()->withErrors(['general' => 'Request DO tidak berada di tahap review Finance.']);
        }

        if ($validated['action'] === 'reject') {
            $requestOrder->transition(
                'verifikasi',
                $validated['dp_note'] ?: 'Dikembalikan oleh Finance untuk diperbaiki Sales Admin.',
                auth()->id()
            );
            return back()->with('success', 'Request DO dikembalikan ke Sales Admin untuk diperbaiki.');
        }

        DB::transaction(function () use ($requestOrder, $validated) {
            $dpStatus = $requestOrder->dp_request_active ? $validated['dp_status'] : 'not_taken';
            $dpAmount = $requestOrder->dp_request_active ? ($validated['dp_amount'] ?? 0) : 0;
            $requestOrder->update([
                'dp_status'      => $dpStatus,
                'dp_amount'      => $dpAmount,
                'dp_note'        => $validated['dp_note'] ?? null,
                'dp_reviewed_by' => auth()->id(),
                'dp_reviewed_at' => now(),
            ]);
            $requestOrder->transition(
                'approval',
                'Finance selesai review. ' . ($requestOrder->dp_request_active
                    ? RequestOrder::DP_STATUSES[$dpStatus] . ' sebesar ' . number_format((float) $dpAmount, 0, ',', '.') . '.'
                    : 'Request DP nonaktif.'),
                auth()->id()
            );

            User::where('role', 'Sales Manager')->where('status', 'Active')->each(function (User $manager) use ($requestOrder) {
                Notification::send(
                    $manager->id,
                    'request_do_manager_approval',
                    'Request DO menunggu approval',
                    $requestOrder->do_number . ' telah direview Finance dan menunggu approval Sales Manager.',
                    route('request-orders.show', $requestOrder)
                );
            });
        });

        return back()->with('success', 'Review Finance & DP selesai. Diteruskan ke Sales Manager.');
    }

    /** Perbarui data DP setelah tahap review Finance tanpa mengubah flow Request DO. */
    public function updateDp(Request $request, RequestOrder $requestOrder)
    {
        if (!in_array($requestOrder->request_status, ['approval', 'assigned'], true)) {
            return back()->withErrors([
                'general' => $requestOrder->request_status === 'finance'
                    ? 'Gunakan form Review Finance & DP untuk meneruskan Request DO ke Sales Manager.'
                    : 'DP baru dapat diperbarui setelah Request DO melewati tahap review Finance.',
            ]);
        }

        $validated = $request->validate([
            'dp_status' => 'required|in:taken,not_taken',
            'dp_amount' => 'required|numeric|min:0',
            'dp_note'   => 'nullable|string|max:1000',
        ], [
            'dp_status.required' => 'Status DP wajib dipilih.',
            'dp_amount.required' => 'Nominal DP wajib diisi.',
        ]);

        if ($validated['dp_status'] === 'taken' && (float) $validated['dp_amount'] <= 0) {
            return back()->withInput()->withErrors([
                'dp_amount' => 'Nominal DP terambil harus lebih dari 0.',
            ]);
        }

        $wasResubmitted = DB::transaction(function () use ($requestOrder, $validated) {
            $requestOrder->update([
                'dp_status'      => $validated['dp_status'],
                'dp_amount'      => $validated['dp_amount'],
                'dp_note'        => $validated['dp_note'] ?? null,
                'dp_reviewed_by' => auth()->id(),
                'dp_reviewed_at' => now(),
            ]);

            return $requestOrder->resubmitManagerApproval(
                'Data DP diperbarui oleh ' . auth()->user()->name . ' dan diajukan ulang ke Sales Manager.',
                auth()->id()
            );
        });

        return back()->with(
            'success',
            $wasResubmitted
                ? 'Data DP berhasil diperbarui & diajukan ulang ke Sales Manager.'
                : 'Data DP berhasil diperbarui tanpa mengubah tahap flow Request DO.'
        );
    }

    /** Aktif/nonaktifkan kebutuhan DP tanpa menghapus histori review Finance. */
    public function updateDpActive(Request $request, RequestOrder $requestOrder)
    {
        $data = $request->validate([
            'active' => 'required|boolean',
            'note' => 'nullable|string|max:1000',
        ]);

        $active = (bool) $data['active'];
        $wasResubmitted = DB::transaction(function () use ($requestOrder, $data, $active) {
            $requestOrder->update([
                'dp_request_active' => $active,
                'dp_status' => $active ? ($requestOrder->dp_status ?: 'pending') : 'not_taken',
                'dp_amount' => $active ? $requestOrder->dp_amount : 0,
                'dp_note' => $data['note'] ?: ($active ? 'Request DP diaktifkan kembali.' : 'Request DP dinonaktifkan.'),
                'dp_reviewed_by' => auth()->id(),
                'dp_reviewed_at' => now(),
            ]);

            \App\Models\OrderStatusLog::record(
                $requestOrder,
                null,
                $active ? 'dp_activated' : 'dp_deactivated',
                auth()->id(),
                $data['note'] ?: ($active ? 'Request DP diaktifkan.' : 'Request DP dinonaktifkan.')
            );

            return $requestOrder->resubmitManagerApproval(
                'Pengaturan Request DP diperbarui oleh ' . auth()->user()->name . ' dan diajukan ulang ke Sales Manager.',
                auth()->id()
            );
        });

        $message = 'Request DP berhasil ' . ($active ? 'diaktifkan.' : 'dinonaktifkan.');
        if ($wasResubmitted) {
            $message .= ' Perubahan diajukan ulang ke Sales Manager.';
        }

        return back()->with('success', $message);
    }

    /** Batalkan request sebelum DO final diterbitkan. */
    public function cancel(Request $request, RequestOrder $requestOrder)
    {
        $data = $request->validate(['reason' => 'required|string|max:1000']);

        if ($requestOrder->request_status === 'assigned' || $requestOrder->deliveryOrder()->exists()) {
            return back()->withErrors(['general' => 'Request sudah menjadi Delivery Order dan tidak dapat dibatalkan dari menu Request DO.']);
        }
        if ($requestOrder->request_status === 'cancelled') {
            return back()->withErrors(['general' => 'Request DO sudah dibatalkan.']);
        }

        DB::transaction(function () use ($requestOrder, $data) {
            $requestOrder->update([
                'status' => 'Cancelled',
                'operational_status' => 'cancelled',
                'do_approved' => false,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancel_reason' => $data['reason'],
            ]);
            $requestOrder->transition('cancelled', $data['reason'], auth()->id());
        });

        return redirect()->route('request-orders.index', ['tab' => 'cancelled'])
            ->with('success', $requestOrder->do_number . ' berhasil dibatalkan.');
    }

    /** Aktifkan kembali request batal ke antrian verifikasi Sales Admin. */
    public function reactivate(Request $request, RequestOrder $requestOrder)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);
        if ($requestOrder->request_status !== 'cancelled') {
            return back()->withErrors(['general' => 'Hanya Request DO batal yang dapat diaktifkan kembali.']);
        }

        DB::transaction(function () use ($requestOrder, $data) {
            $requestOrder->update([
                'status' => 'In Progress',
                'operational_status' => 'running',
                'cancelled_by' => null,
                'cancelled_at' => null,
                'cancel_reason' => null,
            ]);
            $requestOrder->transition('verifikasi', $data['note'] ?: 'Request DO diaktifkan kembali.', auth()->id());
        });

        return redirect()->route('request-orders.index')->with('success', 'Request DO kembali ke antrian verifikasi.');
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

        if ($request->action === 'reject') {
            if ($assignment) {
                $assignment->update([
                    'approval_status' => 'rejected',
                    'approved_by'     => auth()->id(),
                    'approved_at'     => now(),
                    'approval_note'   => $request->note,
                ]);
            }
            $returnStage = $assignment ? 'dispatch' : 'finance';
            $requestOrder->transition($returnStage, $request->note ?: 'Approval Sales Manager ditolak untuk diperbaiki.', auth()->id());
            return back()->with('success', $assignment
                ? 'Penugasan ditolak. Dikembalikan ke Transport Planner.'
                : 'Request DO ditolak. Dikembalikan ke Finance.');
        }

        // APPROVE → terbitkan DO final otomatis
        DB::transaction(function () use ($requestOrder, $assignment, $request) {
            if ($assignment) {
                $assignment->update([
                    'approval_status' => 'approved',
                    'approved_by'     => auth()->id(),
                    'approved_at'     => now(),
                    'approval_note'   => $request->note,
                ]);
            }

            $requestOrder->transition('assigned', $request->note ?: 'Penugasan disetujui.', auth()->id());

            $do = DeliveryOrder::create([
                // Satu nomor dipakai dari Request DO sampai DO final agar mudah ditelusuri.
                'do_number'        => $requestOrder->do_number,
                'request_order_id' => $requestOrder->id,
                'customer_id'      => $requestOrder->customer_id,
                'vendor_id'        => $assignment?->vendor_id ?? $requestOrder->vendor_id,
                'user_id'          => $requestOrder->user_id,
                'status'           => 'surat_jalan',
                'assignment_type'  => $assignment?->assignment_type,
                'fleet_info'       => $assignment?->isExternal()
                                        ? ($assignment->vendor?->vendor_name ?? $assignment->fleet_info)
                                        : $assignment?->fleet_info,
                'driver_name'      => $assignment?->driver_name,
                'driver_phone'     => $assignment?->driver_phone,
                'origin'           => $requestOrder->origin,
                'destination'      => $requestOrder->destination,
                'do_date'          => now()->toDateString(),
                'pickup_date'      => $requestOrder->pickup_date,
                'actual_cost'      => $assignment?->estimated_cost ?? 0,
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

        $message = 'Request DO disetujui Sales Manager & Delivery Order otomatis diterbitkan.';
        if (!$requestOrder->fresh()->do_approved) {
            // DO tidak dapat ditutup selama harga belum disetujui, jadi diingatkan
            // sekarang — bukan nanti saat POD sudah terverifikasi.
            return back()
                ->with('success', $message)
                ->with('warning', 'Harga DO belum disetujui. Approve harga sebelum DO ditutup, karena penutupan DO akan ditolak selama harga belum disetujui.');
        }

        return back()->with('success', $message);
    }

    // ─────────────────── APPROVAL DO (bandingkan Jual vs HPP) ───────────────────
    public function approveDo(Request $request, RequestOrder $requestOrder)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject,unapprove',
            'note'   => 'required_if:action,reject|nullable|string|max:1000',
        ], [
            'note.required_if' => 'Alasan reject wajib diisi agar Finance mengetahui harga yang harus diperbaiki.',
        ]);

        $requestOrder->loadMissing('jobDetails', 'items');

        if ($validated['action'] === 'reject') {
            if ($requestOrder->request_status !== 'approval' || $requestOrder->deliveryOrder()->exists()) {
                return back()->withErrors([
                    'general' => 'Reject harga hanya dapat dilakukan saat Request DO menunggu approval dan DO final belum terbit.',
                ]);
            }

            DB::transaction(function () use ($requestOrder, $validated) {
                $requestOrder->update(['do_approved' => false]);
                $requestOrder->transition(
                    'finance',
                    'Harga DO ditolak oleh ' . auth()->user()->name . '. Alasan: ' . $validated['note'],
                    auth()->id()
                );

                User::where('role', 'Finance')->where('status', 'Active')->each(function (User $finance) use ($requestOrder, $validated) {
                    Notification::send(
                        $finance->id,
                        'request_do_price_rejected',
                        'Harga Request DO perlu diperbaiki',
                        $requestOrder->do_number . ' ditolak karena harga belum benar. Alasan: ' . $validated['note'],
                        route('request-orders.show', $requestOrder)
                    );
                });
            });

            return back()->with('success', 'DO ditolak karena harga tidak benar dan dikembalikan ke Finance.');
        }

        if ($validated['action'] === 'approve') {
            // Approve sekaligus menutup kembali kunci koreksi harga.
            $requestOrder->update(['do_approved' => true, 'price_correction_open' => false]);
            \App\Models\OrderStatusLog::record(
                $requestOrder, null, 'do_approved', auth()->id(),
                ($validated['note'] ?? null) ?: 'DO disetujui. Jual ' . number_format($requestOrder->total_revenue) . ' / HPP ' . number_format($requestOrder->total_cost) . '.'
            );
            $msg = 'DO disetujui & siap diinvoice.';
        } else {
            // Unapprove setelah DO final terbit membuka jalur koreksi harga untuk
            // Finance. Tanpa ini harga yang salah tidak dapat diperbaiki sama
            // sekali dan DO akan tertahan permanen saat hendak ditutup.
            $correctionOpen = $requestOrder->request_status === 'assigned'
                && $requestOrder->invoice_status === 'uninvoiced'
                && $requestOrder->deliveryOrder()->exists();

            $requestOrder->update([
                'do_approved' => false,
                'price_correction_open' => $correctionOpen,
            ]);
            $msg = 'Approval DO dibatalkan.';

            if ($correctionOpen) {
                \App\Models\OrderStatusLog::record(
                    $requestOrder, null, 'price_correction_open', auth()->id(),
                    ($validated['note'] ?? null) ?: 'Kunci koreksi harga dibuka oleh ' . auth()->user()->name . '. Finance dapat memperbaiki rincian harga.'
                );

                User::where('role', 'Finance')->where('status', 'Active')->each(function (User $finance) use ($requestOrder) {
                    Notification::send(
                        $finance->id,
                        'request_do_price_correction_open',
                        'Koreksi harga DO dibuka',
                        $requestOrder->do_number . ' dibuka untuk perbaikan harga. Perbaiki rincian lalu minta approve ulang.',
                        route('request-orders.show', $requestOrder)
                    );
                });

                $msg = 'Approval DO dibatalkan & kunci koreksi harga dibuka untuk Finance.';
            }
        }

        return back()->with('success', $msg);
    }

    /** Ubah status pelaksanaan tanpa mengubah tahap flow approval Request DO. */
    public function updateOperationalStatus(Request $request, RequestOrder $requestOrder)
    {
        $validated = $request->validate([
            'operational_status' => 'required|in:' . implode(',', array_keys(RequestOrder::OPERATIONAL_STATUSES)),
            'operational_note'   => 'required_unless:operational_status,running|nullable|string|max:1000',
            'rescheduled_for'    => 'required_if:operational_status,rescheduled|nullable|date',
        ], [
            'operational_note.required_unless' => 'Keterangan wajib diisi ketika DO tidak berjalan.',
            'rescheduled_for.required_if'       => 'Tanggal jadwal baru wajib diisi untuk status Reschedule.',
        ]);

        $from = $requestOrder->operational_status ?? 'running';
        $to   = $validated['operational_status'];
        $note = $validated['operational_note'] ?? null;

        DB::transaction(function () use ($requestOrder, $from, $to, $note, $validated) {
            $updates = [
                'operational_status'             => $to,
                'operational_note'               => $note,
                'rescheduled_for'                => $to === 'rescheduled' ? $validated['rescheduled_for'] : null,
                'operational_status_changed_by'  => auth()->id(),
                'operational_status_changed_at'  => now(),
            ];

            // Status lama dipertahankan untuk kompatibilitas laporan, tetapi Cancel
            // harus dikeluarkan dari perhitungan DO berjalan/selesai.
            if ($to === 'cancelled') {
                $updates['status'] = 'Cancelled';
                $updates['do_approved'] = false;
            } elseif ($requestOrder->status === 'Cancelled') {
                $updates['status'] = 'In Progress';
            }

            $requestOrder->update($updates);

            $logNote = $note ?: 'DO diaktifkan kembali dan dapat berjalan.';
            if ($to === 'rescheduled' && !empty($validated['rescheduled_for'])) {
                $logNote .= ' Jadwal baru: ' . date('d M Y', strtotime($validated['rescheduled_for'])) . '.';
            }

            \App\Models\OrderStatusLog::record(
                $requestOrder,
                'operational_' . $from,
                'operational_' . $to,
                auth()->id(),
                $logNote
            );
        });

        return back()->with('success', 'Status operasional ' . $requestOrder->do_number . ' diperbarui menjadi ' . RequestOrder::OPERATIONAL_STATUSES[$to] . '.');
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

        $query = RequestOrder::with(['customer', 'items'])
            ->whereBetween('order_date', [$startDate, $endDate]);

        if ($request->get('tab', 'active') === 'cancelled') {
            $query->where('request_status', 'cancelled');
        } else {
            $query->whereNotIn('request_status', ['assigned', 'cancelled']);
        }

        if ($request->filled('flow') && $request->get('flow') !== 'all') {
            $query->where('request_status', $request->get('flow'));
        }
        if ($request->filled('operational_status') && $request->get('operational_status') !== 'all') {
            $query->where('operational_status', $request->get('operational_status'));
        }
        if ($request->filled('dp_status') && $request->get('dp_status') !== 'all') {
            $query->where('dp_status', $request->get('dp_status'));
        }
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(fn($q) => $q
                ->where('do_number', 'like', "%$search%")
                ->orWhereHas('customer', fn($customer) => $customer->where('company_name', 'like', "%$search%"))
                ->orWhereHas('items', fn($item) => $item->where('service_name', 'like', "%$search%")));
        }

        $sos = $query->orderByDesc('order_date')->get();

        $headers = ['Request DO', 'Customer', 'Flow', 'Status Operasional', 'Keterangan Status', 'Alasan Batal', 'Jadwal Reschedule', 'Request DP', 'Status DP', 'Nominal DP', 'Catatan DP', 'Direview Finance', 'Delivery Type', 'Origin', 'Destination', 'Tracking', 'Service', 'Unit', 'Tonase', 'Qty', 'Buy Price', 'Sell Price', 'Subtotal Revenue', 'Subtotal HPP', 'Gross Profit', 'Currency', 'Status', 'Tgl Order', 'ETA'];

        $rows = [];
        foreach ($sos as $so) {
            // Request tanpa item layanan tetap diekspor sebagai satu baris.
            $items = $so->items->isNotEmpty() ? $so->items : [null];
            foreach ($items as $item) {
                $rows[] = [
                    $so->do_number,
                    $so->customer?->company_name ?? '-',
                    $so->flow_label,
                    $so->operational_status_label,
                    $so->operational_note,
                    $so->cancel_reason,
                    $so->rescheduled_for?->format('Y-m-d'),
                    $so->dp_request_active ? 'Aktif' : 'Nonaktif',
                    $so->dp_status_label,
                    (float) $so->dp_amount,
                    $so->dp_note,
                    $so->dp_reviewed_at?->format('Y-m-d H:i:s'),
                    $so->delivery_type, $so->origin, $so->destination, $so->tracking_number,
                    $item?->service_name, $item?->unit,
                    $item?->tonnage !== null ? (float) $item->tonnage : null,
                    $item ? (float) $item->qty : null,
                    $item ? (float) $item->buy_price : null,
                    $item ? (float) $item->sell_price : null,
                    $item ? (float) $item->subtotal_revenue : null,
                    $item ? (float) $item->subtotal_cost : null,
                    $item ? (float) $item->gross_profit : null,
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
            'alamat'            => 'nullable|string|max:1000',
        ];
    }

    /** Field operasional muatan (opsional) — dipakai bersama oleh store() & update(). */
    private function operationalFields(Request $request): array
    {
        $keys = [
            'checker', 'jenis_truck', 'no_pol', 'komoditi', 'depo', 'muat', 'tgl_muat',
            'bongkar', 'tgl_bongkar', 'tujuan', 'no_container', 'no_seal', 'grade', 'sektor',
            'supir', 'hp_supir', 'kota', 'alamat', 'keterangan',
        ];
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = $request->input($k);
        }
        return $out;
    }
}
