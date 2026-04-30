@extends('layouts.app')

@section('title', 'Calendar')

@push('styles')
<style>
    .cal-wrap { background:#fff; border-radius:12px; border:1px solid #f0f0f0; padding:24px; }
    .cal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; }
    .cal-nav-btn { width:34px;height:34px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer; }
    .cal-nav-btn:hover { background:#f9fafb; }
    .cal-title { font-size:15px;font-weight:700;color:#0f1d35; }

    .cal-grid { width:100%; border-collapse:collapse; }
    .cal-grid th { font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;padding:8px 4px;text-align:center;letter-spacing:0.5px; }
    .cal-grid td { width:14.28%;height:90px;border:1px solid #f0f0f0;padding:6px;vertical-align:top;cursor:pointer; }
    .cal-grid td:hover { background:#f9fafb; }
    .cal-grid td.today { background:#eff6ff; }
    .cal-grid td.today .day-num { background:#3b82f6;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center; }
    .cal-grid td.other-month { opacity:0.35; }
    .day-num { font-size:13px;font-weight:600;color:#374151;width:24px;height:24px;display:flex;align-items:center;justify-content:center; }
    .day-event { font-size:10px;font-weight:600;padding:2px 6px;border-radius:4px;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer; }
    .event-call  { background:#dcfce7;color:#16a34a; }
    .event-visit { background:#dbeafe;color:#2563eb; }
    .event-email { background:#fef9c3;color:#854d0e; }
    .event-note  { background:#f3e8ff;color:#7c3aed; }
    .event-task  { background:#fee2e2;color:#dc2626; }
    .more-events { font-size:10px;color:#6b7280;margin-top:2px;cursor:pointer; }

    .sidebar-card { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:18px; }
    .sidebar-card-title { font-size:13px;font-weight:700;color:#0f1d35;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between; }
    .event-item { display:flex;align-items:flex-start;gap:10px;padding:10px 0;border-bottom:1px solid #f9fafb; }
    .event-item:last-child { border-bottom:none; }
    .event-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px; }
    .event-name { font-size:12px;font-weight:600;color:#0f1d35; }
    .event-meta { font-size:11px;color:#6b7280; }
    .event-time-badge { font-size:11px;font-weight:600;padding:2px 8px;border-radius:20px;flex-shrink:0; }

    .badge-overdue { background:#fee2e2;color:#dc2626; }
    .badge-planned { background:#dbeafe;color:#2563eb; }
    .badge-done    { background:#dcfce7;color:#16a34a; }

    .filter-type-btn { padding:5px 12px;border-radius:20px;border:1px solid #e5e7eb;font-size:12px;font-weight:500;cursor:pointer;background:#fff;color:#6b7280;transition:.15s; }
    .filter-type-btn.active { background:#3b82f6;color:#fff;border-color:#3b82f6; }
    .filter-type-btn:hover:not(.active) { background:#f9fafb; }
</style>
@endpush

@section('content')
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f1d35">Calendar</h4>
        <p class="text-muted mb-0" style="font-size:13px">Jadwal activity, follow up, dan meeting sales</p>
    </div>
    <div class="d-flex gap-2">
        <div class="d-flex gap-1">
            <button class="filter-type-btn active" onclick="filterType(this,'all')">All</button>
            <button class="filter-type-btn" onclick="filterType(this,'Call')">Call</button>
            <button class="filter-type-btn" onclick="filterType(this,'Visit')">Visit</button>
            <button class="filter-type-btn" onclick="filterType(this,'Email')">Email</button>
            <button class="filter-type-btn" onclick="filterType(this,'Task')">Task</button>
        </div>
        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addEventModal" style="border-radius:8px;font-size:13px">
            <i class="fas fa-plus me-1"></i> Add Activity
        </button>
    </div>
</div>

<div class="row g-3">
    <!-- Calendar Grid -->
    <div class="col-md-9">
        <div class="cal-wrap">
            <div class="cal-header">
                <button class="cal-nav-btn" id="prevMonth"><i class="fas fa-chevron-left" style="font-size:12px"></i></button>
                <div class="cal-title" id="calTitle"></div>
                <button class="cal-nav-btn" id="nextMonth"><i class="fas fa-chevron-right" style="font-size:12px"></i></button>
                <button class="btn btn-light btn-sm ms-3" onclick="goToday()" style="font-size:12px;border-radius:6px;border:1px solid #e5e7eb">Today</button>
            </div>
            <table class="cal-grid" id="calendarTable">
                <thead>
                    <tr>
                        <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
                    </tr>
                </thead>
                <tbody id="calBody"></tbody>
            </table>
        </div>
    </div>

    <!-- Sidebar -->
    <div class="col-md-3 d-flex flex-column gap-3">
        <!-- Upcoming -->
        <div class="sidebar-card">
            <div class="sidebar-card-title">
                <span><i class="fas fa-clock text-primary me-2" style="font-size:12px"></i>Upcoming (7 Days)</span>
                <span class="badge" style="background:#eff6ff;color:#2563eb;font-size:11px">{{ $upcoming->count() }}</span>
            </div>
            @forelse($upcoming as $act)
            <div class="event-item">
                <div class="event-dot" style="background:{{ match($act->type) { 'Call'=>'#16a34a','Visit'=>'#2563eb','Email'=>'#ca8a04',default=>'#7c3aed' } }}"></div>
                <div style="flex:1;min-width:0">
                    <div class="event-name">{{ Str::limit($act->subject, 30) }}</div>
                    <div class="event-meta">{{ $act->customer?->company_name ?? $act->lead?->company_name ?? '-' }}</div>
                    <div class="event-meta">{{ $act->activity_at->format('d M · H:i') }}</div>
                </div>
                <span class="event-time-badge badge-planned">{{ $act->type }}</span>
            </div>
            @empty
            <div class="text-center py-3" style="color:#9ca3af;font-size:12px">Tidak ada jadwal mendatang</div>
            @endforelse
        </div>

        <!-- Overdue -->
        <div class="sidebar-card">
            <div class="sidebar-card-title">
                <span><i class="fas fa-exclamation-circle text-danger me-2" style="font-size:12px"></i>Overdue</span>
                <span class="badge" style="background:#fee2e2;color:#dc2626;font-size:11px">{{ $overdue->count() }}</span>
            </div>
            @forelse($overdue as $act)
            <div class="event-item">
                <div class="event-dot" style="background:#ef4444"></div>
                <div style="flex:1;min-width:0">
                    <div class="event-name">{{ Str::limit($act->subject, 30) }}</div>
                    <div class="event-meta" style="color:#dc2626">{{ $act->activity_at->format('d M · H:i') }}</div>
                </div>
                <span class="event-time-badge badge-overdue">Overdue</span>
            </div>
            @empty
            <div class="text-center py-3" style="color:#9ca3af;font-size:12px">Tidak ada overdue</div>
            @endforelse
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal fade" id="addEventModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Activity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('tasks.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Jenis Activity</label>
                        <select name="type" class="form-select" required>
                            <option>Call</option><option>Visit</option><option>Email</option><option>Note</option><option>Task</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" name="subject" class="form-control" required placeholder="Judul activity">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tanggal & Waktu</label>
                        <input type="datetime-local" name="activity_at" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Sales PIC</label>
                        <select name="sales_user_id" class="form-select" required>
                            @foreach(App\Models\SalesUser::all() as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option>Planned</option><option>Pending</option><option>Done</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Keterangan</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="Opsional"></textarea>
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
const events = @json($events);
let currentDate = new Date();
let activeTypeFilter = 'all';

function renderCalendar() {
    const year  = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('calTitle').textContent = monthNames[month] + ' ' + year;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();

    let html = '';
    let day = 1;
    let prevDays = new Date(year, month, 0).getDate();

    for (let r = 0; r < 6; r++) {
        html += '<tr>';
        for (let c = 0; c < 7; c++) {
            const cellIndex = r * 7 + c;
            let dateNum, isCurrentMonth = true;
            if (cellIndex < firstDay) {
                dateNum = prevDays - firstDay + cellIndex + 1;
                isCurrentMonth = false;
            } else if (day > daysInMonth) {
                dateNum = day - daysInMonth;
                day++;
                isCurrentMonth = false;
            } else {
                dateNum = day++;
            }

            const isToday = isCurrentMonth &&
                dateNum === today.getDate() &&
                month === today.getMonth() &&
                year === today.getFullYear();

            const dateStr = year + '-' + String(month+1).padStart(2,'0') + '-' + String(dateNum).padStart(2,'0');
            const dayEvents = events.filter(e => e.date === dateStr && (activeTypeFilter === 'all' || e.type === activeTypeFilter));

            html += `<td class="${isToday?'today':''} ${!isCurrentMonth?'other-month':''}">`;
            html += `<div class="day-num">${dateNum}</div>`;
            dayEvents.slice(0, 3).forEach(e => {
                const cls = 'event-' + e.type.toLowerCase();
                html += `<div class="day-event ${cls}" title="${e.customer} · ${e.time}">${e.time} ${e.title}</div>`;
            });
            if (dayEvents.length > 3) {
                html += `<div class="more-events">+${dayEvents.length - 3} more</div>`;
            }
            html += '</td>';
        }
        html += '</tr>';
        if (!isCurrentMonth && day > daysInMonth && r >= 4) break;
    }
    document.getElementById('calBody').innerHTML = html;
}

document.getElementById('prevMonth').onclick = () => { currentDate.setMonth(currentDate.getMonth()-1); renderCalendar(); };
document.getElementById('nextMonth').onclick = () => { currentDate.setMonth(currentDate.getMonth()+1); renderCalendar(); };
function goToday() { currentDate = new Date(); renderCalendar(); }
function filterType(btn, type) {
    document.querySelectorAll('.filter-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeTypeFilter = type;
    renderCalendar();
}

renderCalendar();
</script>
@endpush
