@extends('layouts.app')

@section('title', 'Sales Activity')
@section('page-title', 'Sales Activity')
@section('page-subtitle', 'Kelola aktivitas harian dan follow up dengan customer')

@section('content')
<div class="row g-3">
    {{-- LEFT: Activity Timeline --}}
    <div class="col-lg-8">
        {{-- Action Buttons --}}
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="fas fa-plus"></i> Add Activity
            </button>
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="fas fa-phone" style="color:#059669"></i> Log Call
            </button>
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="fas fa-building" style="color:#7c3aed"></i> Log Visit
            </button>
            <button class="btn btn-outline-secondary d-flex align-items-center gap-2">
                <i class="fas fa-envelope" style="color:#d97706"></i> Send Email
            </button>
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('sales.activity') }}">
            <div class="card mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-auto">
                            <input type="date" name="date" value="{{ $date ?? '' }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                onclick="document.querySelector('[name=date]').value=''; this.closest('form').submit()">
                                Semua Tanggal
                            </button>
                        </div>
                        <div class="col-auto">
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="all">All Sales</option>
                                @foreach($salesUsers as $su)
                                <option value="{{ $su->id }}" @selected($salesId==$su->id)>{{ $su->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="activity_type" class="form-select form-select-sm">
                                <option value="all">All Type</option>
                                @foreach(['Call','Visit','Email','Note','Others'] as $t)
                                <option value="{{ $t }}" @selected($type==$t)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        {{-- Timeline --}}
        <div class="card">
            <div class="card-header">Activity Timeline</div>
            <div class="card-body p-3">
                <div class="activity-timeline">
                    @forelse($activities as $act)
                    <div class="activity-item">
                        <div class="activity-time">
                            {{ $act->activity_at->format('H:i') }}<br>
                            <span style="font-size:.65rem;color:var(--text-muted);font-weight:400">{{ $act->activity_at->format('d M Y') }}</span>
                        </div>
                        <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#dbeafe' : ($act->type === 'Email' ? '#fef3c7' : '#f3f4f6')) }}">
                            <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#2563eb' : ($act->type === 'Email' ? '#d97706' : '#6b7280')) }};font-size:.8rem"></i>
                        </div>
                        <div class="flex-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="activity-subject">{{ $act->subject }}</span>
                                    <span class="ms-2" style="background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:600">{{ $act->type }}</span>
                                </div>
                                <span class="badge-{{ strtolower($act->status) }}">{{ $act->status }}</span>
                            </div>

                            {{-- Client info --}}
                            @php $client = $act->lead?->company_name ?? $act->customer?->company_name ?? null; @endphp
                            @if($client)
                            <div class="mt-1" style="font-size:.75rem;color:#2563eb;font-weight:600">
                                <i class="fas fa-building me-1" style="font-size:.65rem"></i>{{ $client }}
                                @if($act->lead)
                                <span style="background:#dbeafe;color:#1d4ed8;padding:1px 6px;border-radius:10px;font-size:.65rem;margin-left:4px">
                                    {{ $act->lead->pipeline_stage }}
                                </span>
                                @endif
                            </div>
                            @endif

                            @if($act->description)
                            <div class="activity-desc mt-1">{{ $act->description }}</div>
                            @endif

                            {{-- Foto kunjungan --}}
                            @if($act->photo && $act->type === 'Visit')
                            <div class="mt-2">
                                <a href="{{ asset('storage/' . $act->photo) }}" target="_blank">
                                    <img src="{{ asset('storage/' . $act->photo) }}" alt="Foto Kunjungan"
                                        style="max-height:140px;max-width:100%;border-radius:8px;border:1px solid #e5e7eb;cursor:zoom-in">
                                </a>
                                <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px">
                                    <i class="fas fa-image me-1"></i>Foto kunjungan · <a href="{{ asset('storage/' . $act->photo) }}" target="_blank" style="color:var(--primary)">Lihat penuh</a>
                                </div>
                            </div>
                            @endif

                            <div class="activity-meta d-flex align-items-center gap-3 mt-1">
                                <span><i class="fas fa-user me-1"></i>{{ $act->salesUser?->name ?? 'Unknown' }}</span>
                                @if($act->next_follow_up)
                                <span style="color:#d97706"><i class="fas fa-calendar-check me-1"></i>Follow up: {{ $act->next_follow_up->format('d M Y') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2"></i>
                        <p>Tidak ada aktivitas pada tanggal ini.</p>
                    </div>
                    @endforelse
                </div>

                @if($activities->hasPages())
                <div class="mt-3">{{ $activities->links() }}</div>
                @endif
            </div>
        </div>

    </div>

    {{-- RIGHT Sidebar --}}
    <div class="col-lg-4">
        {{-- Today Reminder --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Today Reminder</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)">View All</a>
            </div>
            <div class="card-body p-3">
                @if($overdueActivities->count())
                <div style="font-size:.72rem;font-weight:700;color:#dc2626;margin-bottom:6px">OVERDUE ({{ $overdueActivities->count() }})</div>
                @foreach($overdueActivities->take(2) as $act)
                <div class="reminder-item">
                    <div class="reminder-time" style="color:#dc2626">{{ $act->activity_at->format('H:i') }}</div>
                    <div style="flex:1">
                        <div style="font-size:.78rem;font-weight:600">{{ $act->subject }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">{{ $act->description }}</div>
                    </div>
                    <span class="badge-overdue">Overdue</span>
                </div>
                @endforeach
                @endif

                @if($todayReminders->count())
                <div style="font-size:.72rem;font-weight:700;color:#2563eb;margin:8px 0 6px">TODAY ({{ $todayReminders->count() }})</div>
                @foreach($todayReminders->take(4) as $act)
                <div class="reminder-item">
                    <div class="reminder-time">{{ $act->activity_at->format('H:i') }}</div>
                    <div style="flex:1">
                        <div style="font-size:.78rem;font-weight:600">{{ $act->subject }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">{{ Str::limit($act->description, 40) }}</div>
                    </div>
                    <span class="badge-today">Today</span>
                </div>
                @endforeach
                @endif

                @if($upcomingActivities->count())
                <div style="font-size:.72rem;font-weight:700;color:#d97706;margin:8px 0 6px">UPCOMING ({{ $upcomingActivities->count() }})</div>
                @foreach($upcomingActivities->take(2) as $act)
                <div class="reminder-item">
                    <div class="reminder-time">{{ $act->activity_at->format('d M') }}</div>
                    <div style="flex:1">
                        <div style="font-size:.78rem;font-weight:600">{{ $act->subject }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">{{ Str::limit($act->description, 40) }}</div>
                    </div>
                    <span class="badge-tomorrow">Tomorrow</span>
                </div>
                @endforeach
                @endif
            </div>
        </div>

        {{-- Pipeline Summary --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Pipeline (Leads)</span>
                <a href="{{ route('leads.index') }}" style="font-size:.75rem;color:var(--primary)">Lihat Semua</a>
            </div>
            <div class="card-body p-3">
                @foreach($pipelineSummary as $stage => $data)
                @php
                $colors = ['Identifying'=>'#2563eb','Approaching'=>'#d97706','Follow Up'=>'#7c3aed','Closing'=>'#059669'];
                @endphp
                <div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid #f3f4f6">
                    <span style="font-size:.8rem;color:{{ $colors[$stage] ?? '#333' }};font-weight:600">{{ $stage }}</span>
                    <span style="font-size:.75rem;color:var(--text-muted)">{{ $data['count'] }} Leads</span>
                    <span style="font-size:.75rem;font-weight:600">{{ idrm($data['value']) }}</span>
                </div>
                @endforeach
                <div class="d-flex justify-content-between pt-2">
                    <strong style="font-size:.82rem">Total Pipeline Value</strong>
                    <strong style="font-size:.82rem;color:var(--primary)">{{ idrm(collect($pipelineSummary)->sum(fn($d) => $d['value'])) }}</strong>
                </div>
            </div>
        </div>

        {{-- Update Pipeline Stage --}}
        <div class="card mt-3">
            <div class="card-header">Update Pipeline Stage</div>
            <div class="card-body p-3">
                <p style="font-size:.75rem;color:var(--text-muted);margin-bottom:10px">Pilih lead dan update stage-nya langsung dari sini.</p>
                <div class="mb-2">
                    <label class="form-label" style="font-size:.78rem">Lead</label>
                    <select id="stageLeadSelect" class="form-select form-select-sm">
                        <option value="">-- Pilih Lead --</option>
                        @foreach(\App\Models\Lead::whereNotIn('pipeline_stage',['Won','Lost'])->orderBy('company_name')->get() as $l)
                        <option value="{{ $l->id }}" data-stage="{{ $l->pipeline_stage }}">{{ $l->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-size:.78rem">Pipeline Stage</label>
                    <select id="stageSelect" class="form-select form-select-sm">
                        @foreach(['Identifying','Approaching','Follow Up','Closing','Won'] as $s)
                        <option value="{{ $s }}">{{ $s }}</option>
                        @endforeach
                    </select>
                </div>
                <button onclick="updateLeadStage()" class="btn btn-sm btn-primary w-100">
                    <i class="fas fa-arrow-right me-1"></i> Update Stage
                </button>
                <div id="stageUpdateMsg" class="mt-2" style="font-size:.75rem;display:none"></div>
            </div>
        </div>
    </div>
</div>

{{-- Add Activity Modal --}}
<div class="modal fade" id="addActivityModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Add Activity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="{{ route('sales.activity.store') }}" enctype="multipart/form-data" id="activityForm">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Client / Company <span class="text-danger">*</span></label>
                        <select name="lead_id" class="form-select" id="actLeadSelect" onchange="onLeadChange(this)">
                            <option value="">Pilih atau cari client</option>
                            @foreach(\App\Models\Lead::orderBy('company_name')->get() as $lead)
                            <option value="{{ $lead->id }}" data-stage="{{ $lead->pipeline_stage }}">{{ $lead->company_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Pipeline Stage — muncul setelah lead dipilih --}}
                    <div class="mb-3" id="stageWrap" style="display:none">
                        <label class="form-label">Update Pipeline Stage</label>
                        <select name="pipeline_stage" class="form-select" id="actStageSelect">
                            @foreach(['Identifying','Approaching','Follow Up','Closing','Won','Lost'] as $s)
                            <option value="{{ $s }}">{{ $s }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Opsional — biarkan jika tidak ingin mengubah stage</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Activity Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2 flex-wrap">
                            @foreach(['Call','Visit','Email','Note','Others'] as $t)
                            <div>
                                <input type="radio" class="btn-check" name="type" id="type_{{ $t }}" value="{{ $t }}"
                                    {{ $t === 'Call' ? 'checked' : '' }} onchange="onTypeChange('{{ $t }}')">
                                <label class="btn btn-sm btn-outline-secondary" for="type_{{ $t }}">{{ $t }}</label>
                            </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Foto Upload --}}
                    <div class="mb-3" id="photoWrap" style="display:none">
                        <label class="form-label">Foto Kunjungan</label>
                        <input type="file" name="photo" id="photoFileInput" class="form-control"
                            accept="image/jpg,image/jpeg,image/png,image/webp"
                            onchange="previewPhoto(this)">
                        <div class="form-text">JPG/PNG/WebP, maks 3MB</div>
                        <div id="photoPreview" class="mt-2" style="display:none">
                            <img id="previewImg" src="" alt="Preview"
                                style="max-width:100%;max-height:180px;border-radius:8px;border:1px solid #e5e7eb">
                            <div style="font-size:.7rem;color:#059669;margin-top:4px">
                                <i class="fas fa-check-circle me-1"></i>Foto siap diupload
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Date &amp; Time <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="activity_at" class="form-control" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject <span class="text-danger">*</span></label>
                        <input type="text" name="subject" class="form-control" placeholder="Judul aktivitas..." required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Tulis catatan aktivitas..."></textarea>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Next Follow Up</label>
                            <input type="date" name="next_follow_up" class="form-control">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="Done">Done</option>
                                <option value="Planned">Planned</option>
                            </select>
                        </div>
                    </div>
                    <input type="hidden" name="pipeline_stage_hidden" value="">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
    function onTypeChange(type) {
        document.getElementById('photoWrap').style.display = type === 'Visit' ? 'block' : 'none';
        if (type !== 'Visit') {
            document.getElementById('photoPreview').style.display = 'none';
        }
    }

    function onLeadChange(sel) {
        const wrap = document.getElementById('stageWrap');
        const stSel = document.getElementById('actStageSelect');
        if (sel.value) {
            wrap.style.display = 'block';
            const stage = sel.options[sel.selectedIndex].dataset.stage;
            if (stage) {
                for (let o of stSel.options) {
                    o.selected = o.value === stage;
                }
            }
        } else {
            wrap.style.display = 'none';
        }
    }

    function previewPhoto(input) {
        const preview = document.getElementById('photoPreview');
        const img = document.getElementById('previewImg');
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = e => {
                img.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // Update pipeline stage dari sidebar
    function updateLeadStage() {
        const leadId = document.getElementById('stageLeadSelect').value;
        const stage = document.getElementById('stageSelect').value;
        const msg = document.getElementById('stageUpdateMsg');

        if (!leadId) {
            msg.style.display = 'block';
            msg.style.color = '#dc2626';
            msg.textContent = 'Pilih lead terlebih dahulu.';
            return;
        }

        msg.style.display = 'block';
        msg.style.color = '#9ca3af';
        msg.textContent = 'Menyimpan...';

        fetch(`/leads/${leadId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    _method: 'PUT',
                    pipeline_stage: stage
                })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    msg.style.color = '#059669';
                    msg.textContent = `✓ Stage berhasil diupdate ke "${stage}"`;
                    // Update data-stage di dropdown
                    const opt = document.querySelector(`#stageLeadSelect option[value="${leadId}"]`);
                    if (opt) opt.dataset.stage = stage;
                } else {
                    throw new Error('Gagal');
                }
            })
            .catch(() => {
                msg.style.color = '#dc2626';
                msg.textContent = 'Gagal mengupdate stage. Coba lagi.';
            });
    }

    // Sync stageSelect saat pilih lead di sidebar
    document.addEventListener('DOMContentLoaded', function() {
        const leadSel = document.getElementById('stageLeadSelect');
        const stageSel = document.getElementById('stageSelect');
        if (leadSel && stageSel) {
            leadSel.addEventListener('change', function() {
                const stage = this.options[this.selectedIndex]?.dataset?.stage;
                if (stage) {
                    for (let o of stageSel.options) o.selected = o.value === stage;
                }
            });
        }
    });
</script>
@endpush
@endsection