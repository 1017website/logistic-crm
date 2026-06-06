@extends('layouts.app')
@section('title', 'Master Service Type')
@section('page-title', 'Master Service Type')
@section('page-subtitle', 'Kelola daftar jenis layanan (service type) untuk vendor')

@section('content')

{{-- KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['bg'=>'#f2f2f2','icon'=>'fas fa-tags','color'=>'#111111','label'=>'Total Service Type','value'=>$serviceTypes->total()],
        ['bg'=>'#f0fdf4','icon'=>'fas fa-check-circle','color'=>'#10b981','label'=>'Aktif','value'=>$totalActive],
        ['bg'=>'#fef2f2','icon'=>'fas fa-ban','color'=>'#dc2626','label'=>'Non-Aktif','value'=>$totalInactive],
    ] as $k)
    <div class="col-md-4">
        <div style="background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:16px 20px;display:flex;align-items:center;gap:12px">
            <div style="width:44px;height:44px;border-radius:50%;background:{{ $k['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="{{ $k['icon'] }}" style="color:{{ $k['color'] }}"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#6b7280">{{ $k['label'] }}</div>
                <div style="font-size:22px;font-weight:700;color:#111827">{{ $k['value'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

@if($errors->any())
<div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body p-3 pb-0">
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <form method="GET" action="{{ route('service-types.index') }}" class="d-flex gap-2 align-items-center">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari service type..." value="{{ $search }}" style="width:220px">
                <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                @if($search)<a href="{{ route('service-types.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>@endif
            </form>
            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceTypeModal">
                <i class="fas fa-plus me-1"></i> Tambah Service Type
            </button>
        </div>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
        <table class="table crm-table mb-0">
            <thead>
                <tr>
                    <th style="width:60px">No</th>
                    <th>Nama Service Type</th>
                    <th style="width:120px;text-align:center">Urutan</th>
                    <th style="width:120px;text-align:center">Status</th>
                    <th style="width:130px;text-align:center">Dipakai</th>
                    <th style="width:120px;text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($serviceTypes as $i => $st)
                <tr>
                    <td style="color:#9ca3af">{{ $serviceTypes->firstItem() + $i }}</td>
                    <td style="font-weight:600;color:#111827">{{ $st->name }}</td>
                    <td style="text-align:center;color:#6b7280">{{ $st->sort_order }}</td>
                    <td style="text-align:center">
                        @if($st->is_active)
                        <span style="font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;background:#d1fae5;color:#059669">Aktif</span>
                        @else
                        <span style="font-size:11px;font-weight:600;padding:2px 10px;border-radius:20px;background:#fee2e2;color:#dc2626">Non-Aktif</span>
                        @endif
                    </td>
                    <td style="text-align:center;color:#6b7280">{{ $usage[$st->name] ?? 0 }} vendor</td>
                    <td style="text-align:center;white-space:nowrap">
                        <button class="btn btn-sm btn-outline-secondary"
                            onclick='openEditServiceType(@json($st))' title="Edit">
                            <i class="fas fa-pen"></i>
                        </button>
                        @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('service-types.destroy', $st) }}" class="d-inline"
                            onsubmit="return confirm('Hapus service type &quot;{{ $st->name }}&quot;?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-4" style="color:#9ca3af">
                    <i class="fas fa-tags" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>
                    Belum ada service type.
                </td></tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

    @if($serviceTypes->hasPages())
    <div class="card-footer p-3">{{ $serviceTypes->links() }}</div>
    @endif
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="addServiceTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('service-types.store') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Tambah Service Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Service Type <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" required placeholder="cth: Trucking trailer">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="sort_order" class="form-control" min="0" placeholder="auto">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="addStActive" value="1" checked>
                                <label class="form-check-label" for="addStActive">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit --}}
<div class="modal fade" id="editServiceTypeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="editServiceTypeForm">
                @csrf @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title">Edit Service Type</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nama Service Type <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="editStName" class="form-control" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Urutan</label>
                            <input type="number" name="sort_order" id="editStSort" class="form-control" min="0">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check">
                                <input type="checkbox" name="is_active" class="form-check-input" id="editStActive" value="1">
                                <label class="form-check-label" for="editStActive">Aktif</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openEditServiceType(st) {
    document.getElementById('editServiceTypeForm').action = '/service-types/' + st.id;
    document.getElementById('editStName').value = st.name;
    document.getElementById('editStSort').value = st.sort_order;
    document.getElementById('editStActive').checked = !!st.is_active;
    new bootstrap.Modal(document.getElementById('editServiceTypeModal')).show();
}
</script>
@endpush

@endsection
