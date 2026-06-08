@extends('layouts.app')
@section('title', 'Permintaan Hapus')
@section('page-title', 'Permintaan Hapus')
@section('page-subtitle', 'Tinjau & setujui permintaan penghapusan data dari tim')

@section('content')

{{-- Filter status --}}
<div class="d-flex gap-2 mb-3 flex-wrap">
    @foreach(['pending'=>'Menunggu','approved'=>'Disetujui','rejected'=>'Ditolak'] as $val => $label)
        <a href="{{ route('deletion-requests.index', ['status' => $val]) }}"
           class="btn btn-sm {{ $status === $val ? 'btn-primary' : 'btn-outline-secondary' }}">
            {{ $label }}
            <span class="badge bg-light text-dark ms-1">{{ $counts[$val] ?? 0 }}</span>
        </a>
    @endforeach
</div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<div style="background:#fff;border-radius:12px;border:1px solid #f0f0f0;overflow:hidden">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:.82rem">
            <thead style="background:#fafafa">
                <tr>
                    <th class="py-2 ps-3">Modul</th>
                    <th class="py-2">Data</th>
                    <th class="py-2">Diminta Oleh</th>
                    <th class="py-2">Alasan</th>
                    <th class="py-2">Waktu</th>
                    <th class="py-2">Status</th>
                    <th class="py-2 text-end pe-3">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($requests as $dr)
                    <tr>
                        <td class="ps-3"><span class="badge bg-secondary">{{ $dr->module_title }}</span></td>
                        <td class="fw-semibold">{{ $dr->model_label ?? '#' . $dr->model_id }}</td>
                        <td>{{ $dr->requester?->name ?? '-' }}</td>
                        <td style="max-width:220px">
                            <span class="text-muted">{{ $dr->reason ?: '—' }}</span>
                        </td>
                        <td><span class="text-muted">{{ $dr->created_at->format('d M Y H:i') }}</span></td>
                        <td>
                            @if($dr->isPending())
                                <span class="badge bg-warning text-dark">Menunggu</span>
                            @elseif($dr->isApproved())
                                <span class="badge bg-success">Disetujui</span>
                            @else
                                <span class="badge bg-danger">Ditolak</span>
                            @endif
                            @if(!$dr->isPending() && $dr->reviewer)
                                <div style="font-size:.7rem;color:var(--text-muted)">oleh {{ $dr->reviewer->name }}</div>
                            @endif
                        </td>
                        <td class="text-end pe-3">
                            @if($dr->isPending())
                                <div class="d-flex gap-1 justify-content-end">
                                    <form method="POST" action="{{ route('deletion-requests.approve', $dr) }}"
                                          onsubmit="return confirm('Setujui & hapus data ini?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success" style="padding:3px 9px" title="Setujui">
                                            <i class="fas fa-check" style="font-size:.7rem"></i>
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('deletion-requests.reject', $dr) }}"
                                          onsubmit="return confirm('Tolak permintaan ini?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 9px" title="Tolak">
                                            <i class="fas fa-times" style="font-size:.7rem"></i>
                                        </button>
                                    </form>
                                </div>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada permintaan {{ $status }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $requests->links() }}</div>

@endsection
