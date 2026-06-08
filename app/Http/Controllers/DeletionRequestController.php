<?php

namespace App\Http\Controllers;

use App\Models\DeletionRequest;
use App\Models\Notification;
use App\Models\Lead;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeletionRequestController extends Controller
{
    /** Map module slug => model class (validasi input) */
    private function resolveModelClass(string $module): ?string
    {
        foreach (DeletionRequest::MODULES as $class => $cfg) {
            if (($cfg['module'] ?? null) === $module) {
                return $class;
            }
        }
        return null;
    }

    /**
     * Hapus model. Jika Lead/Customer saling terhubung, hapus pasangannya juga.
     * Soft delete; child (PIC/layanan) ikut terhapus via sync observer.
     */
    public static function cascadeDelete($model): void
    {
        DB::transaction(function () use ($model) {
            if ($model instanceof Lead) {
                $customer = $model->customer_id ? Customer::find($model->customer_id) : null;
                if (!$customer) {
                    $customer = Customer::whereRaw('LOWER(TRIM(company_name)) = ?', [strtolower(trim((string) $model->company_name))])->first();
                }
                $model->delete();
                // Hapus semua lead lain yang menunjuk customer ini juga (mis. duplikat).
                if ($customer) {
                    Lead::where('customer_id', $customer->id)->where('id', '!=', $model->id)->get()->each(fn($l) => $l->delete());
                    $customer->delete();
                }
            } elseif ($model instanceof Customer) {
                // Hapus semua lead milik customer ini, lalu customer.
                Lead::where('customer_id', $model->id)->get()->each(fn($l) => $l->delete());
                Lead::whereRaw('LOWER(TRIM(company_name)) = ?', [strtolower(trim((string) $model->company_name))])
                    ->get()->each(fn($l) => $l->delete());
                $model->delete();
            } else {
                // Modul lain (vendor, delivery order): hapus apa adanya.
                $model->delete();
            }
        });
    }

    /**
     * Semua role: ajukan permintaan hapus.
     * Admin yang menekan "hapus" pun lewat sini, tapi langsung di-approve.
     */
    public function store(Request $request)
    {
        $request->validate([
            'module'   => 'required|string',
            'model_id' => 'required|integer',
            'reason'   => 'nullable|string|max:1000',
        ]);

        $modelClass = $this->resolveModelClass($request->module);
        if (!$modelClass) {
            return back()->withErrors(['delete' => 'Modul tidak dikenal.']);
        }

        $model = $modelClass::find($request->model_id);
        if (!$model) {
            return back()->withErrors(['delete' => 'Data tidak ditemukan.']);
        }

        // Admin: langsung hapus tanpa antrian persetujuan.
        if (auth()->user()->isAdmin()) {
            $label = $model->{(DeletionRequest::MODULES[$modelClass]['label_field'] ?? 'id')} ?? $model->id;
            self::cascadeDelete($model);
            return back()->with('success', 'Data "' . $label . '" berhasil dihapus (termasuk data terkait Lead/Customer).');
        }

        $dr = DeletionRequest::request($model, auth()->id(), $request->reason);

        // Notifikasi ke admin
        Notification::broadcast(
            'delete_request',
            'Permintaan Hapus: ' . ($dr->module_title),
            auth()->user()->name . ' meminta hapus "' . ($dr->model_label ?? '#' . $dr->model_id) . '"',
            route('deletion-requests.index')
        );

        return back()->with('success', 'Permintaan hapus dikirim ke administrator untuk disetujui.');
    }

    /** Admin: daftar permintaan hapus */
    public function index(Request $request)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $status = $request->get('status', 'pending');

        $query = DeletionRequest::with(['requester', 'reviewer']);
        if (in_array($status, ['pending', 'approved', 'rejected'], true)) {
            $query->where('status', $status);
        }

        $requests = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        $counts = [
            'pending'  => DeletionRequest::where('status', 'pending')->count(),
            'approved' => DeletionRequest::where('status', 'approved')->count(),
            'rejected' => DeletionRequest::where('status', 'rejected')->count(),
        ];

        return view('deletion_requests.index', compact('requests', 'status', 'counts'));
    }

    /** Admin: setujui — soft delete target lalu tandai approved */
    public function approve(Request $request, DeletionRequest $deletionRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if (!$deletionRequest->isPending()) {
            return back()->withErrors(['delete' => 'Permintaan sudah diproses.']);
        }

        DB::transaction(function () use ($deletionRequest, $request) {
            $modelClass = $deletionRequest->model_type;
            $model = $modelClass::find($deletionRequest->model_id);
            if ($model) {
                self::cascadeDelete($model); // soft delete + pasangan lead/customer
            }

            $deletionRequest->update([
                'status'      => 'approved',
                'reviewed_by' => auth()->id(),
                'reviewed_at' => now(),
                'review_note' => $request->get('review_note'),
            ]);

            Notification::send(
                $deletionRequest->requested_by,
                'delete_request',
                'Permintaan Hapus Disetujui',
                'Permintaan hapus "' . ($deletionRequest->model_label ?? '#' . $deletionRequest->model_id) . '" telah disetujui.',
                route('deletion-requests.index')
            );
        });

        return back()->with('success', 'Permintaan hapus disetujui dan data telah dihapus.');
    }

    /** Admin: tolak — target tetap aktif */
    public function reject(Request $request, DeletionRequest $deletionRequest)
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        if (!$deletionRequest->isPending()) {
            return back()->withErrors(['delete' => 'Permintaan sudah diproses.']);
        }

        $deletionRequest->update([
            'status'      => 'rejected',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $request->get('review_note'),
        ]);

        Notification::send(
            $deletionRequest->requested_by,
            'delete_request',
            'Permintaan Hapus Ditolak',
            'Permintaan hapus "' . ($deletionRequest->model_label ?? '#' . $deletionRequest->model_id) . '" ditolak administrator.',
            route('deletion-requests.index')
        );

        return back()->with('success', 'Permintaan hapus ditolak.');
    }
}
