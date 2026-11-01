@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', 'Dashboard')
@section('page-subtitle', 'Sales Activity Overview')

@section('content')
{{-- KPI Row --}}
@php
$growthBadge = function ($val, $prev_label) {
    if ($val == 0) return '<span class="kpi-change" style="color:#9ca3af">— vs ' . $prev_label . '</span>';
    $cls = $val > 0 ? 'up' : 'down';
    $icon = $val > 0 ? 'arrow-up' : 'arrow-down';
    return '<span class="kpi-change ' . $cls . '"><i class="fas fa-' . $icon . '"></i> ' . abs($val) . '%</span><span class="kpi-vs ms-1">vs ' . $prev_label . '</span>';
};
@endphp
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="kpi-card h-100">
            <div class="kpi-icon" style="background:#e0f2fe">
                <i class="fas fa-chart-line" style="color:#0369a1"></i>
            </div>
            <div>
                <div class="kpi-label">Omzet Keseluruhan</div>
                <div class="kpi-value">{{ idrm($totalTurnover) }}</div>
                <div style="font-size:10px;color:#9ca3af;margin-top:2px">Nilai jual seluruh invoice terbit, sebelum PPN</div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="kpi-card h-100">
            <div class="kpi-icon" style="background:#fef3c7">
                <i class="fas fa-file-invoice-dollar" style="color:#b45309"></i>
            </div>
            <div>
                <div class="kpi-label">Total Piutang Keseluruhan</div>
                <div class="kpi-value">{{ idrm($totalReceivables) }}</div>
                <div style="font-size:10px;color:#9ca3af;margin-top:2px">Grand total invoice terbit dikurangi pembayaran masuk</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-xl col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#e5e5e5">
                <i class="fas fa-dollar-sign" style="color:#111111"></i>
            </div>
            <div>
                <div class="kpi-label">Omzet Bulan Ini</div>
                <div class="kpi-value">{{ idrm($revenue) }}</div>
                <div>{!! $growthBadge($revenueGrowth, $prevLabel) !!}</div>
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
                <div class="small">{{ $doPeriodLabel }}</div>
                <div>{!! $growthBadge($doGrowth, $doPreviousLabel) !!}</div>
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
                <div>{!! $growthBadge($leadsGrowth, $prevLabel) !!}</div>
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
                <div>{!! $growthBadge($dealGrowth, $prevLabel) !!}</div>
            </div>
        </div>
    </div>
</div>

<section class="card mb-4" id="customer-do-summary" aria-labelledby="customer-do-heading">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 id="customer-do-heading" class="h6 mb-1">Jumlah DO per Customer</h2>
            <div class="small">{{ $doPeriodLabel }}: {{ number_format($customerDoTotal) }} DO dari {{ number_format($customerDoCount) }} customer</div>
        </div>
        <form method="GET" action="{{ route('dashboard') }}#customer-do-summary" class="d-flex flex-wrap align-items-end gap-2">
            <div>
                <label for="do-month" class="form-label small mb-1">Bulan</label>
                <select id="do-month" name="do_month" required class="form-select form-select-sm">
                    @foreach([1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'] as $month => $label)
                    <option value="{{ $month }}" @selected($doMonth === $month)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="do-year" class="form-label small mb-1">Tahun</label>
                <input id="do-year" type="number" name="do_year" value="{{ $doYear }}" min="1900" max="9999" required class="form-control form-control-sm" style="width:100px">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Tampilkan</button>
        </form>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0" style="min-width:0;table-layout:fixed;width:100%">
            <thead><tr><th scope="col" class="px-3">Customer</th><th scope="col" class="text-end px-3" style="width:120px">Jumlah DO</th></tr></thead>
            <tbody>
                @forelse($customerDos as $row)
                <tr>
                    <td class="px-3" style="overflow-wrap:anywhere">{{ $row->customer?->company_name ?? 'Tanpa customer' }}</td>
                    <td class="text-end px-3">
                        @if(auth()->user()->canAccess('delivery_orders') && $row->customer_id)
                        <a href="{{ route('delivery-orders.index', ['customer_id' => $row->customer_id, 'start_date' => $doStart->toDateString(), 'end_date' => $doEnd->toDateString()]) }}" aria-label="Lihat {{ $row->do_count }} DO {{ $row->customer?->company_name ?? 'customer' }} pada {{ $doPeriodLabel }}">{{ number_format($row->do_count) }}</a>
                        @else
                        {{ number_format($row->do_count) }}
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="2" class="text-center py-4">Belum ada Delivery Order pada {{ $doPeriodLabel }}.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($customerDoCount > 5 || $showAllCustomers)
    <div class="card-footer d-flex flex-wrap justify-content-between align-items-center gap-2">
        <a class="btn btn-outline-secondary btn-sm" href="{{ route('dashboard', ['do_month' => $doMonth, 'do_year' => $doYear, 'do_all' => $showAllCustomers ? 0 : 1]) }}#customer-do-summary">{{ $showAllCustomers ? 'Tampilkan 5 Teratas' : 'Lihat Semua' }}</a>
        @if($showAllCustomers)
        {{ $customerDos->fragment('customer-do-summary')->links() }}
        @else
        <span class="small">Menampilkan 5 customer dengan jumlah DO terbanyak.</span>
        @endif
    </div>
    @endif
</section>

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
                        $colors = ['Identifying'=>'#111111','Approaching'=>'#d97706','Follow Up'=>'#7c3aed','Won'=>'#059669','Maintaining'=>'#4f46e5'];
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
                    <div style="width:30px;height:30px;background:{{ $reminder->type === 'Call' ? '#d1fae5' : '#e5e5e5' }};border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="fas fa-{{ $reminder->type_icon }}" style="font-size:.7rem;color:{{ $reminder->type === 'Call' ? '#059669' : '#111111' }}"></i>
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
                    <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#e5e5e5' : '#fef3c7') }}">
                        <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#111111' : '#d97706') }};font-size:.8rem"></i>
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
                <div class="table-responsive">
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
    </div>

    {{-- Quick Action --}}
    @if(auth()->user()->canAccess('quick_actions'))
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Quick Action</div>
            <div class="card-body p-3">
                <div class="row g-2">
                    <div class="col-4">
                        <a href="{{ route('leads.index') }}" class="quick-action-btn">
                            <div class="qa-icon" style="background:#e5e5e5">
                                <i class="fas fa-plus" style="color:#111111;font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">Leads</span>
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
    @endif
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
            borderColor: '#111111',
            backgroundColor: 'rgba(17,17,17,.08)',
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
