@extends('layouts.app')

@section('title', 'Tasks & Reminder')

@push('styles')
<style>
    .kpi-card { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:18px 20px; }
    .kpi-icon { width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0; }
    .kpi-value { font-size:22px;font-weight:700;color:#0f1d35; }
    .kpi-label { font-size:12px;color:#6b7280; }

    .filter-pill { padding:6px 16px;border-radius:20px;border:1px solid #e5e7eb;font-size:12px;font-weight:500;cursor:pointer;background:#fff;color:#6b7280;text-decoration:none;transition:.15s; }
    .filter-pill.active { background:#0f1d35;color:#fff;border-color:#0f1d35; }
    .filter-pill:hover:not(.active) { background:#f9fafb;color:#374151; }

    .task-card { background:#fff;border-radius:10px;border:1px solid #f0f0f0;padding:16px 18px;margin-bottom:10px;transition:.15s; }
    .task-card:hover { box-shadow:0 2px 8px rgba(0,0,0,.08); }
    .task-card.overdue { border-left:3px solid #ef4444; }
    .task-card.done    { opacity:.65; }
    .task-card.today   { border-left:3px solid #3b82f6; }

    .badge-type { font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600; }
    .type-call  { background:#dcfce7;color:#16a34a; }
    .type-visit { background:#dbeafe;color:#2563eb; }
    .type-email { background:#fef9c3;color:#854d0e; }
    .type-note  { background:#f3e8ff;color:#7c3aed; }
    .type-task  { background:#fee2e2;color:#dc2626; }

    .badge-status { font-size:11px;padding:3px 10px;border-radius:20px;font-weight:600; }
    .status-done    { background:#dcfce7;color:#16a34a; }
    .status-planned { background:#dbeafe;color:#2563eb; }
    .status-pending { background:#fef9c3;color:#854d0e; }
    .status-overdue { background:#fee2e2;color:#dc2626; }

    .complete-btn { width:22px;height:22px;border-radius:50%;border:2px solid #d1d5db;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:.15s; }
    .complete-btn:hover { border-color:#10b981;background:#f0fdf4; }
    .complete-btn.done { background:#10b981;border-color:#10b981; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f1d35">Tasks & Reminder</h4>
        <p class="text-muted mb-0" style="font-size:13px">Daftar tugas, follow up, dan pengingat sales</p>
    </div>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTaskModal" style="border-radius:8px;font-size:13px">
        <i class="fas fa-plus me-1"></i> Add Task
    </button>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#eff6ff"><i class="fas fa-calendar-day" style="color:#3b82f6"></i></div>
            <div>
                <div class="kpi-label">Today</div>
                <div class="kpi-value">{{ $totalToday }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#fee2e2"><i class="fas fa-exclamation-circle" style="color:#dc2626"></i></div>
            <div>
                <div class="kpi-label">Overdue</div>
                <div class="kpi-value" style="color:#dc2626">{{ $totalOverdue }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#fff7ed"><i class="fas fa-clock" style="color:#f97316"></i></div>
            <div>
                <div class="kpi-label">Upcoming (7 Days)</div>
                <div class="kpi-value">{{ $totalUpcoming }}</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:#f0fdf4"><i class="fas fa-check-circle" style="color:#10b981"></i></div>
            <div>
                <div class="kpi-label">Done</div>
                <div class="kpi-value" style="color:#10b981">{{ $totalDone }}</div>
            </div>
        </div>
    </div>
</div>

<!-- Filter & Search -->
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <a href="{{ route('tasks.index') }}" class="filter-pill {{ $filter === 'all' ? 'active' : '' }}">All</a>
        <a href="{{ route('tasks.index', ['filter'=>'today']) }}" class="filter-pill {{ $filter === 'today' ? 'active' : '' }}">Today</a>
        <a href="{{ route('tasks.index', ['filter'=>'overdue']) }}" class="filter-pill {{ $filter === 'overdue' ? 'active' : '' }}">Overdue</a>
        <a href="{{ route('tasks.index', ['filter'=>'upcoming']) }}" class="filter-pill {{ $filter === 'upcoming' ? 'active' : '' }}">Upcoming</a>

        <select class="form-select form-select-sm" style="width:130px;font-size:12px;border-radius:8px" onchange="location.href=this.value">
            <option value="{{ route('tasks.index', array_merge(request()->query(), ['type'=>''])) }}">All Type</option>
            @foreach(['Call'=>'Call','Visit'=>'Visit','Email'=>'Email','Note'=>'Note','Others'=>'Task'] as $value => $label)
            <option value="{{ route('tasks.index', array_merge(request()->query(), ['type'=>$value])) }}" {{ $type === $value ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>

        <select class="form-select form-select-sm" style="width:160px;font-size:12px;border-radius:8px" onchange="location.href=this.value">
            <option value="{{ route('tasks.index', array_merge(request()->query(), ['sales_user_id'=>''])) }}">All Sales</option>
            @foreach($salesUsers as $su)
            <option value="{{ route('tasks.index', array_merge(request()->query(), ['sales_user_id'=>$su->id])) }}" {{ $salesId == $su->id ? 'selected' : '' }}>{{ $su->name }}</option>
            @endforeach
        </select>
    </div>
    <div style="font-size:13px;color:#6b7280">
        Showing {{ $tasks->firstItem() }}–{{ $tasks->lastItem() }} of {{ $tasks->total() }} tasks
    </div>
</div>

<!-- Task List -->
@forelse($tasks as $task)
<div class="task-card {{ $task->status === 'Overdue' ? 'overdue' : ($task->status === 'Done' ? 'done' : (date('Y-m-d') === $task->activity_at->format('Y-m-d') ? 'today' : '')) }}">
    <div class="d-flex align-items-start gap-3">
        <!-- Complete checkbox -->
        <form method="POST" action="{{ route('tasks.update', $task->id) }}" class="d-flex align-items-center" style="margin-top:2px">
            @csrf @method('PATCH')
            <input type="hidden" name="status" value="{{ $task->status === 'Done' ? 'Planned' : 'Done' }}">
            <input type="hidden" name="subject" value="{{ $task->subject }}">
            <button type="submit" class="complete-btn {{ $task->status === 'Done' ? 'done' : '' }}" style="background:{{ $task->status === 'Done' ? '#10b981' : 'transparent' }};border:none;padding:0">
                @if($task->status === 'Done')
                    <i class="fas fa-check" style="font-size:10px;color:#fff"></i>
                @endif
            </button>
        </form>

        <!-- Content -->
        <div style="flex:1;min-width:0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="fw-600" style="font-size:14px;color:#0f1d35;font-weight:600;{{ $task->status === 'Done' ? 'text-decoration:line-through' : '' }}">{{ $task->subject }}</span>
                <span class="badge-type type-{{ strtolower($task->type) }}">{{ $task->type }}</span>
                <span class="badge-status status-{{ strtolower($task->status) }}">{{ $task->status }}</span>
            </div>
            <div class="d-flex align-items-center gap-3 mt-1 flex-wrap">
                <span style="font-size:12px;color:#6b7280">
                    <i class="fas fa-building me-1" style="font-size:10px"></i>
                    {{ $task->customer?->company_name ?? $task->lead?->company_name ?? 'No Customer' }}
                </span>
                <span style="font-size:12px;color:#6b7280">
                    <i class="fas fa-user me-1" style="font-size:10px"></i>{{ $task->salesUser?->name ?? '-' }}
                </span>
                <span style="font-size:12px;color:{{ $task->status === 'Overdue' ? '#dc2626' : '#6b7280' }}">
                    <i class="fas fa-clock me-1" style="font-size:10px"></i>{{ $task->activity_at->format('d M Y · H:i') }}
                </span>
                @if($task->description)
                <span style="font-size:12px;color:#9ca3af"><i class="fas fa-align-left me-1" style="font-size:10px"></i>{{ Str::limit($task->description, 50) }}</span>
                @endif
            </div>
        </div>

        <!-- Actions -->
        <div class="d-flex gap-1">
            <button class="btn btn-sm" style="padding:4px 8px;border:1px solid #e5e7eb;border-radius:6px;font-size:11px;color:#6b7280"
                onclick="openEditTask({{ $task->id }}, @js($task->subject), '{{ $task->status }}', '{{ $task->activity_at->format('Y-m-d\TH:i') }}', @js($task->description))">
                <i class="fas fa-edit"></i>
            </button>
            <form method="POST" action="{{ route('tasks.destroy', $task->id) }}"
                onsubmit="return confirm('Apakah Anda yakin ingin menghapus task ini? Tindakan ini tidak dapat dibatalkan.')">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-sm" style="padding:4px 8px;border:1px solid #fecaca;border-radius:6px;font-size:11px;color:#dc2626">
                    <i class="fas fa-trash"></i>
                </button>
            </form>
        </div>
    </div>
</div>
@empty
<div class="text-center py-5" style="color:#9ca3af">
    <i class="fas fa-tasks" style="font-size:32px;display:block;margin-bottom:12px"></i>
    <div style="font-size:14px;font-weight:600">Tidak ada task ditemukan</div>
    <div style="font-size:13px">Tambah task baru atau ubah filter</div>
</div>
@endforelse

<!-- Pagination -->
<div class="d-flex justify-content-center mt-4">
    {{ $tasks->withQueryString()->links('pagination::bootstrap-5') }}
</div>

<!-- Add Task Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Task / Reminder</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('tasks.store') }}" method="POST" id="addTaskForm">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Jenis</label>
                            <select name="type" class="form-select" required>
                                <option value="Call">Call</option><option value="Visit">Visit</option><option value="Email">Email</option><option value="Note">Note</option><option value="Others">Task</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option>Planned</option><option>Pending</option><option>Done</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject / Judul</label>
                            <input type="text" name="subject" class="form-control" required placeholder="Contoh: Follow up PT. Maju Bersama">
                        </div>
                        {{-- Linked to: Lead atau Customer --}}
                        <div class="col-12">
                            <label class="form-label">Terkait Customer Existing</label>
                            <select name="customer_id" class="form-select">
                                <option value="">- Pilih customer (opsional) -</option>
                                @foreach($customers as $cust)
                                <option value="{{ $cust->id }}">{{ $cust->company_name }} <span style="color:#9ca3af">— {{ $cust->salesUser?->name }}</span></option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal & Waktu</label>
                            <input type="datetime-local" name="activity_at" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            @include('components.sales-pic-field')
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan (Opsional)</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Detail task..."></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Next Follow Up (Opsional)</label>
                            <input type="date" name="next_follow_up" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm">Simpan Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Edit Task</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editTaskForm" method="POST">
                @csrf @method('PATCH')
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" id="editTaskSubject" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" id="editTaskStatus" class="form-select">
                            <option value="Planned">Planned</option>
                            <option value="Pending">Pending</option>
                            <option value="Done">Done</option>
                            <option value="Overdue">Overdue</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal & Waktu</label>
                        <input type="datetime-local" name="activity_at" id="editTaskActivityAt" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" id="editTaskDescription" class="form-control" rows="2"></textarea>
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

@push('scripts')
<script>
function openEditTask(id, subject, status, activityAt, description) {
    document.getElementById('editTaskForm').action = `/tasks/${id}`;
    document.getElementById('editTaskSubject').value = subject || '';
    document.getElementById('editTaskStatus').value = status || 'Planned';
    document.getElementById('editTaskActivityAt').value = activityAt || '';
    document.getElementById('editTaskDescription').value = description || '';
    new bootstrap.Modal(document.getElementById('editTaskModal')).show();
}
// Prevent data loss
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('addTaskForm');
    const modalEl = document.getElementById('addTaskModal');
    if (!form || !modalEl) return;
    modalEl.addEventListener('hide.bs.modal', function(e) {
        const inputs = form.querySelectorAll('input[type=text],textarea');
        let hasData = false;
        inputs.forEach(i => { if (i.value.trim()) hasData = true; });
        if (hasData && !confirm('Data yang sudah diisi akan hilang. Tutup form?')) {
            e.preventDefault();
        }
    });
});
</script>
@endpush

@endsection
