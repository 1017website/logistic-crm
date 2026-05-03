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
                            <input type="date" name="date" value="{{ $date }}" class="form-control form-control-sm">
                        </div>
                        <div class="col-auto">
                            <select name="user_id" class="form-select form-select-sm">
                                <option value="all">All Sales</option>
                                @foreach($salesUsers as $su)
                                    <option value="{{ $su->id }}" @selected($salesId == $su->id)>{{ $su->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <select name="activity_type" class="form-select form-select-sm">
                                <option value="all">All Type</option>
                                @foreach(['Call','Visit','Email','Note','Others'] as $t)
                                    <option value="{{ $t }}" @selected($type == $t)>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-auto">
                            <input type="text" name="search" class="form-control form-control-sm" placeholder="Search activity / client..." value="{{ request('search') }}">
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
                                    <span class="ms-2 badge-{{ strtolower($act->type) }}" style="background:#f3f4f6;color:#374151;padding:2px 8px;border-radius:20px;font-size:.68rem;font-weight:600">{{ $act->type }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="badge-{{ strtolower($act->status) }}">{{ $act->status }}</span>
                                    <button class="btn btn-sm p-0" style="color:var(--text-muted)"><i class="fas fa-ellipsis-v"></i></button>
                                </div>
                            </div>
                            <div class="activity-desc mt-1">{{ $act->description }}</div>
                            <div class="activity-meta d-flex align-items-center gap-3 mt-1">
                                <span><i class="fas fa-user me-1"></i>PIC: {{ $act->salesUser?->name }}</span>
                                <span><i class="fas fa-calendar me-1"></i>{{ $act->activity_at->format('d M Y, H:i') }}</span>
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

                @if($activities->count() >= 6)
                <div class="text-center mt-3">
                    <button class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-chevron-down me-1"></i>Load More Activity
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- Recent Notes --}}
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Notes</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)">View All</a>
            </div>
            <div class="card-body p-3">
                <div class="row g-2">
                    @foreach($recentNotes->take(4) as $note)
                    <div class="col-6">
                        <div class="p-3 rounded" style="background:#f9fafb;border:1px solid var(--border-color)">
                            <div style="font-size:.8rem;font-weight:600;color:#111">{{ $note->lead?->company_name ?? $note->customer?->company_name ?? 'No Client' }}</div>
                            <div style="font-size:.7rem;color:var(--text-muted);margin:2px 0">{{ $note->activity_at->format('d M Y · H:i') }}</div>
                            <div style="font-size:.75rem;color:#374151;margin-top:4px">{{ Str::limit($note->description, 80) }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
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
                <a href="{{ route('pipeline.index') }}" style="font-size:.75rem;color:var(--primary)">View Pipeline</a>
            </div>
            <div class="card-body p-3">
                @foreach($pipelineSummary as $stage => $data)
                @php
                    $colors = ['Identifying'=>'#2563eb','Approaching'=>'#d97706','Follow Up'=>'#7c3aed','Closing'=>'#059669'];
                @endphp
                <div class="d-flex align-items-center justify-content-between py-2" style="border-bottom:1px solid #f3f4f6">
                    <a href="{{ route('leads.index', ['stage'=>$stage]) }}" style="font-size:.8rem;color:{{ $colors[$stage] ?? '#333' }};font-weight:600;text-decoration:none">{{ $stage }}</a>
                    <span style="font-size:.75rem;color:var(--text-muted)">{{ $data['count'] }} Leads</span>
                    <span style="font-size:.75rem;font-weight:600">{{ idrm($data['value']) }}</span>
                </div>
                @endforeach
                <div class="d-flex justify-content-between pt-2">
                    <strong style="font-size:.82rem">Total Pipeline Value</strong>
                    <strong style="font-size:.82rem;color:var(--primary)">Rp {{ number_format(collect($pipelineSummary)->sum(fn($d) => $d['value'])/1000000000,2) }}M</strong>
                </div>
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
            <form method="POST" action="{{ route('sales.activity.store') }}">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Client / Company <span class="text-danger">*</span></label>
                        <select name="lead_id" class="form-select">
                            <option value="">Pilih atau cari client</option>
                            @foreach(\App\Models\Lead::all() as $lead)
                            <option value="{{ $lead->id }}">{{ $lead->company_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Activity Type <span class="text-danger">*</span></label>
                        <div class="d-flex gap-2">
                            @foreach(['Call','Visit','Email','Note','Others'] as $t)
                            <div>
                                <input type="radio" class="btn-check" name="type" id="type_{{ $t }}" value="{{ $t }}" {{ $t === 'Call' ? 'checked' : '' }}>
                                <label class="btn btn-sm btn-outline-secondary" for="type_{{ $t }}">{{ $t }}</label>
                            </div>
                            @endforeach
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
                    <input type="hidden" name="user_id" value="1">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sm btn-primary">Save Activity</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
