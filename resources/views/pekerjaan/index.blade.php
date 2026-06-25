@extends('layouts.app')
@section('title', 'Master Pekerjaan')
@section('page-title', 'Master Pekerjaan')
@section('page-subtitle', 'Jenis pekerjaan logistik & kode (TR/NTR)')

@section('content')
@php $u = auth()->user(); @endphp
<div class="row g-3"><div class="col-12">
    @if(session('success'))<div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>@endif
    @foreach($errors->all() as $e)<div class="alert alert-danger py-2" style="font-size:13px">{{ $e }}</div>@endforeach

    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex gap-2">
            <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari pekerjaan..." value="{{ $search }}" style="width:240px">
            <button class="btn btn-primary btn-sm"><i class="fas fa-search"></i></button>
        </form>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPekerjaanModal"><i class="fas fa-plus me-1"></i> Tambah</button>
    </div>

    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:13px">
            <thead style="background:#f8f9fa"><tr>
                <th class="px-3 py-2">Nama Pekerjaan</th><th class="py-2">Kode</th><th class="py-2">Tipe</th><th class="py-2">Status</th><th class="py-2"></th>
            </tr></thead>
            <tbody>
                @forelse($pekerjaan as $p)
                <tr>
                    <td class="px-3 py-2" style="font-weight:600">{{ $p->name }}</td>
                    <td class="py-2">{{ $p->code ?? '-' }}</td>
                    <td class="py-2">{{ $p->type ? (\App\Models\Pekerjaan::TYPES[$p->type] ?? $p->type) : '-' }}</td>
                    <td class="py-2"><span class="badge bg-{{ $p->is_active ? 'success' : 'secondary' }}">{{ $p->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td class="py-2 text-nowrap">
                        <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px" onclick='openEditPekerjaan(@json($p))'><i class="fas fa-pencil-alt"></i></button>
                        @if($u->isAdmin())
                        <form method="POST" action="{{ route('pekerjaan.destroy',$p->id) }}" class="d-inline" onsubmit="return confirm('Hapus pekerjaan ini?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger" style="padding:3px 7px"><i class="fas fa-trash"></i></button></form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada data pekerjaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
    <div class="mt-3">{{ $pekerjaan->links() }}</div>
</div></div>

<div class="modal fade" id="addPekerjaanModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" id="pekerjaanForm" action="{{ route('pekerjaan.store') }}">@csrf
        <input type="hidden" name="_method" id="pekerjaanMethod" value="POST">
        <div class="modal-header"><h6 class="modal-title" id="pekerjaanTitle">Tambah Pekerjaan</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:13px">
            <div class="mb-2"><label class="form-label">Nama Pekerjaan</label><input type="text" name="name" id="pkName" class="form-control form-control-sm" required></div>
            <div class="row g-2 mb-2">
                <div class="col-6"><label class="form-label">Kode</label><input type="text" name="code" id="pkCode" class="form-control form-control-sm" placeholder="TR"></div>
                <div class="col-6"><label class="form-label">Tipe</label>
                    <select name="type" id="pkType" class="form-select form-select-sm">
                        <option value="">—</option><option value="TR">Trucking</option><option value="NTR">Non-Trucking</option>
                    </select></div>
            </div>
            <div class="form-check"><input type="checkbox" name="is_active" id="pkActive" class="form-check-input" value="1" checked><label class="form-check-label" for="pkActive">Aktif</label></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-primary">Simpan</button></div>
    </form>
</div></div></div>

<script>
const pekerjaanStore = "{{ route('pekerjaan.store') }}";
function openEditPekerjaan(p){
    const f = document.getElementById('pekerjaanForm');
    f.action = `/pekerjaan/${p.id}`;
    document.getElementById('pekerjaanMethod').value = 'PUT';
    document.getElementById('pekerjaanTitle').textContent = 'Edit Pekerjaan';
    document.getElementById('pkName').value = p.name || '';
    document.getElementById('pkCode').value = p.code || '';
    document.getElementById('pkType').value = p.type || '';
    document.getElementById('pkActive').checked = !!p.is_active;
    new bootstrap.Modal(document.getElementById('addPekerjaanModal')).show();
}
document.getElementById('addPekerjaanModal')?.addEventListener('hidden.bs.modal', function(){
    const f = document.getElementById('pekerjaanForm'); f.reset(); f.action = pekerjaanStore;
    document.getElementById('pekerjaanMethod').value = 'POST';
    document.getElementById('pekerjaanTitle').textContent = 'Tambah Pekerjaan';
    document.getElementById('pkActive').checked = true;
});
</script>
@endsection
