<?php

namespace App\Http\Controllers;

use App\Models\DeletionRequest;
use App\Models\Notification;
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
            $model->delete();
            return back()->with('success', 'Data "' . $label . '" berhasil dihapus.');
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
                $model->delete(); // soft delete
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
