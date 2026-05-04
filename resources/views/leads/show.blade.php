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
        <form method="POST" action="{{ route('leads.update', $lead) }}" class="d-inline">
            @csrf @method('PUT')
            <input type="hidden" name="pipeline_stage" value="Lost">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tandai sebagai Lost?')">
                <i class="fas fa-times-circle me-1"></i> Mark as Lost
            </button>
        </form>
        <form method="POST" action="{{ route('leads.update', $lead) }}" class="d-inline">
            @csrf @method('PUT')
            <input type="hidden" name="pipeline_stage" value="Won">
            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Convert lead ini ke Deal Won?')">
                <i class="fas fa-check-circle me-1"></i> Convert to Deal
            </button>
        </form>
    </div>
</div>

{{-- Main Content --}}
<div class="row g-3">

    {{-- LEFT: Company Info --}}
    <div class="col-lg-4">
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
                ['icon'=>'user','label'=>'PIC','value'=>$lead->pic_name],
                ['icon'=>'briefcase','label'=>'Jabatan','value'=>$lead->pic_position ?? '-'],
                ['icon'=>'phone','label'=>'Phone','value'=>$lead->phone ?? '-'],
                ['icon'=>'envelope','label'=>'Email','value'=>$lead->email ?? '-'],
                ['icon'=>'industry','label'=>'Industry','value'=>$lead->industry ?? '-'],
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

        @if($lead->service_type)
        <div class="card mb-3">
            <div class="card-header">Kebutuhan & Rute</div>
            <div class="card-body p-3">
                @foreach([
                ['icon'=>'ship','label'=>'Jenis Layanan','value'=>$lead->service_type],
                ['icon'=>'route','label'=>'Rute','value'=>$lead->route ?? '-'],
                ['icon'=>'box','label'=>'Commodity','value'=>$lead->commodity ?? '-'],
                ['icon'=>'chart-bar','label'=>'Volume','value'=>$lead->volume_estimate ?? '-'],
                ['icon'=>'clock','label'=>'Timeline','value'=>$lead->timeline ?? '-'],
                ] as $f)
                <div class="d-flex gap-2 mb-2">
                    <i class="fas fa-{{ $f['icon'] }}" style="width:16px;color:var(--text-muted);font-size:.75rem;flex-shrink:0;margin-top:2px"></i>
                    <div>
                        <div style="font-size:.68rem;color:var(--text-muted)">{{ $f['label'] }}</div>
                        <div style="font-size:.78rem">{{ $f['value'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    {{-- MIDDLE: Activity --}}
    <div class="col-lg-5">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Activity Timeline</span>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                        <i class="fas fa-plus me-1"></i> Add Activity
                    </button>
                    <button class="btn btn-sm btn-outline-success" style="font-size:.72rem" onclick="quickActivity('Call')">
                        <i class="fas fa-phone me-1"></i> Log Call
                    </button>
                    <button class="btn btn-sm btn-outline-primary" style="font-size:.72rem" onclick="quickActivity('Visit')">
                        <i class="fas fa-building me-1"></i> Log Visit
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="activity-timeline">
                    @forelse($lead->activities->sortByDesc('activity_at') as $act)
                    <div class="activity-item">
                        <div class="activity-time" style="font-size:.7rem;color:var(--text-muted);min-width:45px">
                            {{ $act->activity_at->format('d M') }}
                        </div>
                        <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#dbeafe' : ($act->type === 'Email' ? '#fef3c7' : '#f3f4f6')) }}">
                            <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#2563eb' : ($act->type === 'Email' ? '#d97706' : '#6b7280')) }};font-size:.75rem"></i>
                        </div>
                        <div class="activity-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <span class="activity-subject">{{ $act->subject ?: $act->type }}</span>
                                    <span class="ms-2 badge-{{ strtolower($act->status) }}" style="font-size:.65rem">{{ $act->status }}</span>
                                </div>
                            </div>
                            @if($act->description)
                            <div class="activity-desc">{{ $act->description }}</div>
                            @endif
                            <div class="activity-meta">
                                <span><i class="fas fa-user me-1"></i>{{ $act->salesUser?->name ?? '-' }}</span>
                                <span class="ms-2"><i class="fas fa-clock me-1"></i>{{ $act->activity_at->format('H:i') }}</span>
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
                    <div style="width:32px;height:32px;background:#dbeafe;border-radius:7px;display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-calendar" style="color:#2563eb;font-size:.75rem"></i>
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
                ['label'=>'Potensi Revenue','value'=>idr($lead->potensi_revenue)],
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

        {{-- Quick Actions --}}
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body p-3">
                <div class="row g-2">
                    @foreach([
                    ['icon'=>'phone','label'=>'Log Call','color'=>'#d1fae5','ico'=>'#059669','action'=>"quickActivity('Call')"],
                    ['icon'=>'building','label'=>'Log Visit','color'=>'#dbeafe','ico'=>'#2563eb','action'=>"quickActivity('Visit')"],
                    ['icon'=>'envelope','label'=>'Log Email','color'=>'#fef3c7','ico'=>'#d97706','action'=>"quickActivity('Email')"],
                    ['icon'=>'sticky-note','label'=>'Add Note','color'=>'#ccfbf1','ico'=>'#0d9488','action'=>"quickActivity('Note')"],
                    ['icon'=>'bell','label'=>'Set Reminder','color'=>'#fee2e2','ico'=>'#dc2626','action'=>"document.getElementById('actType').value='Task';new bootstrap.Modal(document.getElementById('addActivityModal')).show()"],
                    ['icon'=>'file-invoice','label'=>'Quotation','color'=>'#ede9fe','ico'=>'#7c3aed','action'=>''],
                    ] as $qa)
                    <div class="col-4">
                        <div class="quick-action-btn" onclick="{{ $qa['action'] }}" style="cursor:pointer">
                            <div class="qa-icon" style="background:{{ $qa['color'] }}">
                                <i class="fas fa-{{ $qa['icon'] }}" style="color:{{ $qa['ico'] }};font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">{{ $qa['label'] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
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
                            <label class="form-label">PIC Name <span class="text-danger">*</span></label>
                            <input type="text" name="pic_name" class="form-control" value="{{ $lead->pic_name }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Jabatan</label>
                            <input type="text" name="pic_position" class="form-control" value="{{ $lead->pic_position }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ $lead->phone }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ $lead->email }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Industry</label>
                            <input type="text" name="industry" class="form-control" value="{{ $lead->industry }}">
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
                        <div class="col-md-4">
                            <label class="form-label">Service Type</label>
                            <select name="service_type" class="form-select">
                                <option value="">- Pilih -</option>
                                @foreach(['Sea Freight Import','Sea Freight Export','Air Freight Import','Air Freight Export','Trucking Domestic','Custom Clearance'] as $svc)
                                <option value="{{ $svc }}" {{ $lead->service_type === $svc ? 'selected' : '' }}>{{ $svc }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Rute</label>
                            <input type="text" name="route" class="form-control" value="{{ $lead->route }}" placeholder="Contoh: Jakarta - Surabaya">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Commodity</label>
                            <input type="text" name="commodity" class="form-control" value="{{ $lead->commodity }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Potensi Revenue (IDR)</label>
                            <input type="text" name="potensi_revenue" class="form-control idr-input" value="{{ idr_input($lead->potensi_revenue) }}" placeholder="Contoh: 100.000.000">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Probability (%)</label>
                            <input type="number" name="probability" class="form-control" min="0" max="100" value="{{ $lead->probability }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Expected Closing</label>
                            <input type="date" name="expected_closing" class="form-control" value="{{ $lead->expected_closing?->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            @include('components.sales-pic-field', ['selectedId' => $lead->user_id])
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Competitor</label>
                            <input type="text" name="competitor" class="form-control" value="{{ $lead->competitor }}">
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
<div class="modal fade" id="addActivityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Activity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('leads.activity.store', $lead) }}">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenis Activity <span class="text-danger">*</span></label>
                            <select name="type" id="actType" class="form-select" required>
                                @foreach(['Call','Visit','Email','Note','Task'] as $t)
                                <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Done">Done</option>
                                <option value="Planned">Planned</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" required placeholder="Contoh: Follow up tawaran Sea Freight">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal & Waktu <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="activity_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                        </div>
                        <div class="col-md-6">
                            @include('components.sales-pic-field', ['selectedId' => $lead->user_id])
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Detail aktivitas..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Next Follow Up</label>
                            <input type="date" name="next_follow_up" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                            <label class="form-label">Potensi Revenue</label>
                            <input type="text" name="potensi_revenue" class="form-control idr-input" value="{{ idr_input($lead->potensi_revenue) }}" placeholder="Contoh: 100.000.000">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Probability (%)</label>
                            <input type="number" name="probability" class="form-control" min="0" max="100" value="{{ $lead->probability }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Closing</label>
                            <input type="date" name="expected_closing" class="form-control" value="{{ $lead->expected_closing?->format('Y-m-d') }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Competitor</label>
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

@endsection

@push('scripts')
<script>
    function quickActivity(type) {
        document.getElementById('actType').value = type;
        new bootstrap.Modal(document.getElementById('addActivityModal')).show();
    }
</script>
@endpush