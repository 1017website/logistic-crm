@extends('layouts.app')
@section('title', 'Calendar')
@section('page-title', 'Calendar')
@section('page-subtitle', 'Jadwal activity, follow up, dan meeting sales')

@push('styles')
<style>
    .cal-wrap { background:#fff; border-radius:12px; border:1px solid #f0f0f0; padding:20px; }
    .cal-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .cal-nav-btn { width:32px;height:32px;border-radius:8px;border:1px solid #e5e7eb;background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;transition:.15s; }
    .cal-nav-btn:hover { background:#f9fafb; }
    .cal-title { font-size:16px;font-weight:700;color:#0f1d35;min-width:160px;text-align:center; }

    .cal-grid { width:100%;border-collapse:collapse; }
    .cal-grid th { font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;padding:8px 4px;text-align:center;letter-spacing:.5px; }
    .cal-grid td { width:14.28%;height:88px;border:1px solid #f3f4f6;padding:5px;vertical-align:top;cursor:pointer;transition:background .1s; }
    .cal-grid td:hover { background:#f9fafb; }
    .cal-grid td.today { background:#eff6ff; }
    .cal-grid td.today .day-num { background:#2563eb;color:#fff;border-radius:50%; }
    .cal-grid td.other-month { opacity:.3;cursor:default; }
    .cal-grid td.has-events { border-left:3px solid #e5e7eb; }
    .day-num { font-size:12px;font-weight:600;color:#374151;width:22px;height:22px;display:flex;align-items:center;justify-content:center;margin-bottom:2px; }
    .day-event { font-size:10px;font-weight:500;padding:1px 5px;border-radius:3px;margin-top:1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;cursor:pointer; }
    .event-call  { background:#dcfce7;color:#15803d; }
    .event-visit { background:#dbeafe;color:#1d4ed8; }
    .event-email { background:#fef9c3;color:#92400e; }
    .event-note  { background:#f3e8ff;color:#6d28d9; }
    .event-task  { background:#fee2e2;color:#b91c1c; }
    .more-events { font-size:9px;color:#6b7280;margin-top:1px;padding:0 2px; }

    /* Sidebar */
    .sidebar-card { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:16px; }
    .ev-item { display:flex;align-items:flex-start;gap:10px;padding:9px 0;border-bottom:1px solid #f9fafb;cursor:pointer; }
    .ev-item:last-child { border-bottom:none; }
    .ev-item:hover { opacity:.8; }
    .ev-dot { width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px; }
    .ev-title { font-size:12px;font-weight:600;color:#0f1d35;line-height:1.3; }
    .ev-meta  { font-size:11px;color:#6b7280;margin-top:1px; }
    .ev-badge { font-size:10px;font-weight:600;padding:1px 7px;border-radius:20px;flex-shrink:0; }

    /* Event Detail Popup */
    .event-popup {
        position:fixed;background:#fff;border-radius:12px;border:1px solid #e5e7eb;
        box-shadow:0 8px 32px rgba(0,0,0,.15);min-width:260px;max-width:300px;
        z-index:9999;padding:0;overflow:hidden;display:none;
    }
    .popup-header { padding:12px 14px;border-bottom:1px solid #f0f0f0; }
    .popup-body { padding:12px 14px;font-size:12px; }
    .popup-row { display:flex;gap:8px;margin-bottom:6px; }
    .popup-icon { width:16px;flex-shrink:0;color:#9ca3af;font-size:11px;margin-top:1px; }
    .popup-actions { padding:10px 14px;border-top:1px solid #f0f0f0;display:flex;gap:6px; }

    .filter-pill { padding:4px 12px;border-radius:20px;border:1px solid #e5e7eb;font-size:12px;font-weight:500;cursor:pointer;background:#fff;color:#6b7280;transition:.15s; }
    .filter-pill.active { background:#2563eb;color:#fff;border-color:#2563eb; }
</style>
@endpush

@section('content')

{{-- Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex gap-1 align-items-center flex-wrap">
        <button class="filter-pill active" onclick="filterType(this,'all')">All</button>
        <button class="filter-pill" onclick="filterType(this,'Call')"><i class="fas fa-phone me-1" style="font-size:10px"></i>Call</button>
        <button class="filter-pill" onclick="filterType(this,'Visit')"><i class="fas fa-building me-1" style="font-size:10px"></i>Visit</button>
        <button class="filter-pill" onclick="filterType(this,'Email')"><i class="fas fa-envelope me-1" style="font-size:10px"></i>Email</button>
        <button class="filter-pill" onclick="filterType(this,'Note')"><i class="fas fa-sticky-note me-1" style="font-size:10px"></i>Note</button>
        <button class="filter-pill" onclick="filterType(this,'Others')"><i class="fas fa-tasks me-1" style="font-size:10px"></i>Task</button>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm no-select2" style="width:150px;font-size:13px" onchange="filterSales(this.value)">
            <option value="">Semua Sales</option>
            @foreach(\App\Models\User::orderBy('name')->get() as $su)
            <option value="{{ $su->id }}">{{ $su->name }}</option>
            @endforeach
        </select>
        <button class="btn btn-primary btn-sm" onclick="openAddModal()" style="border-radius:8px;font-size:13px">
            <i class="fas fa-plus me-1"></i> Add Activity
        </button>
    </div>
</div>

<div class="row g-3">
    {{-- Calendar --}}
    <div class="col-md-9">
        <div class="cal-wrap">
            <div class="cal-header">
                <button class="cal-nav-btn" onclick="changeMonth(-1)"><i class="fas fa-chevron-left" style="font-size:11px"></i></button>
                <div class="cal-title" id="calTitle"></div>
                <button class="cal-nav-btn" onclick="changeMonth(1)"><i class="fas fa-chevron-right" style="font-size:11px"></i></button>
                <button class="btn btn-light btn-sm" onclick="goToday()" style="font-size:12px;border:1px solid #e5e7eb;border-radius:6px;margin-left:4px">Today</button>
                <div class="ms-auto d-flex gap-2" style="font-size:11px">
                    <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:#dcfce7;display:inline-block"></span>Call</span>
                    <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:#dbeafe;display:inline-block"></span>Visit</span>
                    <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:#fef9c3;display:inline-block"></span>Email</span>
                    <span style="display:flex;align-items:center;gap:4px"><span style="width:8px;height:8px;border-radius:2px;background:#fee2e2;display:inline-block"></span>Task</span>
                </div>
            </div>
            <table class="cal-grid">
                <thead>
                    <tr><th>Min</th><th>Sen</th><th>Sel</th><th>Rab</th><th>Kam</th><th>Jum</th><th>Sab</th></tr>
                </thead>
                <tbody id="calBody"></tbody>
            </table>
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="col-md-3 d-flex flex-column gap-3">

        {{-- Mini stats --}}
        <div class="sidebar-card">
            <div style="font-size:12px;font-weight:700;color:#0f1d35;margin-bottom:12px">Bulan Ini</div>
            <div class="row g-2 text-center">
                <div class="col-6">
                    <div style="background:#f0fdf4;border-radius:8px;padding:10px">
                        <div style="font-size:20px;font-weight:700;color:#16a34a" id="statTotal">0</div>
                        <div style="font-size:11px;color:#6b7280">Total</div>
                    </div>
                </div>
                <div class="col-6">
                    <div style="background:#fef2f2;border-radius:8px;padding:10px">
                        <div style="font-size:20px;font-weight:700;color:#dc2626">{{ $overdue->count() }}</div>
                        <div style="font-size:11px;color:#6b7280">Overdue</div>
                    </div>
                </div>
                <div class="col-6">
                    <div style="background:#eff6ff;border-radius:8px;padding:10px">
                        <div style="font-size:20px;font-weight:700;color:#2563eb">{{ $upcoming->count() }}</div>
                        <div style="font-size:11px;color:#6b7280">Upcoming</div>
                    </div>
                </div>
                <div class="col-6">
                    <div style="background:#f0fdf4;border-radius:8px;padding:10px">
                        <div style="font-size:20px;font-weight:700;color:#16a34a" id="statDone">0</div>
                        <div style="font-size:11px;color:#6b7280">Done</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Upcoming --}}
        <div class="sidebar-card">
            <div style="font-size:13px;font-weight:700;color:#0f1d35;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
                <span><i class="fas fa-clock text-primary me-1" style="font-size:11px"></i> Upcoming (7 Hari)</span>
                <span style="background:#eff6ff;color:#2563eb;font-size:11px;padding:1px 7px;border-radius:20px;font-weight:600">{{ $upcoming->count() }}</span>
            </div>
            @forelse($upcoming as $act)
            @php $dotColor = match($act->type) {'Call'=>'#16a34a','Visit'=>'#2563eb','Email'=>'#ca8a04',default=>'#7c3aed'}; @endphp
            <div class="ev-item" onclick="showEventDetail({{ $act->id }})">
                <div class="ev-dot" style="background:{{ $dotColor }}"></div>
                <div style="flex:1;min-width:0">
                    <div class="ev-title">{{ Str::limit($act->subject, 28) }}</div>
                    <div class="ev-meta">{{ $act->customer?->company_name ?? $act->lead?->company_name ?? '-' }}</div>
                    <div class="ev-meta"><i class="fas fa-clock me-1" style="font-size:9px"></i>{{ $act->activity_at->format('d M · H:i') }}</div>
                </div>
                <span class="ev-badge" style="background:#eff6ff;color:#2563eb">{{ $act->type }}</span>
            </div>
            @empty
            <div class="text-center py-3" style="color:#9ca3af;font-size:12px">
                <i class="fas fa-calendar-check" style="font-size:20px;display:block;margin-bottom:6px;opacity:.3"></i>
                Tidak ada jadwal mendatang
            </div>
            @endforelse
        </div>

        {{-- Overdue --}}
        @if($overdue->count() > 0)
        <div class="sidebar-card">
            <div style="font-size:13px;font-weight:700;color:#0f1d35;margin-bottom:12px;display:flex;align-items:center;justify-content:space-between">
                <span><i class="fas fa-exclamation-circle text-danger me-1" style="font-size:11px"></i> Overdue</span>
                <span style="background:#fee2e2;color:#dc2626;font-size:11px;padding:1px 7px;border-radius:20px;font-weight:600">{{ $overdue->count() }}</span>
            </div>
            @foreach($overdue as $act)
            <div class="ev-item" onclick="showEventDetail({{ $act->id }})">
                <div class="ev-dot" style="background:#ef4444"></div>
                <div style="flex:1;min-width:0">
                    <div class="ev-title">{{ Str::limit($act->subject, 28) }}</div>
                    <div class="ev-meta" style="color:#dc2626">{{ $act->activity_at->format('d M · H:i') }}</div>
                </div>
                <span class="ev-badge" style="background:#fee2e2;color:#dc2626">Overdue</span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Event Detail Popup --}}
<div class="event-popup" id="eventPopup">
    <div class="popup-header">
        <div style="display:flex;align-items:center;justify-content:space-between">
            <span style="font-size:13px;font-weight:700;color:#0f1d35" id="popupTitle">-</span>
            <button onclick="closePopup()" style="background:none;border:none;color:#9ca3af;cursor:pointer;font-size:14px;padding:0">×</button>
        </div>
        <div id="popupBadges" class="mt-1"></div>
    </div>
    <div class="popup-body" id="popupBody"></div>
    <div class="popup-actions" id="popupActions"></div>
</div>
<div id="popupOverlay" onclick="closePopup()" style="display:none;position:fixed;inset:0;z-index:9998"></div>

{{-- Add Activity Modal --}}
<div class="modal fade" data-bs-backdrop="static" data-bs-keyboard="false" id="addActivityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Tambah Activity</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="{{ route('tasks.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Jenis Activity <span class="text-danger">*</span></label>
                            <select name="type" id="addActType" class="form-select" required>
                                <option value="Call">Call</option><option value="Visit">Visit</option><option value="Email">Email</option><option value="Note">Note</option><option value="Others">Task</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Planned">Planned</option>
                                <option value="Done">Done</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" name="subject" class="form-control" required placeholder="Contoh: Follow up PT. ABC">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tanggal & Waktu <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="activity_at" id="addActDate" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            @include('components.sales-pic-field')
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lead (Opsional)</label>
                            <select name="lead_id" class="form-select">
                                <option value="">- Tidak ada / pilih lead -</option>
                                @foreach(\App\Models\Lead::whereNotIn('pipeline_stage',['Won','Lost'])->orderBy('company_name')->get() as $lead)
                                <option value="{{ $lead->id }}">{{ $lead->company_name }} ({{ $lead->pipeline_stage }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Keterangan</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Opsional..."></textarea>
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

@endsection

@push('scripts')
<script>
// ── Data dari server (bulan saat ini) ──
let allEvents = @json($events);
let currentDate = new Date();
let activeType   = 'all';
let activeSales  = '';

// ── Stats update ──
function updateStats() {
    const year  = currentDate.getFullYear();
    const month = String(currentDate.getMonth()+1).padStart(2,'0');
    const monthEvents = allEvents.filter(e => e.date.startsWith(`${year}-${month}`));
    document.getElementById('statTotal').textContent = monthEvents.length;
    document.getElementById('statDone').textContent  = monthEvents.filter(e => e.status === 'Done').length;
}

// ── Render calendar ──
function renderCalendar() {
    const year  = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const monthNames = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('calTitle').textContent = monthNames[month] + ' ' + year;

    const firstDay   = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month+1, 0).getDate();
    const prevDays   = new Date(year, month, 0).getDate();
    const today      = new Date();

    let html = '';
    let day  = 1, nextDay = 1;

    for (let r = 0; r < 6; r++) {
        html += '<tr>';
        for (let c = 0; c < 7; c++) {
            const cellIdx = r*7+c;
            let dateNum, isCurrentMonth = true;

            if (cellIdx < firstDay) {
                dateNum = prevDays - firstDay + cellIdx + 1;
                isCurrentMonth = false;
            } else if (day > daysInMonth) {
                dateNum = nextDay++;
                isCurrentMonth = false;
            } else {
                dateNum = day++;
            }

            const isToday = isCurrentMonth &&
                dateNum === today.getDate() &&
                month   === today.getMonth() &&
                year    === today.getFullYear();

            const dateStr  = `${year}-${String(month+1).padStart(2,'0')}-${String(dateNum).padStart(2,'0')}`;
            const filtered = allEvents.filter(e =>
                e.date === dateStr &&
                (activeType  === 'all' || e.type  === activeType) &&
                (activeSales === ''   || String(e.sales_id) === activeSales)
            );

            const hasBorder = filtered.length > 0;
            html += `<td class="${isToday?'today':''} ${!isCurrentMonth?'other-month':''} ${hasBorder?'has-events':''}"
                onclick="${isCurrentMonth ? `clickDay('${dateStr}')` : ''}">`;
            html += `<div class="day-num">${dateNum}</div>`;
            filtered.slice(0, 3).forEach(e => {
                const cls = 'event-' + e.type.toLowerCase();
                html += `<div class="day-event ${cls}" title="${e.customer} · ${e.time}"
                    onclick="event.stopPropagation(); showEventById(${e.id})">${e.time} ${e.title || e.type}</div>`;
            });
            if (filtered.length > 3) {
                html += `<div class="more-events">+${filtered.length-3} lainnya</div>`;
            }
            html += '</td>';
        }
        html += '</tr>';
        if (!isCurrentMonth && day > daysInMonth && r >= 4) break;
    }

    document.getElementById('calBody').innerHTML = html;
    updateStats();
}

// ── Navigasi bulan — fetch data baru dari server ──
function changeMonth(dir) {
    currentDate.setMonth(currentDate.getMonth() + dir);
    fetchEvents(currentDate.getFullYear(), currentDate.getMonth()+1);
}
function goToday() {
    currentDate = new Date();
    fetchEvents(currentDate.getFullYear(), currentDate.getMonth()+1);
}

function fetchEvents(year, month) {
    fetch(`/calendar?year=${year}&month=${month}&json=1`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        allEvents = data;
        rebuildEventsMap();
        renderCalendar();
    })
    .catch(() => renderCalendar()); // fallback render saja
}

// ── Filter ──
function filterType(btn, type) {
    document.querySelectorAll('.filter-pill').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    activeType = type;
    renderCalendar();
}
function filterSales(val) {
    activeSales = val;
    renderCalendar();
}

// ── Klik tanggal kosong → buka add modal dengan tanggal ter-set ──
function setAddActivityDate(value) {
    const el = document.getElementById('addActDate');
    if (!el) return;
    const normalized = value.replace('T', ' ');
    el.value = normalized;
    if (el._airDatepicker) el._airDatepicker.selectDate(new Date(normalized));
}
function clickDay(dateStr) {
    setAddActivityDate(dateStr + ' 09:00');
    new bootstrap.Modal(document.getElementById('addActivityModal'), {backdrop:'static', keyboard:false}).show();
}
function openAddModal() {
    const now = new Date();
    const yyyy = now.getFullYear();
    const mm = String(now.getMonth()+1).padStart(2,'0');
    const dd = String(now.getDate()).padStart(2,'0');
    const hh = String(now.getHours()).padStart(2,'0');
    const mi = String(now.getMinutes()).padStart(2,'0');
    setAddActivityDate(`${yyyy}-${mm}-${dd} ${hh}:${mi}`);
    new bootstrap.Modal(document.getElementById('addActivityModal'), {backdrop:'static', keyboard:false}).show();
}

// ── Event detail popup ──
let eventsMap = {};
function rebuildEventsMap() {
    eventsMap = {};
    allEvents.forEach(e => eventsMap[e.id] = e);
}
rebuildEventsMap();

function showEventById(id) {
    const e = eventsMap[id];
    if (!e) return;
    showPopup(e);
}
function showEventDetail(id) {
    const e = allEvents.find(x => x.id == id);
    if (!e) return;
    showPopup(e);
}
function showPopup(e) {
    const typeColors = {Call:'#16a34a',Visit:'#1d4ed8',Email:'#92400e',Note:'#6d28d9',Others:'#b91c1c'};
    const typeBgs    = {Call:'#dcfce7',Visit:'#dbeafe',Email:'#fef9c3',Note:'#f3e8ff',Others:'#fee2e2'};
    const statusBgs  = {Done:'#dcfce7',Planned:'#dbeafe',Pending:'#fef9c3',Overdue:'#fee2e2'};
    const statusClr  = {Done:'#16a34a',Planned:'#2563eb',Pending:'#d97706',Overdue:'#dc2626'};

    document.getElementById('popupTitle').textContent = e.title || e.type;
    document.getElementById('popupBadges').innerHTML =
        `<span style="font-size:10px;padding:1px 8px;border-radius:20px;background:${typeBgs[e.type]||'#f3f4f6'};color:${typeColors[e.type]||'#374151'};font-weight:600">${e.type}</span>
         <span style="font-size:10px;padding:1px 8px;border-radius:20px;background:${statusBgs[e.status]||'#f3f4f6'};color:${statusClr[e.status]||'#374151'};font-weight:600;margin-left:4px">${e.status}</span>`;

    document.getElementById('popupBody').innerHTML = `
        <div class="popup-row"><i class="fas fa-calendar popup-icon"></i><span>${e.date} · ${e.time}</span></div>
        <div class="popup-row"><i class="fas fa-building popup-icon"></i><span>${e.customer}</span></div>
        <div class="popup-row"><i class="fas fa-user-tie popup-icon"></i><span>${e.sales}</span></div>
        ${e.description ? `<div class="popup-row"><i class="fas fa-align-left popup-icon"></i><span>${e.description}</span></div>` : ''}
    `;

    document.getElementById('popupActions').innerHTML = `
        <form method="POST" action="/tasks/${e.id}" style="margin:0;display:inline">
            <input type="hidden" name="_method" value="PATCH">
            <input type="hidden" name="_token" value="${document.querySelector('meta[name=csrf-token]').content}">
            <input type="hidden" name="status" value="Done">
            <input type="hidden" name="subject" value="${e.title||e.type}">
            <button type="submit" class="btn btn-sm btn-success" style="font-size:11px;padding:3px 10px">
                <i class="fas fa-check me-1"></i>Mark Done
            </button>
        </form>
        <a href="/tasks" class="btn btn-sm btn-light" style="font-size:11px;padding:3px 10px;border:1px solid #e5e7eb">
            <i class="fas fa-list me-1"></i>Lihat Tasks
        </a>
    `;

    // Posisikan popup di tengah viewport
    const popup = document.getElementById('eventPopup');
    popup.style.display = 'block';
    popup.style.top  = '50%';
    popup.style.left = '50%';
    popup.style.transform = 'translate(-50%, -50%)';
    document.getElementById('popupOverlay').style.display = 'block';

    // Update map dengan data terbaru
    eventsMap[e.id] = e;
}
function closePopup() {
    document.getElementById('eventPopup').style.display = 'none';
    document.getElementById('popupOverlay').style.display = 'none';
}

// Init
renderCalendar();
</script>
@endpush
