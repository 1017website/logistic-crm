@extends('layouts.app')
@section('title', $lead->company_name)
@section('page-title', '')

@section('content')
{{-- Breadcrumb --}}
<nav style="font-size:.8rem;color:var(--text-muted)" class="mb-3">
    <a href="{{ route('leads.index') }}" style="color:var(--primary)">Leads</a>
    <span class="mx-2">&rsaquo;</span>
    <span>Detail Lead</span>
</nav>

{{-- Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="user-avatar" style="width:50px;height:50px;font-size:1rem;border-radius:10px">
            {{ $lead->logo_initials }}
        </div>
        <div>
            <h4 class="mb-1 fw-bold">{{ $lead->company_name }}</h4>
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <span style="font-size:.75rem;color:var(--text-muted)">{{ $lead->lead_code }}</span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editLeadModal">
            <i class="fas fa-edit me-1"></i> Edit Lead
        </button>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addActivityModal">
            <i class="fas fa-plus me-1"></i> Add Activity
        </button>
    </div>
</div>

{{-- Main Content --}}
<div class="row g-3">

    {{-- LEFT: Company Info --}}
    <div class="col-lg-4">
        {{-- Info Perusahaan --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Informasi Company</span>
                <button class="btn btn-sm p-0" style="font-size:.75rem;color:var(--primary);background:none;border:none" data-bs-toggle="modal" data-bs-target="#editLeadModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
            </div>
            <div class="card-body p-3">
                @foreach([
                ['icon'=>'building','label'=>'Nama Perusahaan','value'=>$lead->company_name],
                ['icon'=>'industry','label'=>'Industry','value'=>$lead->industry ?? '-'],
                ['icon'=>'map-marker-alt','label'=>'Lokasi','value'=>$lead->location ?? '-'],
                ['icon'=>'globe','label'=>'Sumber Lead','value'=>$lead->lead_source ?? '-'],
                ['icon'=>'user-tie','label'=>'Sales PIC','value'=>$lead->salesUser?->name ?? '-'],
                ['icon'=>'calendar','label'=>'Dibuat','value'=>$lead->created_at->format('d M Y')],
                ] as $f)
                <div class="d-flex gap-2 mb-2">
                    <i class="fas fa-{{ $f['icon'] }}" style="width:16px;color:var(--text-muted);margin-top:2px;font-size:.75rem;flex-shrink:0"></i>
                    <div>
                        <div style="font-size:.68rem;color:var(--text-muted)">{{ $f['label'] }}</div>
                        <div style="font-size:.8rem;font-weight:500">{{ $f['value'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Multi PIC --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Daftar PIC</span>
                <button class="btn btn-sm p-0" style="font-size:.75rem;color:var(--primary);background:none;border:none" data-bs-toggle="modal" data-bs-target="#addLeadPicModal">
                    <i class="fas fa-plus me-1"></i> Tambah PIC
                </button>
            </div>
            <div class="card-body p-3">
                {{-- PIC utama (dari lead) --}}
                <div class="d-flex align-items-start gap-2 mb-3 pb-2" style="border-bottom:1px solid #f3f4f6">
                    <div style="width:30px;height:30px;background:#e5e5e5;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas fa-user" style="color:#111111;font-size:.65rem"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.8rem;font-weight:600">{{ $lead->pic_name }}
                            <span style="font-size:.65rem;background:#e5e5e5;color:#000000;padding:1px 6px;border-radius:10px;margin-left:4px">Utama</span>
                        </div>
                        @if($lead->pic_position)<div style="font-size:.72rem;color:var(--text-muted)">{{ $lead->pic_position }}</div>@endif
                        @if($lead->phone)<div style="font-size:.72rem">{{ $lead->phone }}</div>@endif
                        @if($lead->email)<div style="font-size:.72rem;color:var(--primary)">{{ $lead->email }}</div>@endif
                    </div>
                </div>
                {{-- PIC tambahan --}}
                @forelse($lead->pics as $pic)
                <div class="d-flex align-items-start gap-2 mb-2">
                    <div style="width:30px;height:30px;background:#f3f4f6;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas fa-user" style="color:#6b7280;font-size:.65rem"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.8rem;font-weight:600">{{ $pic->pic_name }}</div>
                        @if($pic->pic_position)<div style="font-size:.72rem;color:var(--text-muted)">{{ $pic->pic_position }}</div>@endif
                        @if($pic->phone)<div style="font-size:.72rem">{{ $pic->phone }}</div>@endif
                        @if($pic->email)<div style="font-size:.72rem;color:var(--primary)">{{ $pic->email }}</div>@endif
                    </div>
                    @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('leads.pics.destroy', [$lead, $pic]) }}" onsubmit="return confirm('Hapus PIC {{ addslashes($pic->pic_name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm p-0" style="color:#ef4444;background:none;border:none"><i class="fas fa-times"></i></button>
                    </form>
                    @endif
                </div>
                @empty
                <div style="font-size:.78rem;color:var(--text-muted)">Belum ada PIC tambahan.</div>
                @endforelse
            </div>
        </div>

        {{-- Layanan --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Kebutuhan Layanan</span>
                <button class="btn btn-sm p-0" style="font-size:.75rem;color:var(--primary);background:none;border:none" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fas fa-plus me-1"></i> Tambah
                </button>
            </div>
            <div class="card-body p-3">
                @forelse($lead->products as $prod)
                <div class="d-flex align-items-center justify-content-between mb-2 pb-2" style="border-bottom:1px solid #f9fafb">
                    <div>
                        <div style="font-size:.82rem;font-weight:600">{{ $prod->display_name }}</div>
                        @php
                            $metaParts = [];
                            if ($prod->unit) $metaParts[] = e($prod->unit);
                            if ($prod->tonnage) $metaParts[] = rtrim(rtrim(number_format($prod->tonnage, 3, ',', '.'), '0'), ',') . ' ton';
                        @endphp
                        @if(count($metaParts) || $prod->shipping_zone)
                        <div style="font-size:.72rem;color:var(--text-muted)">
                            {!! implode(' · ', $metaParts) !!}@if($prod->shipping_zone)@if(count($metaParts)) · @endif<i class="fas fa-map-marker-alt"></i> {{ $prod->shipping_zone }}@endif
                        </div>
                        @endif
                    </div>
                    @if(auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('leads.products.destroy', [$lead, $prod]) }}" onsubmit="return confirm('Hapus layanan {{ addslashes($prod->display_name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn btn-sm p-0" style="color:#ef4444;background:none;border:none"><i class="fas fa-times"></i></button>
                    </form>
                    @endif
                </div>
                @empty
                <div style="font-size:.78rem;color:var(--text-muted)">Belum ada layanan ditambahkan.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- MIDDLE: Activity --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Activity Timeline</span>
                <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                    <i class="fas fa-plus me-1"></i> Add Activity
                </button>
            </div>
            <div class="card-body p-3">
                <div class="activity-timeline">
                    @forelse($lead->activities->sortByDesc('activity_at') as $act)
                    <div class="activity-item">
                        <div class="activity-time" style="font-size:.7rem;color:var(--text-muted);min-width:45px">
                            {{ $act->activity_at?->format('d M') ?? '-' }}
                        </div>
                        <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#e5e5e5' : ($act->type === 'Email' ? '#fef3c7' : '#f3f4f6')) }}">
                            <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#111111' : ($act->type === 'Email' ? '#d97706' : '#6b7280')) }};font-size:.75rem"></i>
                        </div>
                        <div class="activity-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="activity-subject">{{ $act->subject ?: $act->type }}</span>
                                    <span class="ms-2 badge-{{ strtolower($act->status) }}" style="font-size:.65rem">{{ $act->status }}</span>
                                    @if($act->pipeline_stage)
                                        <span class="ms-1" style="font-size:.62rem;padding:1px 6px;border-radius:12px;background:#e5e5e5;color:#000000;font-weight:600">{{ $act->pipeline_stage === 'Won' ? 'Won/Closing' : $act->pipeline_stage }}</span>
                                    @endif
                                </div>
                            </div>
                            @if($act->description)
                            <div class="activity-desc">{{ $act->description }}</div>
                            @endif
                            <div class="activity-meta">
                                <span><i class="fas fa-user me-1"></i>{{ $act->salesUser?->name ?? '-' }}</span>
                                <span class="ms-2"><i class="fas fa-clock me-1"></i>{{ $act->activity_at?->format('H:i') ?? '-' }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4" style="color:var(--text-muted);font-size:.8rem">
                        <i class="fas fa-calendar-times" style="font-size:1.5rem;display:block;margin-bottom:8px;opacity:.3"></i>
                        Belum ada aktivitas.
                    </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Catatan Internal --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Catatan Internal</span>
                <button class="btn btn-sm p-0" style="font-size:.75rem;color:var(--primary);background:none;border:none" data-bs-toggle="modal" data-bs-target="#editCatatanModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
            </div>
            <div class="card-body p-3">
                @if($lead->catatan_internal)
                @foreach(explode("\n", $lead->catatan_internal) as $line)
                @if(trim($line))
                <div class="d-flex gap-2 mb-1">
                    <i class="fas fa-circle" style="font-size:.35rem;color:var(--primary);margin-top:6px;flex-shrink:0"></i>
                    <span style="font-size:.8rem">{{ trim($line) }}</span>
                </div>
                @endif
                @endforeach
                @else
                <p style="font-size:.8rem;color:var(--text-muted)">Belum ada catatan internal.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- RIGHT: Status & Actions --}}
    <div class="col-lg-3">
        {{-- Next Follow Up --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Next Follow Up</span>
                <button class="btn btn-sm p-0" style="font-size:.75rem;color:var(--primary);background:none;border:none" data-bs-toggle="modal" data-bs-target="#editFollowUpModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
            </div>
            <div class="card-body p-3">
                @if($lead->next_follow_up)
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:32px;height:32px;background:#e5e5e5;border-radius:7px;display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-calendar" style="color:#111111;font-size:.75rem"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:.85rem">{{ $lead->next_follow_up->format('d M Y') }}</div>
                        @if($lead->next_follow_up_time)
                        <div style="font-size:.75rem;color:var(--text-muted)">{{ substr($lead->next_follow_up_time, 0, 5) }}</div>
                        @endif
                    </div>
                </div>
                @if($lead->next_follow_up_notes)
                <div style="font-size:.78rem;color:#374151">{{ $lead->next_follow_up_notes }}</div>
                @endif
                @else
                <p style="font-size:.8rem;color:var(--text-muted)">Belum dijadwalkan.</p>
                @endif
            </div>
        </div>

        {{-- Status & Info --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Status & Info</span>
                <button class="btn btn-sm p-0" style="font-size:.75rem;color:var(--primary);background:none;border:none" data-bs-toggle="modal" data-bs-target="#editStatusModal">
                    <i class="fas fa-edit me-1"></i>Edit
                </button>
            </div>
            <div class="card-body p-3">
                @foreach([
                ['label'=>'Lead Score','value'=>($lead->lead_score ?? 0) . ' / 100'],
                ['label'=>'Probability','value'=>($lead->probability ?? 0) . '%'],
                ['label'=>'Expected Closing','value'=>$lead->expected_closing ? $lead->expected_closing->format('M Y') : '-'],
                ['label'=>'Competitor','value'=>$lead->competitor ?? '-'],
                ] as $f)
                <div class="d-flex justify-content-between align-items-center py-1" style="border-bottom:1px solid #f3f4f6;font-size:.78rem">
                    <span style="color:var(--text-muted)">{{ $f['label'] }}</span>
                    <span style="font-weight:600;text-align:right;max-width:55%">{{ $f['value'] }}</span>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Hapus Lead --}}
        @if(auth()->user()->isAdmin())
        <div class="card">
            <div class="card-body p-3">
                <form method="POST" action="{{ route('leads.destroy', $lead) }}" onsubmit="return confirm('Apakah Anda yakin ingin menghapus lead {{ addslashes($lead->company_name) }}? Tindakan ini tidak dapat dibatalkan.')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                        <i class="fas fa-trash me-1"></i> Hapus Lead Ini
                    </button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- ===================== MODALS ===================== --}}

{{-- 1. Edit Lead Modal --}}
<div class="modal fade" id="editLeadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Lead — {{ $lead->company_name }}</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.update', $lead) }}">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nama Perusahaan <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="{{ $lead->company_name }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Industry</label>
                            <input type="text" name="industry" class="form-control" value="{{ $lead->industry }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Lokasi</label>
                            <input type="text" name="location" class="form-control" value="{{ $lead->location }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Alamat</label>
                            <input type="text" name="address" class="form-control" value="{{ $lead->address }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">PIC Name <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control" value="{{ $lead->pic_name }}" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Jabatan PIC</label>
                            <input type="text" name="pic_position" class="form-control" value="{{ $lead->pic_position }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ $lead->phone }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ $lead->email }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sumber Lead</label>
                            <select name="lead_source" class="form-select">
                                <option value="">- Pilih -</option>
                                @foreach(['Referral','Website','Cold Call','Email Campaign','Social Media','Exhibition','Lainnya'] as $src)
                                <option value="{{ $src }}" {{ $lead->lead_source === $src ? 'selected' : '' }}>{{ $src }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Competitor / Vendor Existing</label>
                            <input type="text" name="competitor" class="form-control" value="{{ $lead->competitor }}" placeholder="Vendor yang sedang digunakan">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Probability (%)</label>
                            <input type="number" name="probability" class="form-control" min="0" max="100" value="{{ $lead->probability }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Closing</label>
                            <input type="date" name="expected_closing" class="form-control" value="{{ $lead->expected_closing?->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-4">
                            @include('components.sales-pic-field', ['selectedId' => $lead->user_id])
                        </div>
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

{{-- 2. Add Activity Modal --}}
@include('components.shared-activity-modal', [
    'activityModalTitle' => 'Add Activity — '.$lead->company_name,
    'activityContextType' => 'lead',
    'activityContextId' => $lead->id,
    'activityContextLabel' => $lead->company_name.' (Lead)',
    'activityContextStage' => $lead->pipeline_stage,
])

{{-- 3. Edit Catatan Internal Modal --}}
<div class="modal fade" id="editCatatanModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Catatan Internal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.update', $lead) }}">
                @csrf @method('PUT')
                <div class="modal-body">
                    <label class="form-label">Catatan Internal</label>
                    <textarea name="catatan_internal" class="form-control" rows="6" placeholder="Catatan internal tentang lead ini...">{{ $lead->catatan_internal }}</textarea>
                    <div class="form-text">Satu baris = satu poin catatan</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- 4. Edit Follow Up Modal --}}
<div class="modal fade" id="editFollowUpModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Set Next Follow Up</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.update', $lead) }}">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tanggal Follow Up</label>
                        <input type="date" name="next_follow_up" class="form-control" value="{{ $lead->next_follow_up?->format('Y-m-d') }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Catatan</label>
                        <textarea name="next_follow_up_notes" class="form-control" rows="3" placeholder="Tujuan follow up...">{{ $lead->next_follow_up_notes }}</textarea>
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

{{-- 5. Edit Status Modal --}}
<div class="modal fade" id="editStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Status & Info</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.update', $lead) }}">
                @csrf @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Probability (%)</label>
                            <input type="number" name="probability" class="form-control" min="0" max="100" value="{{ $lead->probability }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Closing</label>
                            <input type="date" name="expected_closing" class="form-control" value="{{ $lead->expected_closing?->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Competitor / Vendor Existing</label>
                            <input type="text" name="competitor" class="form-control" value="{{ $lead->competitor }}">
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

{{-- 6. Add Layanan Modal --}}
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Layanan</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.products.store', $lead) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Nama Layanan <span class="text-danger">*</span></label>
                            <input type="text" name="service_name" list="vendorServiceOptions" class="form-control" required placeholder="Contoh: Trucking trailer">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Satuan (unit)</label>
                            <input type="text" name="unit" class="form-control" placeholder="Contoh: trip, container, kg">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Tonase</label>
                            <input type="number" step="0.001" min="0" name="tonnage" class="form-control" placeholder="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Zona Pengiriman</label>
                            <input type="text" name="shipping_zone" class="form-control" placeholder="Contoh: Jawa Timur, Jabodetabek, Sumatera">
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

{{-- 7. Add PIC Modal --}}
<div class="modal fade" id="addLeadPicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah PIC</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.pics.store', $lead) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Nama PIC <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Jabatan</label>
                            <input type="text" name="pic_position" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan PIC</button>
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