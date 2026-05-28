@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Sales Activity Overview')

@section('content')
{{-- KPI Row --}}
@php
function growthBadge($val, $prev_label) {
    if ($val == 0) return '<span class="kpi-change" style="color:#9ca3af">— vs ' . $prev_label . '</span>';
    $cls = $val > 0 ? 'up' : 'down';
    $icon = $val > 0 ? 'arrow-up' : 'arrow-down';
    return '<span class="kpi-change ' . $cls . '"><i class="fas fa-' . $icon . '"></i> ' . abs($val) . '%</span><span class="kpi-vs ms-1">vs ' . $prev_label . '</span>';
}
@endphp
<div class="row g-3 mb-4">
    <div class="col-xl col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dbeafe">
                <i class="fas fa-dollar-sign" style="color:#2563eb"></i>
            </div>
            <div>
                <div class="kpi-label">Revenue (Omzet)</div>
                <div class="kpi-value">{{ idrm($revenue) }}</div>
                <div>{!! growthBadge($revenueGrowth, $prevLabel) !!}</div>
                <div style="font-size:10px;color:#9ca3af;margin-top:2px">dari PO berstatus Done</div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5">
                <i class="fas fa-truck" style="color:#059669"></i>
            </div>
            <div>
                <div class="kpi-label">Total DO</div>
                <div class="kpi-value">{{ $totalDo }}</div>
                <div>{!! growthBadge($doGrowth, $prevLabel) !!}</div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#ede9fe">
                <i class="fas fa-chart-bar" style="color:#7c3aed"></i>
            </div>
            <div>
                <div class="kpi-label">Conversion Rate</div>
                <div class="kpi-value">{{ $conversionRate }}%</div>
                <div><span class="kpi-change" style="color:#9ca3af">Bulan ini</span></div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7">
                <i class="fas fa-users" style="color:#d97706"></i>
            </div>
            <div>
                <div class="kpi-label">Active Leads</div>
                <div class="kpi-value">{{ $activeLeads }}</div>
                <div>{!! growthBadge($leadsGrowth, $prevLabel) !!}</div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5">
                <i class="fas fa-check-circle" style="color:#059669;font-size:1.4rem"></i>
            </div>
            <div>
                <div class="kpi-label">Deal Closed</div>
                <div class="kpi-value">{{ $dealClosed }}</div>
                <div>{!! growthBadge($dealGrowth, $prevLabel) !!}</div>
            </div>
        </div>
    </div>
</div>

{{-- Charts Row --}}
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Revenue (Omzet)</span>
                <select class="form-select form-select-sm" style="width:130px">
                    <option>This Month</option>
                    <option>Last Month</option>
                </select>
            </div>
            <div class="card-body p-3">
                <canvas id="revenueChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Volume PO</span>
                <select class="form-select form-select-sm" style="width:130px"><option>This Month</option></select>
            </div>
            <div class="card-body p-3">
                <canvas id="volumeChart" height="120"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Trend Won/Closing (Deal)</span>
                <select class="form-select form-select-sm" style="width:110px"><option>This Month</option></select>
            </div>
            <div class="card-body p-3">
                <canvas id="closingChart" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- Pipeline + Reminder --}}
<div class="row g-3 mb-4">
    {{-- Pipeline --}}
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Pipeline Sales</div>
            <div class="card-body p-3">
                <div class="row g-2">
                    @foreach($pipelineStages as $stageName => $stageLeads)
                    @php
                        $slugs = ['Identifying'=>'identifying','Approaching'=>'approaching','Follow Up'=>'follow-up','Won'=>'won','Maintaining'=>'maintaining'];
                        $slug = $slugs[$stageName] ?? 'identifying';
                        $colors = ['Identifying'=>'#2563eb','Approaching'=>'#d97706','Follow Up'=>'#7c3aed','Won'=>'#059669','Maintaining'=>'#4f46e5'];
                    @endphp
                    <div class="col-xl col-md-4 col-sm-6">
                        <div class="kanban-header kanban-{{ $slug }}">
                            <span>{{ strtoupper($stageName === 'Won' ? 'Won/Closing' : $stageName) }}</span>
                            <span class="badge" style="background:{{ $colors[$stageName] ?? '#333' }};color:#fff;font-size:.65rem">{{ $stageLeads->count() }}</span>
                        </div>
                        <div class="kanban-body">
                            @forelse($stageLeads->take(3) as $lead)
                            <div class="kanban-card" onclick="window.location='{{ route('leads.show', $lead) }}'">
                                <div class="kc-company">{{ $lead->company_name }}</div>
                                <div class="kc-service">{{ $lead->product_interest }}</div>
                                <div class="kc-footer">
                                    <span class="badge-{{ strtolower($lead->temperature) }}">{{ $lead->temperature }}</span>
                                    <small style="color:var(--text-muted)">Today</small>
                                </div>
                            </div>
                            @empty
                            <div class="text-center text-muted p-3" style="font-size:.75rem">No leads</div>
                            @endforelse
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Today Reminder --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Today Reminder</span>
                <a href="{{ route('sales.activity') }}" style="font-size:.75rem;color:var(--primary)">View All</a>
            </div>
            <div class="card-body p-3">
                @forelse($todayReminders as $reminder)
                <div class="reminder-item">
                    <div class="reminder-time">{{ $reminder->activity_at->format('H:i') }}</div>
                    <div style="width:30px;height:30px;background:{{ $reminder->type === 'Call' ? '#d1fae5' : '#dbeafe' }};border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas fa-{{ $reminder->type_icon }}" style="font-size:.7rem;color:{{ $reminder->type === 'Call' ? '#059669' : '#2563eb' }}"></i>
                    </div>
                    <div class="flex-1">
                        <div style="font-size:.78rem;font-weight:600">{{ $reminder->subject }}</div>
                        <div style="font-size:.7rem;color:var(--text-muted)">{{ $reminder->description }}</div>
                    </div>
                    <span class="badge-{{ strtolower($reminder->status) }}">{{ $reminder->status }}</span>
                </div>
                @empty
                <div class="text-center text-muted p-3" style="font-size:.8rem">Tidak ada reminder hari ini</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- Bottom Row --}}
<div class="row g-3">
    {{-- Recent Activity --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Recent Sales Activity</span>
                <a href="{{ route('sales.activity') }}" style="font-size:.75rem;color:var(--primary)">View All</a>
            </div>
            <div class="card-body p-3">
                @foreach($recentActivities as $act)
                <div class="activity-item">
                    <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#dbeafe' : '#fef3c7') }}">
                        <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#2563eb' : '#d97706') }};font-size:.8rem"></i>
                    </div>
                    <div class="flex-1">
                        <div class="activity-subject">{{ $act->subject }}</div>
                        <div class="activity-desc">{{ Str::limit($act->description, 45) }}</div>
                        <div class="activity-meta">{{ $act->salesUser?->name }} · {{ $act->activity_at->format('d M, H:i') }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Top Sales --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Top Sales Performance</span>
                <select class="form-select form-select-sm" style="width:120px"><option>This Month</option></select>
            </div>
            <div class="card-body p-3">
                <table class="table crm-table mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Sales</th>
                            <th>Revenue</th>
                            <th>Deal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($topSales->take(5) as $i => $s)
                        <tr>
                            <td><span style="font-weight:700;color:{{ $i === 0 ? '#f59e0b' : ($i === 1 ? '#9ca3af' : ($i === 2 ? '#d97706' : '#374151')) }}">{{ $i+1 }}</span></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <div class="user-avatar" style="width:28px;height:28px;font-size:.65rem">{{ substr($s->name,0,2) }}</div>
                                    <span>{{ $s->name }}</span>
                                </div>
                            </td>
                            <td>Rp {{ number_format($s->deals_closed * 50000000 / 1000000, 0) }}M</td>
                            <td>{{ $s->deals_closed }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Quick Action --}}
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Quick Action</div>
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-4">
                        <a href="{{ route('leads.index') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#dbeafe">
                                <i class="fas fa-plus" style="color:#2563eb;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Add Lead</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="{{ route('sales.activity') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#d1fae5">
                                <i class="fas fa-phone" style="color:#059669;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Log Call</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="{{ route('sales.activity') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#fef3c7">
                                <i class="fas fa-map-marker-alt" style="color:#d97706;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Log Visit</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="{{ route('sales.activity') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#ede9fe">
                                <i class="fas fa-envelope" style="color:#7c3aed;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Send Email</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="#" class="quick-action-btn">
                            <div class="qa-icon" style="background:#ccfbf1">
                                <i class="fas fa-file-alt" style="color:#0d9488;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Create PO</span>
                        </a>
                    </div>
                    <div class="col-4">
                        <a href="{{ route('sales.activity') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#fef3c7">
                                <i class="fas fa-sticky-note" style="color:#d97706;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Add Note</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// ── Revenue Chart (data real dari DB) ──
const labels     = {!! json_encode(array_column($revenueChart, 'date')) !!};
const values     = {!! json_encode(array_column($revenueChart, 'value')) !!};
const volValues  = {!! json_encode(array_column($volumeChart, 'value')) !!};
const wonValues  = {!! json_encode(array_column($trendWon, 'value')) !!};
const lostValues = {!! json_encode(array_column($trendLost, 'value')) !!};

new Chart(document.getElementById('revenueChart').getContext('2d'), {
    type: 'line',
    data: {
        labels,
        datasets: [{
            data: values.map(v => v / 1000000),
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,.1)',
            fill: true, tension: .4, borderWidth: 2, pointRadius: 0,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 6 } },
            y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, callback: v => 'Rp ' + v + 'M' } }
        }
    }
});

// ── Volume PO Chart (data real dari DB) ──
new Chart(document.getElementById('volumeChart').getContext('2d'), {
    type: 'bar',
    data: {
        labels,
        datasets: [{
            data: volValues,
            backgroundColor: '#10b981',
            borderRadius: 3,
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 6 } },
            y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, stepSize: 1 } }
        }
    }
});

// ── Won/Lost Trend Chart (data real dari DB) ──
new Chart(document.getElementById('closingChart').getContext('2d'), {
    type: 'line',
    data: {
        labels,
        datasets: [
            {
                label: 'Won',
                data: wonValues,
                borderColor: '#10b981', fill: false, tension: .4, borderWidth: 2, pointRadius: 0,
            },
            {
                label: 'Lost',
                data: lostValues,
                borderColor: '#ef4444', fill: false, tension: .4, borderWidth: 2, pointRadius: 0,
            }
        ]
    },
    options: {
        plugins: { legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 12 } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, maxTicksLimit: 5 } },
            y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, stepSize: 1 } }
        }
    }
});
</script>
@endpush
