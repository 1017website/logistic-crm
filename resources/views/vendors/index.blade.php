@extends('layouts.app')
@section('title', 'Database Vendor')
@section('page-title', 'Database Vendor')
@section('page-subtitle', 'Kelola data vendor Internal dan External')

@section('content')
<div class="row g-3">
<div class="col-12">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addVendorModal">
                <i class="fas fa-plus me-1"></i> Tambah Vendor
            </button>
            <a href="{{ route('vendors.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i> Export Excel
            </a>
        </div>
        <div class="d-flex gap-3 flex-wrap">
            @foreach([[$totalVendor,'Total','#111'],[$externalVendor,'Local','#2563eb'],[$internalVendor,'Import','#7c3aed'],[$existingVendor,'Existing','#059669'],[$potentialVendor,'Potential','#f97316']] as $s)
            <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}" style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                <div style="font-size:1.2rem;font-weight:800;color:{{ $s[2] }}">{{ $s[0] }}</div>
                <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('vendors.index') }}">
        <div class="card mb-3"><div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="vendor_type" class="form-select form-select-sm">
                        <option value="all">All Type</option>
                        <option value="External"  @selected($vendorType=='External')>Local</option>
                        <option value="Internal" @selected($vendorType=='Internal')>Import</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="relationship_status" class="form-select form-select-sm">
                        <option value="all">All Relationship</option>
                        <option value="Existing"  @selected($relationshipStatus=='Existing')>Existing</option>
                        <option value="Potential" @selected($relationshipStatus=='Potential')>Potential</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="all">All Status</option>
                        <option value="Active"     @selected($status=='Active')>Active</option>
                        <option value="Non-Active" @selected($status=='Non-Active')>Non-Active</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari vendor, layanan..." value="{{ $search }}">
                </div>
                <div class="col-md-1">
                    <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i></button>
                </div>
            </div>
        </div></div>
    </form>

    {{-- Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px">
                    <thead style="background:#f8f9fa">
                        <tr>
                            <th class="px-3 py-2">Vendor</th>
                            <th class="py-2">PIC</th>
                            <th class="py-2">Phone</th>
                            <th class="py-2">Service Type</th>
                            <th class="py-2">Layanan Vendor</th>
                            <th class="py-2">Type</th>
                            <th class="py-2">Relationship</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Rating</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($vendors as $s)
                        <tr>
                            <td class="px-3 py-2">
                                <div style="font-weight:700">{{ $s->vendor_name }}</div>
                                @if($s->is_preferred)<span style="font-size:10px;color:#d97706">⭐ Preferred</span>@endif
                            </td>
                            <td class="py-2">
                                <div>{{ $s->pic_name }}</div>
                                <div style="font-size:11px;color:#6b7280">{{ $s->pic_position }}</div>
                            </td>
                            <td class="py-2" style="font-size:12px">{{ $s->phone }}</td>
                            <td class="py-2" style="font-size:12px">{{ $s->service_type ?? '-' }}</td>
                            <td class="py-2" style="font-size:12px;max-width:220px">
                                @php
                                    $serviceNames = $s->services->map(function ($p) {
                                        $name = trim($p->service_name ?? '');
                                        $unit = trim($p->unit ?? '');

                                        if ($name === '') {
                                            return null;
                                        }

                                        return $unit !== '' ? $name . ' (' . $unit . ')' : $name;
                                    })->filter()->values();
                                @endphp
                                @if($serviceNames->count() > 0)
                                    <div title="{{ $serviceNames->implode(', ') }}">{{ \Illuminate\Support\Str::limit($serviceNames->implode(', '), 70) }}</div>
                                @else
                                    <span style="color:#9ca3af">-</span>
                                @endif
                            </td>
                            <td class="py-2">
                                <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;
                                    background:{{ $s->vendor_type==='External'?'#dbeafe':'#ede9fe' }};
                                    color:{{ $s->vendor_type==='External'?'#1d4ed8':'#7c3aed' }}">
                                    {{ $s->vendor_type }}
                                    @if($s->vendor_type==='Internal' && $s->origin_country)
                                    <span style="font-size:10px">({{ $s->origin_country }})</span>
                                    @endif
                                </span>
                            </td>
                            <td class="py-2">
                                <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;
                                    background:{{ $s->relationship_status==='Existing'?'#d1fae5':'#fff7ed' }};
                                    color:{{ $s->relationship_status==='Existing'?'#059669':'#ea580c' }}">
                                    {{ $s->relationship_status }}
                                </span>
                            </td>
                            <td class="py-2">
                                <span class="{{ $s->status==='Active'?'badge-existing':'badge-overdue' }}">{{ $s->status }}</span>
                            </td>
                            <td class="py-2" style="font-size:12px">{{ $s->rating > 0 ? $s->rating : '-' }}</td>
                            <td class="py-2">
                                <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px"
                                    onclick="openEditVendor({{ $s->id }})">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info" style="padding:3px 7px" title="Layanan"
                                    onclick="openServiceModal({{ $s->id }}, '{{ addslashes($s->vendor_name) }}')">
                                    <i class="fas fa-boxes" style="font-size:.7rem"></i>
                                </button>
                                <form method="POST" action="{{ route('vendors.destroy', $s) }}" class="d-inline"
                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus vendor {{ addslashes($s->vendor_name) }}? Tindakan ini tidak dapat dibatalkan.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center py-4" style="color:#9ca3af">Belum ada data vendor</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($vendors->hasPages())
            <div class="px-3 py-2">{{ $vendors->links() }}</div>
            @endif
        </div>
    </div>
</div>
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="addVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Vendor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('vendors.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Vendor <span class="text-danger">*</span></label>
                            <input type="text" name="vendor_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vendor Type <span class="text-danger">*</span></label>
                            <select name="vendor_type" class="form-select">
                                <option value="External">External</option>
                                <option value="Internal">Internal</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Service Type</label>
                            <select name="service_type" class="form-select">
                                <option value="">- Pilih -</option>
                                @foreach(\App\Models\Vendor::SERVICE_TYPES as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Service Mode</label>
                            <div class="d-flex gap-3 pt-2">
                                @foreach(\App\Models\Vendor::SERVICE_MODES as $sm)
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="service_mode[]" value="{{ $sm }}" id="addSm{{ $loop->index }}">
                                    <label class="form-check-label" for="addSm{{ $loop->index }}">{{ $sm }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Payment Term</label>
                            <input type="text" name="payment_term" class="form-control" placeholder="Net 30, COD, dll">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PIC Name <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Posisi PIC</label>
                            <input type="text" name="pic_position" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone <span class="text-danger">*</span></label>
                            <input type="text" name="phone" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rating (0-5)</label>
                            <input type="number" name="rating" class="form-control" min="0" max="5" step="0.1" value="0">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Non-Active">Non-Active</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Relationship</label>
                            <select name="relationship_status" class="form-select">
                                <option value="Potential">Potential</option>
                                <option value="Existing">Existing</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_preferred" value="0">
                                <input type="checkbox" name="is_preferred" value="1" class="form-check-input" id="addPreferred">
                                <label class="form-check-label" for="addPreferred">Preferred Vendor</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Alamat</label>
                            <textarea name="address" class="form-control" rows="2"></textarea>
                        </div>

                        {{-- Inline PICs --}}
                        <div class="col-12 mt-2">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-users me-1"></i> PIC Perusahaan</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addSupPicRow('addSupPicsContainer')"><i class="fas fa-plus me-1"></i> Add PIC</button>
                            </div>
                            <div id="addSupPicsContainer"></div>
                        </div>

                        {{-- Inline Products --}}
                        <div class="col-12 mt-1">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-box me-1"></i> Layanan Vendor</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addVendorServiceRow('addSupProductsContainer')"><i class="fas fa-plus me-1"></i> Add Layanan</button>
                            </div>
                            <div id="addSupProductsContainer"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Edit --}}
<div class="modal fade" id="editVendorModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Vendor</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editVendorForm">
                @csrf @method('PUT')
                <input type="hidden" name="pics_submitted" value="1">
                <input type="hidden" name="services_submitted" value="1">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Vendor <span class="text-danger">*</span></label>
                            <input type="text" name="vendor_name" id="esName" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Vendor Type</label>
                            <select name="vendor_type" id="esVendorType" class="form-select">
                                <option value="External">External</option>
                                <option value="Internal">Internal</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Service Type</label>
                            <select name="service_type" id="esServiceType" class="form-select">
                                <option value="">- Pilih -</option>
                                @foreach(\App\Models\Vendor::SERVICE_TYPES as $st)
                                    <option value="{{ $st }}">{{ $st }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Service Mode</label>
                            <div class="d-flex gap-3">
                                @foreach(\App\Models\Vendor::SERVICE_MODES as $sm)
                                <div class="form-check">
                                    <input class="form-check-input es-service-mode" type="checkbox" name="service_mode[]" value="{{ $sm }}" id="esSm{{ $loop->index }}">
                                    <label class="form-check-label" for="esSm{{ $loop->index }}">{{ $sm }}</label>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PIC Name</label>
                            <input type="text" name="pic_name" id="esPic" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" id="esPhone" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" id="esEmail" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rating</label>
                            <input type="number" name="rating" id="esRating" class="form-control" min="0" max="5" step="0.1">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Status</label>
                            <select name="status" id="esStatus" class="form-select">
                                <option value="Active">Active</option>
                                <option value="Non-Active">Non-Active</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Relationship</label>
                            <select name="relationship_status" id="esRelationship" class="form-select">
                                <option value="Potential">Potential</option>
                                <option value="Existing">Existing</option>
                            </select>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <div class="form-check">
                                <input type="hidden" name="is_preferred" value="0">
                                <input type="checkbox" name="is_preferred" value="1" class="form-check-input" id="esPreferred">
                                <label class="form-check-label" for="esPreferred">Preferred Vendor</label>
                            </div>
                        </div>
                    </div>

                    {{-- Inline PICs (edit) --}}
                    <div class="mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-users me-1"></i> PIC Perusahaan</div>
                            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addSupPicRow('editSupPicsContainer')"><i class="fas fa-plus me-1"></i> Add PIC</button>
                        </div>
                        <div id="editSupPicsContainer"></div>
                        <div id="editSupPicsExisting" class="mt-2"></div>
                    </div>

                    {{-- Inline Products (edit) --}}
                    <div class="mt-2">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-box me-1"></i> Layanan Vendor</div>
                            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addVendorServiceRow('editVendorServicesContainer')"><i class="fas fa-plus me-1"></i> Add Layanan</button>
                        </div>
                        <div id="editSupProductsExisting" class="mt-1 mb-2"></div>
                        <div id="editVendorServicesContainer"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Layanan Vendor --}}
<div class="modal fade" id="vendorServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Layanan Vendor — <span id="spModalName"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- List layanan existing --}}
                <div id="spProductList" class="mb-3"></div>
                {{-- Form tambah layanan --}}
                <div style="background:#f9fafb;border-radius:8px;padding:12px">
                    <div style="font-size:.78rem;font-weight:600;margin-bottom:8px">Tambah Layanan</div>
                    <form id="addVendorServiceForm" method="POST">
                        @csrf
                        <div class="row g-2">
                            <div class="col-5">
                                <input type="text" name="service_name" class="form-control form-control-sm" placeholder="Nama layanan *" required>
                            </div>
                            <div class="col-3">
                                <select name="unit" class="form-select form-select-sm">
                                    <option value="per shipment">per shipment</option>
                                    <option value="per trip">per trip</option>
                                    <option value="per container">per container</option>
                                    <option value="FCL">FCL</option>
                                    <option value="LCL">LCL</option>
                                    <option value="custom">custom</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                    <i class="fas fa-plus me-1"></i> Tambah
                                </button>
                            </div>
                            <div class="col-12">
                                <input type="text" name="description" class="form-control form-control-sm" placeholder="Keterangan (opsional)">
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


@php
    $vendorEditData = $vendors->mapWithKeys(function ($s) {
        return [$s->id => [
            'id' => $s->id,
            'vendor_name' => $s->vendor_name,
            'vendor_type' => $s->vendor_type,
            'pic_name' => $s->pic_name,
            'phone' => $s->phone,
            'email' => $s->email,
            'service_type' => $s->service_type,
            'service_mode' => $s->service_mode,
            'status' => $s->status,
            'relationship_status' => $s->relationship_status,
            'is_preferred' => (bool) $s->is_preferred,
            'rating' => $s->rating,
            'pics' => $s->pics->map(function ($pic) {
                return [
                    'pic_name' => $pic->pic_name,
                    'pic_position' => $pic->pic_position,
                    'phone' => $pic->phone,
                    'email' => $pic->email,
                ];
            })->values(),
            'services' => $s->services->map(function ($service) {
                return [
                    'service_name'      => $service->service_name,
                    'unit'              => $service->unit,
                    'tariff'            => $service->tariff,
                    'tariff_unit'       => $service->tariff_unit,
                    'route_origin'      => $service->route_origin,
                    'route_destination' => $service->route_destination,
                    'description'       => $service->description,
                ];
            })->values(),
        ]];
    });
@endphp

@push('scripts')
<script>
const vendorEditData = @json($vendorEditData);

function openEditVendor(id) {
    const data = vendorEditData[id];
    if (!data) return;

    document.getElementById('editVendorForm').action = `/vendors/${id}`;
    document.getElementById('esName').value          = data.vendor_name || '';
    document.getElementById('esVendorType').value    = data.vendor_type || 'External';
    document.getElementById('esServiceType').value   = data.service_type || '';
    document.getElementById('esPic').value           = data.pic_name || '';
    document.getElementById('esPhone').value         = data.phone || '';
    document.getElementById('esEmail').value         = data.email || '';
    document.getElementById('esStatus').value        = data.status || 'Active';
    document.getElementById('esRelationship').value  = data.relationship_status || 'Potential';
    document.getElementById('esPreferred').checked   = !!data.is_preferred;
    document.getElementById('esRating').value        = data.rating || 0;

    // Service mode checkboxes
    const selectedModes = (data.service_mode || '').split(',').map(s => s.trim()).filter(Boolean);
    document.querySelectorAll('.es-service-mode').forEach(cb => {
        cb.checked = selectedModes.includes(cb.value);
    });

    const editSupPicsExisting = document.getElementById('editSupPicsExisting');
    const editSupProductsExisting = document.getElementById('editSupProductsExisting');
    const editSupPicsContainer = document.getElementById('editSupPicsContainer');
    const editVendorServicesContainer = document.getElementById('editVendorServicesContainer');

    editSupPicsExisting.innerHTML = '';
    editSupProductsExisting.innerHTML = '';
    editSupPicsContainer.innerHTML = '';
    editVendorServicesContainer.innerHTML = '';

    (data.pics || []).forEach(function(pic) {
        addSupPicRow('editSupPicsContainer', pic);
    });

    (data.services || []).forEach(function(service) {
        addVendorServiceRow('editVendorServicesContainer', service);
    });

    if ((data.pics || []).length === 0) {
        editSupPicsExisting.innerHTML = '<div style="font-size:.75rem;color:#9ca3af"><i>Belum ada PIC tambahan.</i></div>';
    }

    if ((data.services || []).length === 0) {
        editSupProductsExisting.innerHTML = '<div style="font-size:.75rem;color:#9ca3af"><i>Belum ada layanan vendor.</i></div>';
    }

    new bootstrap.Modal(document.getElementById('editVendorModal')).show();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// ── Inline Vendor PIC rows ──
let supPicIdx = 0;
function addSupPicRow(containerId, data = {}) {
    const i = supPicIdx++;
    const html = `<div class="row g-2 mb-2 align-items-center" id="supPic_${i}">
        <div class="col-4"><input type="text" name="pics[${i}][pic_name]" class="form-control form-control-sm" placeholder="Nama PIC *" value="${escapeHtml(data.pic_name)}" required></div>
        <div class="col-3"><input type="text" name="pics[${i}][pic_position]" class="form-control form-control-sm" placeholder="Jabatan" value="${escapeHtml(data.pic_position)}"></div>
        <div class="col-2"><input type="text" name="pics[${i}][phone]" class="form-control form-control-sm" placeholder="Phone" value="${escapeHtml(data.phone)}"></div>
        <div class="col-2"><input type="email" name="pics[${i}][email]" class="form-control form-control-sm" placeholder="Email" value="${escapeHtml(data.email)}"></div>
        <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('supPic_${i}').remove()"><i class="fas fa-times"></i></button></div>
    </div>`;
    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
}

// ── Inline Vendor Product rows ──
let supProdIdx = 0;
function addVendorServiceRow(containerId, data = {}) {
    const i = supProdIdx++;
    const html = `<div class="row g-2 mb-2 align-items-center" id="supProd_${i}">
        <div class="col-5"><input type="text" name="services[${i}][service_name]" class="form-control form-control-sm" placeholder="Nama Layanan *" value="${escapeHtml(data.service_name)}" required></div>
        <div class="col-3"><input type="text" name="services[${i}][unit]" class="form-control form-control-sm" placeholder="Basis layanan (per trip, per shipment, FCL/LCL...)" value="${escapeHtml(data.unit)}"></div>
        <div class="col-3"><input type="text" name="services[${i}][description]" class="form-control form-control-sm" placeholder="Keterangan" value="${escapeHtml(data.description)}"></div>
        <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('supProd_${i}').remove()"><i class="fas fa-times"></i></button></div>
    </div>`;
    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
}

// Vendor Products (AJAX via form submit → reload)
const vendorPics = @json($vendors->pluck('pics', 'id'));
const vendorServices = @json($vendors->pluck('services', 'id'));

function openServiceModal(vendorId, vendorName) {
    document.getElementById('spModalName').textContent = vendorName;
    document.getElementById('addVendorServiceForm').action = `/vendors/${vendorId}/products`;

    // Render existing services
    const services = vendorServices[vendorId] || [];
    const list = document.getElementById('spProductList');
    if (services.length === 0) {
        list.innerHTML = '<div style="font-size:.8rem;color:#9ca3af">Belum ada layanan.</div>';
    } else {
        list.innerHTML = services.map(p => `
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2" style="border-bottom:1px solid #f3f4f6">
                <div>
                    <div style="font-size:.82rem;font-weight:600">${p.service_name}</div>
                    <div style="font-size:.72rem;color:#6b7280">${p.unit}${p.description ? ' · ' + p.description : ''}</div>
                </div>
                <form method="POST" action="/vendors/${vendorId}/products/${p.id}" onsubmit="return confirm('Hapus layanan ${p.service_name}?')" style="display:inline">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" style="color:#ef4444;background:none;border:none;cursor:pointer"><i class="fas fa-times"></i></button>
                </form>
            </div>
        `).join('');
    }

    new bootstrap.Modal(document.getElementById('vendorServiceModal')).show();
}
</script>
@endpush
@endsection
