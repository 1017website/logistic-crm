@extends('layouts.app')
@section('title', 'Database Supplier')
@section('page-title', 'Database Supplier')
@section('page-subtitle', 'Kelola data supplier Local dan Import')

@section('content')
<div class="row g-3">
<div class="col-12">

    {{-- Header --}}
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="fas fa-plus me-1"></i> Tambah Supplier
            </button>
            <a href="{{ route('suppliers.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i> Export Excel
            </a>
        </div>
        <div class="d-flex gap-3 flex-wrap">
            @foreach([[$totalSupplier,'Total','#111'],[$localSupplier,'Local','#2563eb'],[$importSupplier,'Import','#7c3aed'],[$existingSupplier,'Existing','#059669'],[$potentialSupplier,'Potential','#f97316']] as $s)
            <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}" style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                <div style="font-size:1.2rem;font-weight:800;color:{{ $s[2] }}">{{ $s[0] }}</div>
                <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('suppliers.index') }}">
        <div class="card mb-3"><div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <select name="source_type" class="form-select form-select-sm">
                        <option value="all">All Source</option>
                        <option value="Local"  @selected($sourceType=='Local')>Local</option>
                        <option value="Import" @selected($sourceType=='Import')>Import</option>
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
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari supplier, produk..." value="{{ $search }}">
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
                            <th class="px-3 py-2">Supplier</th>
                            <th class="py-2">PIC</th>
                            <th class="py-2">Phone</th>
                            <th class="py-2">Kategori Produk</th>
                            <th class="py-2">Produk Supplier</th>
                            <th class="py-2">Source</th>
                            <th class="py-2">Relationship</th>
                            <th class="py-2">Status</th>
                            <th class="py-2">Rating</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($suppliers as $s)
                        <tr>
                            <td class="px-3 py-2">
                                <div style="font-weight:700">{{ $s->supplier_name }}</div>
                                @if($s->is_preferred)<span style="font-size:10px;color:#d97706">⭐ Preferred</span>@endif
                            </td>
                            <td class="py-2">
                                <div>{{ $s->pic_name }}</div>
                                <div style="font-size:11px;color:#6b7280">{{ $s->pic_position }}</div>
                            </td>
                            <td class="py-2" style="font-size:12px">{{ $s->phone }}</td>
                            <td class="py-2" style="font-size:12px">{{ $s->product_category ?? '-' }}</td>
                            <td class="py-2" style="font-size:12px;max-width:220px">
                                @php
                                    $productNames = $s->products->map(function ($p) {
                                        $name = trim($p->product_name ?? '');
                                        $unit = trim($p->unit ?? '');

                                        if ($name === '') {
                                            return null;
                                        }

                                        return $unit !== '' ? $name . ' (' . $unit . ')' : $name;
                                    })->filter()->values();
                                @endphp
                                @if($productNames->count() > 0)
                                    <div title="{{ $productNames->implode(', ') }}">{{ \Illuminate\Support\Str::limit($productNames->implode(', '), 70) }}</div>
                                @else
                                    <span style="color:#9ca3af">-</span>
                                @endif
                            </td>
                            <td class="py-2">
                                <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;
                                    background:{{ $s->source_type==='Local'?'#dbeafe':'#ede9fe' }};
                                    color:{{ $s->source_type==='Local'?'#1d4ed8':'#7c3aed' }}">
                                    {{ $s->source_type }}
                                    @if($s->source_type==='Import' && $s->origin_country)
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
                                    onclick="openEditSupplier({{ $s->id }})">
                                    <i class="fas fa-pencil-alt"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info" style="padding:3px 7px" title="Produk"
                                    onclick="openProductModal({{ $s->id }}, '{{ addslashes($s->supplier_name) }}')">
                                    <i class="fas fa-boxes" style="font-size:.7rem"></i>
                                </button>
                                <form method="POST" action="{{ route('suppliers.destroy', $s) }}" class="d-inline"
                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus supplier {{ addslashes($s->supplier_name) }}? Tindakan ini tidak dapat dibatalkan.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="10" class="text-center py-4" style="color:#9ca3af">Belum ada data supplier</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($suppliers->hasPages())
            <div class="px-3 py-2">{{ $suppliers->links() }}</div>
            @endif
        </div>
    </div>
</div>
</div>

{{-- Modal Tambah --}}
<div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Supplier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('suppliers.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Source Type <span class="text-danger">*</span></label>
                            <select name="source_type" class="form-select" id="addSourceType" onchange="toggleOrigin('add')">
                                <option value="Local">Local</option>
                                <option value="Import">Import</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="addOriginWrap" style="display:none">
                            <label class="form-label">Negara Asal</label>
                            <input type="text" name="origin_country" class="form-control" placeholder="China, India, dll">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategori Produk</label>
                            <input type="text" name="product_category" class="form-control" placeholder="Solvent, Resin, Pigment, dll">
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
                                <label class="form-check-label" for="addPreferred">Preferred Supplier</label>
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
                                <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-box me-1"></i> Produk Supplier</div>
                                <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addSupProductRow('addSupProductsContainer')"><i class="fas fa-plus me-1"></i> Add Produk</button>
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
<div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Supplier</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editSupplierForm">
                @csrf @method('PUT')
                <input type="hidden" name="pics_submitted" value="1">
                <input type="hidden" name="products_submitted" value="1">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Supplier <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" id="esName" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Source Type</label>
                            <select name="source_type" id="esSourceType" class="form-select" onchange="toggleOrigin('edit')">
                                <option value="Local">Local</option>
                                <option value="Import">Import</option>
                            </select>
                        </div>
                        <div class="col-md-3" id="editOriginWrap" style="display:none">
                            <label class="form-label">Negara Asal</label>
                            <input type="text" name="origin_country" id="esOrigin" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Kategori Produk</label>
                            <input type="text" name="product_category" id="esCategory" class="form-control">
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
                                <label class="form-check-label" for="esPreferred">Preferred Supplier</label>
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
                            <div style="font-size:.78rem;font-weight:600;color:var(--primary)"><i class="fas fa-box me-1"></i> Produk Supplier</div>
                            <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addSupProductRow('editSupProductsContainer')"><i class="fas fa-plus me-1"></i> Add Produk</button>
                        </div>
                        <div id="editSupProductsExisting" class="mt-1 mb-2"></div>
                        <div id="editSupProductsContainer"></div>
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

{{-- Modal Produk Supplier --}}
<div class="modal fade" id="supplierProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Produk Supplier — <span id="spModalName"></span></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                {{-- List produk existing --}}
                <div id="spProductList" class="mb-3"></div>
                {{-- Form tambah produk --}}
                <div style="background:#f9fafb;border-radius:8px;padding:12px">
                    <div style="font-size:.78rem;font-weight:600;margin-bottom:8px">Tambah Produk</div>
                    <form id="addSupplierProductForm" method="POST">
                        @csrf
                        <div class="row g-2">
                            <div class="col-5">
                                <input type="text" name="product_name" class="form-control form-control-sm" placeholder="Nama produk *" required>
                            </div>
                            <div class="col-3">
                                <select name="unit" class="form-select form-select-sm">
                                    <option value="ton">ton</option>
                                    <option value="kg">kg</option>
                                    <option value="liter">liter</option>
                                    <option value="drum">drum</option>
                                    <option value="pcs">pcs</option>
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
    $supplierEditData = $suppliers->mapWithKeys(function ($s) {
        return [$s->id => [
            'id' => $s->id,
            'supplier_name' => $s->supplier_name,
            'source_type' => $s->source_type,
            'pic_name' => $s->pic_name,
            'phone' => $s->phone,
            'email' => $s->email,
            'product_category' => $s->product_category,
            'origin_country' => $s->origin_country,
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
            'products' => $s->products->map(function ($product) {
                return [
                    'product_name' => $product->product_name,
                    'unit' => $product->unit,
                    'description' => $product->description,
                ];
            })->values(),
        ]];
    });
@endphp

@push('scripts')
<script>
function toggleOrigin(mode) {
    const sel  = document.getElementById(mode === 'add' ? 'addSourceType' : 'esSourceType');
    const wrap = document.getElementById(mode === 'add' ? 'addOriginWrap' : 'editOriginWrap');
    wrap.style.display = sel.value === 'Import' ? 'block' : 'none';
}

const supplierEditData = @json($supplierEditData);

function openEditSupplier(id) {
    const data = supplierEditData[id];
    if (!data) return;

    document.getElementById('editSupplierForm').action = `/suppliers/${id}`;
    document.getElementById('esName').value         = data.supplier_name || '';
    document.getElementById('esSourceType').value   = data.source_type || 'Local';
    document.getElementById('esOrigin').value       = data.origin_country || '';
    document.getElementById('esCategory').value     = data.product_category || '';
    document.getElementById('esPic').value          = data.pic_name || '';
    document.getElementById('esPhone').value        = data.phone || '';
    document.getElementById('esEmail').value        = data.email || '';
    document.getElementById('esStatus').value       = data.status || 'Active';
    document.getElementById('esRelationship').value = data.relationship_status || 'Potential';
    document.getElementById('esPreferred').checked  = !!data.is_preferred;
    document.getElementById('esRating').value       = data.rating || 0;
    toggleOrigin('edit');

    const editSupPicsExisting = document.getElementById('editSupPicsExisting');
    const editSupProductsExisting = document.getElementById('editSupProductsExisting');
    const editSupPicsContainer = document.getElementById('editSupPicsContainer');
    const editSupProductsContainer = document.getElementById('editSupProductsContainer');

    editSupPicsExisting.innerHTML = '';
    editSupProductsExisting.innerHTML = '';
    editSupPicsContainer.innerHTML = '';
    editSupProductsContainer.innerHTML = '';

    (data.pics || []).forEach(function(pic) {
        addSupPicRow('editSupPicsContainer', pic);
    });

    (data.products || []).forEach(function(product) {
        addSupProductRow('editSupProductsContainer', product);
    });

    if ((data.pics || []).length === 0) {
        editSupPicsExisting.innerHTML = '<div style="font-size:.75rem;color:#9ca3af"><i>Belum ada PIC tambahan.</i></div>';
    }

    if ((data.products || []).length === 0) {
        editSupProductsExisting.innerHTML = '<div style="font-size:.75rem;color:#9ca3af"><i>Belum ada produk supplier.</i></div>';
    }

    new bootstrap.Modal(document.getElementById('editSupplierModal')).show();
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// ── Inline Supplier PIC rows ──
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

// ── Inline Supplier Product rows ──
let supProdIdx = 0;
function addSupProductRow(containerId, data = {}) {
    const i = supProdIdx++;
    const html = `<div class="row g-2 mb-2 align-items-center" id="supProd_${i}">
        <div class="col-5"><input type="text" name="products[${i}][product_name]" class="form-control form-control-sm" placeholder="Nama Produk *" value="${escapeHtml(data.product_name)}" required></div>
        <div class="col-3"><input type="text" name="products[${i}][unit]" class="form-control form-control-sm" placeholder="Satuan (ton, kg...)" value="${escapeHtml(data.unit)}"></div>
        <div class="col-3"><input type="text" name="products[${i}][description]" class="form-control form-control-sm" placeholder="Keterangan" value="${escapeHtml(data.description)}"></div>
        <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('supProd_${i}').remove()"><i class="fas fa-times"></i></button></div>
    </div>`;
    document.getElementById(containerId).insertAdjacentHTML('beforeend', html);
}

// Supplier Products (AJAX via form submit → reload)
const supplierPics = @json($suppliers->pluck('pics', 'id'));
const supplierProducts = @json($suppliers->pluck('products', 'id'));

function openProductModal(supplierId, supplierName) {
    document.getElementById('spModalName').textContent = supplierName;
    document.getElementById('addSupplierProductForm').action = `/suppliers/${supplierId}/products`;

    // Render existing products
    const products = supplierProducts[supplierId] || [];
    const list = document.getElementById('spProductList');
    if (products.length === 0) {
        list.innerHTML = '<div style="font-size:.8rem;color:#9ca3af">Belum ada produk.</div>';
    } else {
        list.innerHTML = products.map(p => `
            <div class="d-flex align-items-center justify-content-between mb-2 pb-2" style="border-bottom:1px solid #f3f4f6">
                <div>
                    <div style="font-size:.82rem;font-weight:600">${p.product_name}</div>
                    <div style="font-size:.72rem;color:#6b7280">${p.unit}${p.description ? ' · ' + p.description : ''}</div>
                </div>
                <form method="POST" action="/suppliers/${supplierId}/products/${p.id}" onsubmit="return confirm('Hapus produk ${p.product_name}?')" style="display:inline">
                    <input type="hidden" name="_token" value="{{ csrf_token() }}">
                    <input type="hidden" name="_method" value="DELETE">
                    <button type="submit" style="color:#ef4444;background:none;border:none;cursor:pointer"><i class="fas fa-times"></i></button>
                </form>
            </div>
        `).join('');
    }

    new bootstrap.Modal(document.getElementById('supplierProductModal')).show();
}
</script>
@endpush
@endsection
