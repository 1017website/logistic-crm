@extends('layouts.app')

@section('title', 'Leads')
@section('page-title', 'Leads')
@section('page-subtitle', 'Kelola data prospek dan potensi penjualan')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex gap-2">
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLeadModal">
                <i class="fas fa-plus me-1"></i> Add Lead
            </button>
            {{-- <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fas fa-upload me-1"></i> Import
            </button> --}}
            <a href="{{ route('leads.export') }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i> Export Excel
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('leads.index') }}">
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-4">
                        <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari company..."
                            value="{{ $search }}">
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary ms-1">Reset</a>
                    </div>
                </div>
            </div>
        </div>
    </form>

    {{-- Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table crm-table mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">No.</th>
                            <th>Company</th>
                            <th>PIC / Jabatan</th>
                            <th>Pipeline Stage</th>
                            <th>Product Interest</th>
                            <th>Sales PIC</th>
                            <th>Next Follow Up</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leads as $i => $lead)
                            <tr>
                                <td style="color:#9ca3af;font-size:.75rem">{{ $leads->firstItem() + $i }}</td>
                                <td>
                                    <a href="{{ route('leads.show', $lead) }}"
                                        style="font-weight:600;color:#111;text-decoration:none">{{ $lead->company_name }}</a>
                                    <div style="font-size:.7rem;color:var(--text-muted)">{{ $lead->lead_code }}</div>
                                </td>
                                <td>
                                    <div style="font-size:.8rem">{{ $lead->pic_name }}</div>
                                    <div style="font-size:.7rem;color:var(--text-muted)">
                                        {{ $lead->pic_position ?? $lead->phone }}</div>
                                </td>
                                <td>
                                    @php
                                        $stageColors = [
                                            'Identifying' => ['bg'=>'#e5e5e5','color'=>'#000000'],
                                            'Approaching' => ['bg'=>'#fef3c7','color'=>'#b45309'],
                                            'Follow Up'   => ['bg'=>'#ede9fe','color'=>'#6d28d9'],
                                            'Won'         => ['bg'=>'#d1fae5','color'=>'#065f46'],
                                            'Lost'        => ['bg'=>'#fee2e2','color'=>'#991b1b'],
                                            'Maintaining' => ['bg'=>'#e0e7ff','color'=>'#3730a3'],
                                        ];
                                        $sc = $stageColors[$lead->pipeline_stage] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
                                        $stageLabel = $lead->pipeline_stage === 'Won' ? 'Won/Closing' : $lead->pipeline_stage;
                                    @endphp
                                    <span style="background:{{ $sc['bg'] }};color:{{ $sc['color'] }};padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:600">
                                        {{ $stageLabel }}
                                    </span>
                                </td>
                                <td>
                                    <div style="font-size:.8rem">
                                        @if($lead->products->count())
                                            @foreach($lead->products->take(2) as $p)
                                                <div>{{ $p->display_name }} @if($p->unit)<span style="color:var(--text-muted);font-size:.7rem">{{ $p->unit }}</span>@endif</div>
                                            @endforeach
                                            @if($lead->products->count() > 2)
                                                <div style="font-size:.7rem;color:var(--text-muted)">+{{ $lead->products->count() - 2 }} lainnya</div>
                                            @endif
                                        @else
                                            -
                                        @endif
                                    </div>
                                </td>
                                <td style="font-size:.78rem">{{ $lead->salesUser?->name }}</td>
                                <td style="font-size:.78rem">
                                    @if($lead->next_follow_up)
                                        <span
                                            style="color:{{ $lead->next_follow_up->isPast() ? '#dc2626' : '#d97706' }};font-weight:600">
                                            {{ $lead->next_follow_up->format('d M Y') }}
                                        </span>
                                    @else
                                        <span class="text-muted">-</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="{{ route('leads.show', $lead) }}" class="btn btn-sm btn-outline-primary"
                                            style="padding:3px 7px" title="Lihat Detail">
                                            <i class="fas fa-eye" style="font-size:.7rem"></i>
                                        </a>
                                        <a href="{{ route('leads.show', ['lead' => $lead, 'edit' => 1]) }}" class="btn btn-sm btn-outline-secondary"
                                            style="padding:3px 7px" title="Edit Lead">
                                            <i class="fas fa-edit" style="font-size:.7rem"></i>
                                        </a>
                                        @include('components.delete-request-button', [
                                            'module'  => 'leads',
                                            'id'      => $lead->id,
                                            'label'   => $lead->company_name,
                                            'pending' => in_array($lead->id, $pendingDeletionLeadIds ?? []),
                                        ])
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-4 text-muted">Tidak ada data leads.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($leads->hasPages())
            <div class="card-footer p-3">{{ $leads->links() }}</div>
        @endif
    </div>

    {{-- Add Lead Modal --}}
    <div class="modal fade" id="addLeadModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Add Lead Baru</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('leads.store') }}" id="addLeadForm">
                    @csrf
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger alert-sm py-2 mb-3">
                                <ul class="mb-0 ps-3" style="font-size:.8rem">
                                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Company Name *</label>
                                <input type="text" name="company_name" class="form-control" value="{{ old('company_name') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Industry</label>
                                <input type="text" name="industry" class="form-control" value="{{ old('industry') }}" placeholder="Misal: Manufaktur, Retail">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Lokasi</label>
                                <input type="text" name="location" class="form-control" value="{{ old('location') }}" placeholder="Kota/Wilayah">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Alamat</label>
                                <input type="text" name="address" class="form-control" value="{{ old('address') }}">
                            </div>
                            {{-- PIC Utama --}}
                            <div class="col-12">
                                <div style="font-size:.78rem;font-weight:600;color:var(--primary);margin-bottom:4px">
                                    <i class="fas fa-user me-1"></i> PIC Utama
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Nama PIC *</label>
                                <input type="text" name="pic_name" class="form-control" value="{{ old('pic_name') }}" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Jabatan PIC</label>
                                <input type="text" name="pic_position" class="form-control" value="{{ old('pic_position') }}" placeholder="Direktur, Procurement...">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Phone</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone') }}">
                            </div>
                            <div class="col-6">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control" value="{{ old('email') }}">
                            </div>
                            {{-- Sales Info --}}
                            <div class="col-6">
                                <label class="form-label">Lead Source</label>
                                <select name="lead_source" class="form-select">
                                    <option value="">- Pilih -</option>
                                    @foreach(['Referral', 'Website', 'Cold Call', 'Email Campaign', 'Social Media', 'Exhibition', 'Lainnya'] as $src)
                                        <option value="{{ $src }}" {{ old('lead_source') == $src ? 'selected' : '' }}>{{ $src }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Expected Closing</label>
                                <input type="date" name="expected_closing" class="form-control" value="{{ old('expected_closing') }}">
                            </div>
                            <div class="col-12">
                                @include('components.sales-pic-field')
                            </div>

                            {{-- Tambahan PICs --}}
                            <div class="col-12 mt-1">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div style="font-size:.78rem;font-weight:600;color:var(--primary)">
                                        <i class="fas fa-users me-1"></i> PIC Tambahan
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addLeadPicRow()"><i class="fas fa-plus me-1"></i> Add PIC</button>
                                </div>
                                <div id="leadPicsContainer"></div>
                            </div>

                            {{-- Kebutuhan Layanan --}}
                            <div class="col-12 mt-1">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div style="font-size:.78rem;font-weight:600;color:var(--primary)">
                                        <i class="fas fa-box me-1"></i> Kebutuhan Layanan
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-primary" style="font-size:.7rem;padding:2px 8px" onclick="addLeadProductRow()"><i class="fas fa-plus me-1"></i> Add Layanan</button>
                                </div>
                                <div id="leadProductsContainer"></div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-sm btn-primary">Simpan Lead</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title fw-bold">Import Leads dari CSV</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('leads.import') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-body">
                        <div class="mb-3 p-3" style="background:#f9fafb;border-radius:8px;border:1px solid #e5e7eb">
                            <div style="font-size:12px;font-weight:600;color:#374151;margin-bottom:8px">
                                <i class="fas fa-info-circle text-primary me-1"></i> Format CSV
                            </div>
                            <div style="font-size:11px;color:#6b7280;line-height:1.8">
                                Kolom: <strong>Lead Code, Company Name, PIC Name, Phone, Email, Pipeline Stage, Temperature,
                                    Product Interest, Volume Estimate, Potensi Revenue, Probability, Expected Closing, Sales
                                    PIC, Lead Source</strong>
                            </div>
                            <a href="{{ route('leads.template') }}" class="btn btn-sm btn-outline-primary mt-2"
                                style="font-size:11px">
                                <i class="fas fa-download me-1"></i> Download Template CSV
                            </a>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Pilih File CSV <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control" accept=".csv,.txt" required>
                            <div class="form-text">Format: .csv, maksimal 2MB</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fas fa-upload me-1"></i> Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>


<datalist id="vendorServiceOptions">
    @foreach(($vendorServices ?? collect()) as $svc)
        <option value="{{ $svc->service_name }}">{{ $svc->vendor?->company_name ?? $svc->vendor?->vendor_name ?? '' }}</option>
    @endforeach
</datalist>

@endsection

@push('scripts')
    <script>
        function confirmDelete(msg) {
            return confirm(msg || 'Apakah Anda yakin ingin menghapus data ini?');
        }

        // ── Inline PIC rows ──
        let leadPicIdx = 0;
        function addLeadPicRow() {
            const i = leadPicIdx++;
            const html = `<div class="row g-2 mb-2 align-items-center" id="leadPic_${i}">
                <div class="col-4"><input type="text" name="pics[${i}][pic_name]" class="form-control form-control-sm" placeholder="Nama PIC *" required></div>
                <div class="col-3"><input type="text" name="pics[${i}][pic_position]" class="form-control form-control-sm" placeholder="Jabatan"></div>
                <div class="col-2"><input type="text" name="pics[${i}][phone]" class="form-control form-control-sm" placeholder="Phone"></div>
                <div class="col-2"><input type="email" name="pics[${i}][email]" class="form-control form-control-sm" placeholder="Email"></div>
                <div class="col-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('leadPic_${i}').remove()"><i class="fas fa-times"></i></button></div>
            </div>`;
            document.getElementById('leadPicsContainer').insertAdjacentHTML('beforeend', html);
        }

        // ── Inline Product rows ──
        let leadProdIdx = 0;
        function addLeadProductRow() {
            const i = leadProdIdx++;
            const html = `<div class="row g-2 mb-2 align-items-center" id="leadProd_${i}">
                <div class="col-md-4"><input type="text" name="products[${i}][service_name]" list="vendorServiceOptions" class="form-control form-control-sm" placeholder="Nama Layanan *" required></div>
                <div class="col-md-2"><input type="text" name="products[${i}][unit]" class="form-control form-control-sm" placeholder="Satuan"></div>
                <div class="col-md-2"><input type="number" step="0.001" min="0" name="products[${i}][tonnage]" class="form-control form-control-sm" placeholder="Tonase"></div>
                <div class="col-md-3"><input type="text" name="products[${i}][shipping_zone]" class="form-control form-control-sm" placeholder="Zona Pengiriman"></div>
                <div class="col-md-1 text-end"><button type="button" class="btn btn-sm btn-outline-danger p-1" onclick="document.getElementById('leadProd_${i}').remove()"><i class="fas fa-times"></i></button></div>
            </div>`;
            document.getElementById('leadProductsContainer').insertAdjacentHTML('beforeend', html);
        }

        // Auto-reopen modal Add Lead jika ada error validasi (preserve data)
        @if($errors->any())
            document.addEventListener('DOMContentLoaded', function () {
                var modal = new bootstrap.Modal(document.getElementById('addLeadModal'));
                modal.show();
            });
        @endif

        // Prevent data loss - warn sebelum close modal jika ada isian
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('addLeadForm');
            const modalEl = document.getElementById('addLeadModal');
            if (!form || !modalEl) return;
            modalEl.addEventListener('hide.bs.modal', function(e) {
                const inputs = form.querySelectorAll('input[type=text],input[type=email],textarea');
                let hasData = false;
                inputs.forEach(i => { if (i.value.trim()) hasData = true; });
                if (hasData && !confirm('Data yang sudah diisi akan hilang. Tutup form?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
@endpush