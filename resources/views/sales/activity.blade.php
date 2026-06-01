@extends('layouts.app')

@section('title', 'Sales Activity')
@section('page-title', 'Sales Activity')
@section('page-subtitle', 'Kelola aktivitas harian dan follow up dengan customer')

@push('styles')
<style>
/* Pipeline (Leads) summary — grid agar kolom sejajar antar baris */
.pipeline-row{
    display:grid;
    grid-template-columns:10px 1fr auto auto;
    align-items:center;
    column-gap:10px;
    padding:8px 0;
    border-bottom:1px solid #f3f4f6;
}
.pipeline-row:last-child{border-bottom:none;}
.pipeline-dot{width:8px;height:8px;border-radius:50%;display:inline-block;}
.pipeline-name{font-size:.8rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.pipeline-count{font-size:.72rem;color:var(--text-muted);text-align:right;min-width:62px;white-space:nowrap;}
.pipeline-value{font-size:.75rem;font-weight:600;text-align:right;min-width:78px;white-space:nowrap;}
.pipeline-total{margin-top:4px;padding-top:10px;border-top:2px solid #e5e7eb;}
.pipeline-total .pipeline-name strong,
.pipeline-total .pipeline-value strong{font-size:.82rem;}
</style>
@endpush

@section('content')
<div class="row g-3">
    {{-- LEFT: Activity Timeline --}}
    <div class="col-lg-8">
        {{-- Action Buttons --}}
        <div class="d-flex gap-2 mb-3">
            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                <i class="fas fa-plus"></i> Add Activity
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
                        <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#e5e5e5' : ($act->type === 'Email' ? '#fef3c7' : '#f3f4f6')) }}">
                            <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#111111' : ($act->type === 'Email' ? '#d97706' : '#6b7280')) }};font-size:.8rem"></i>
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
                            <div class="mt-1" style="font-size:.75rem;color:#111111;font-weight:600">
                                <i class="fas fa-building me-1" style="font-size:.65rem"></i>{{ $client }}
                                @php $actStage = $act->pipeline_stage ?? $act->lead?->pipeline_stage; @endphp
                                @if($actStage)
                                <span style="background:#e5e5e5;color:#000000;padding:1px 6px;border-radius:10px;font-size:.65rem;margin-left:4px">
                                    {{ $actStage === 'Won' ? 'Won/Closing' : $actStage }}
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
                                        style="height:90px;width:120px;object-fit:cover;border-radius:8px;border:1px solid #e5e7eb;cursor:zoom-in">
                                </a>
                                <div style="font-size:.68rem;color:var(--text-muted);margin-top:3px">
                                    <i class="fas fa-image me-1"></i>Foto kunjungan · <a href="{{ asset('storage/' . $act->photo) }}" target="_blank" style="color:var(--primary)">Lihat penuh</a>
                                </div>
                            </div>
                            @endif

                            <div class="activity-meta d-flex align-items-center gap-3 mt-1">
                                <span><i class="fas fa-user me-1"></i>{{ $act->salesUser?->name ?? $act->user?->name ?? '-' }}</span>
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
                <div style="font-size:.72rem;font-weight:700;color:#111111;margin:8px 0 6px">TODAY ({{ $todayReminders->count() }})</div>
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
                @php
                $pipeColors = [
                    'Identifying' => '#111111',
                    'Approaching' => '#d97706',
                    'Follow Up'   => '#7c3aed',
                    'Won/Closing' => '#059669',
                    'Maintaining' => '#6366f1',
                ];
                @endphp
                @foreach($pipelineSummary as $stage => $data)
                <div class="pipeline-row">
                    <span class="pipeline-dot" style="background:{{ $pipeColors[$stage] ?? '#9ca3af' }}"></span>
                    <span class="pipeline-name" style="color:{{ $pipeColors[$stage] ?? '#333' }}">{{ $stage }}</span>
                    <span class="pipeline-count">{{ $data['count'] }} Leads</span>
                    <span class="pipeline-value">{{ idrm($data['value']) }}</span>
                </div>
                @endforeach
                <div class="pipeline-row pipeline-total">
                    <span class="pipeline-dot" style="background:transparent"></span>
                    <span class="pipeline-name"><strong>Total Pipeline</strong></span>
                    <span class="pipeline-count"></span>
                    <span class="pipeline-value"><strong style="color:var(--primary)">{{ idrm(collect($pipelineSummary)->sum(fn($d) => $d['value'])) }}</strong></span>
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
                        @foreach(\App\Models\Lead::whereNotIn('pipeline_stage',['Won'])->orderBy('company_name')->get() as $l)
                        <option value="{{ $l->id }}" data-stage="{{ $l->pipeline_stage }}">{{ $l->company_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label" style="font-size:.78rem">Pipeline Stage</label>
                    <select id="stageSelect" class="form-select form-select-sm">
                        @foreach(['Identifying'=>'Identifying','Approaching'=>'Approaching','Follow Up'=>'Follow Up','Won'=>'Won/Closing','Maintaining'=>'Maintaining'] as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
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
@include('components.shared-activity-modal', ['activityModalTitle' => 'Add Activity'])

@endsection