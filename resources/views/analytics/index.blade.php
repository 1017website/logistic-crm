@extends('layouts.app')
@section('title', 'Analytics')
@section('page-title', 'Analytics')
@section('page-subtitle', 'Monitor performa bisnis dan penjualan secara real-time')

@push('styles')
<style>
.kpi-card { background:#fff;border-radius:12px;padding:18px 20px;border:1px solid #f0f0f0; }
.kpi-icon { width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0; }
.kpi-value { font-size:20px;font-weight:700;color:#0f1d35;margin:3px 0 2px; }
.kpi-label { font-size:12px;color:#6b7280; }
.chart-card { background:#fff;border-radius:12px;border:1px solid #f0f0f0;padding:18px 20px; }
.chart-title { font-size:14px;font-weight:600;color:#0f1d35;margin-bottom:4px; }
.funnel-row { display:flex;align-items:center;gap:10px;margin-bottom:6px; }
.funnel-label { font-size:12px;color:#374151;width:90px;flex-shrink:0; }
.funnel-bar-wrap { flex:1;height:32px;display:flex;align-items:center; }
.funnel-bar { height:32px;border-radius:4px;display:flex;align-items:center;padding-left:10px;font-size:11px;font-weight:600;color:#fff;min-width:30px; }
.funnel-count { font-size:11px;color:#6b7280;width:80px;text-align:right;flex-shrink:0; }
.progress-sm { height:5px;border-radius:3px;background:#e5e7eb;overflow:hidden; }
.progress-sm-fill { height:100%;border-radius:3px; }
</style>
@endpush

@section('content')

{{-- Filter bar --}}
<form method="GET" action="{{ route('analytics.index') }}">
    <div class="card mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;font-weight:600">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;font-weight:600">Tanggal Akhir</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label" style="font-size:12px;font-weight:600">Sales PIC</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">Semua Sales</option>
                        @foreach($salesUsers as $su)
                        <option value="{{ $su->id }}" @selected($salesId==$su->id)>{{ $su->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-fill"><i class="fas fa-filter me-1"></i> Filter</button>
                    <a href="{{ route('analytics.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i></a>
                </div>
            </div>
        </div>
    </div>
</form>

{{-- KPI Row --}}
<div class="row g-3 mb-4">
    @php
    $kpis = [
        ['bg'=>'#eff6ff','icon'=>'fas fa-dollar-sign','ico_color'=>'#3b82f6','label'=>'Revenue (Omzet)','value'=>idr($revenue),'sub'=>'Total periode ini'],
        ['bg'=>'#f0fdf4','icon'=>'fas fa-chart-line','ico_color'=>'#10b981','label'=>'Gross Profit','value'=>idr($grossProfit),'sub'=>'Revenue - Biaya Vendor'],
        ['bg'=>'#faf5ff','icon'=>'fas fa-wallet','ico_color'=>'#7c3aed','label'=>'Nett Profit','value'=>idr($nettProfit),'sub'=>'Revenue - Total Biaya'],
        ['bg'=>'#f0fdfa','icon'=>'fas fa-file-invoice','ico_color'=>'#0d9488','label'=>'Volume DO','value'=>$volumeDo,'sub'=>'DO Done periode ini'],
        ['bg'=>'#fff7ed','icon'=>'fas fa-handshake','ico_color'=>'#f97316','label'=>'Deals Closed','value'=>$dealsClosed,'sub'=>'Won periode ini'],
        ['bg'=>'#fef9c3','icon'=>'fas fa-bullseye','ico_color'=>'#ca8a04','label'=>'Conversion Rate','value'=>$conversionRate.'%','sub'=>'Lead → Won'],
    ];
    @endphp
    @foreach($kpis as $k)
    <div class="col">
        <div class="kpi-card d-flex align-items-center gap-3">
            <div class="kpi-icon" style="background:{{ $k['bg'] }}">
                <i class="{{ $k['icon'] }}" style="color:{{ $k['ico_color'] }}"></i>
            </div>
            <div>
                <div class="kpi-label">{{ $k['label'] }}</div>
                <div class="kpi-value">{{ $k['value'] }}</div>
                <div style="font-size:11px;color:#9ca3af">{{ $k['sub'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Row 2: Revenue Trend + Pipeline Funnel + Sales Performance --}}
<div class="row g-3 mb-3">
    {{-- Revenue Trend --}}
    <div class="col-md-5">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-title">Revenue & Profit Trend (6 Bulan)</div>
            </div>
            <canvas id="revenueTrendChart" height="180"></canvas>
        </div>
    </div>

    {{-- Pipeline Funnel --}}
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="chart-title mb-3">Pipeline Conversion Funnel</div>
            @php
            $funnelColors = ['Identifying'=>'#3b82f6','Approaching'=>'#10b981','Follow Up'=>'#f59e0b','Closing'=>'#f97316','Won'=>'#16a34a'];
            $maxCount = max(array_values($funnel->toArray()) + [1]);
            @endphp
            @foreach($funnel as $stage => $count)
            @php $pct = $maxCount > 0 ? round(($count/$maxCount)*100) : 0; @endphp
            <div class="funnel-row">
                <div class="funnel-label">{{ $stage }}</div>
                <div class="funnel-bar-wrap">
                    <div class="funnel-bar" style="width:{{ max($pct,8) }}%;background:{{ $funnelColors[$stage]??'#9ca3af' }}">
                        @if($pct > 20) {{ $count }} @endif
                    </div>
                </div>
                <div class="funnel-count">{{ $count }} leads</div>
            </div>
            @if(!$loop->last)<div style="text-align:center;font-size:10px;color:#d1d5db;margin:2px 0 2px 90px">▼</div>@endif
            @endforeach
            <div class="mt-3 text-center">
                <span style="font-size:12px;color:#6b7280">Conversion Rate</span>
                <div style="font-size:26px;font-weight:700;color:#16a34a">{{ $conversionRate }}%</div>
            </div>
        </div>
    </div>

    {{-- Sales Performance --}}
    <div class="col-md-3">
        <div class="chart-card h-100">
            <div class="chart-title mb-3">Sales Performance</div>
            @php $maxRev = $salesPerformance->max('revenue') ?: 1; @endphp
            @forelse($salesPerformance->take(6) as $s)
            @php $pct = $maxRev > 0 ? round(($s->revenue/$maxRev)*100) : 0; @endphp
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="user-avatar" style="width:28px;height:28px;font-size:.62rem;flex-shrink:0">
                    {{ strtoupper(substr($s->name,0,2)) }}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:12px;font-weight:600;color:#0f1d35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $s->name }}</div>
                    <div class="progress-sm mt-1"><div class="progress-sm-fill" style="width:{{ $pct }}%;background:#3b82f6"></div></div>
                </div>
                <div style="text-align:right;flex-shrink:0;min-width:60px">
                    <div style="font-size:11px;font-weight:600;color:#374151">{{ idrm($s->revenue) }}</div>
                    <div style="font-size:10px;color:#6b7280">{{ $s->deals_closed }} deals</div>
                </div>
            </div>
            @empty
            <div class="text-center py-4" style="color:#9ca3af;font-size:12px">Belum ada data</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Row 3: Revenue by Service + Revenue by Route + Top Customers --}}
<div class="row g-3 mb-3">
    {{-- Revenue by Service --}}
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-title">Revenue by Service Type</div>
            </div>
            @if($revenueByService->count())
            <div class="d-flex align-items-center gap-3">
                <div style="width:120px;height:120px;flex-shrink:0;position:relative">
                <canvas id="serviceDonutChart" style="width:100%;height:100%"></canvas>
                </div>
                <div style="flex:1">
                    @php $totalSvc = $revenueByService->sum('total'); $svcColors = ['#3b82f6','#10b981','#f59e0b','#f97316','#8b5cf6','#ec4899']; @endphp
                    @foreach($revenueByService as $idx => $svc)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:8px;height:8px;border-radius:2px;background:{{ $svcColors[$idx%count($svcColors)] }};flex-shrink:0"></div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:12px;font-weight:600;color:#374151;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $svc->service_type ?: 'Lainnya' }}</div>
                            <div style="font-size:11px;color:#6b7280">{{ idrm($svc->total) }}</div>
                        </div>
                        <span style="font-size:11px;font-weight:600;color:#0f1d35;flex-shrink:0">{{ $totalSvc > 0 ? round(($svc->total/$totalSvc)*100,1) : 0 }}%</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <div class="text-center py-4" style="color:#9ca3af;font-size:12px">
                <i class="fas fa-chart-pie" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>
                Belum ada data DO periode ini
            </div>
            @endif
        </div>
    </div>

    {{-- Revenue by Route --}}
    <div class="col-md-4">
        <div class="chart-card">
            <div class="chart-title mb-3">Revenue by Route (Top 5)</div>
            @if($revenueByRoute->count())
            @php $maxRoute = $revenueByRoute->max('total') ?: 1; @endphp
            @foreach($revenueByRoute as $r)
            @php $pct = round(($r->total/$maxRoute)*100); @endphp
            <div class="d-flex align-items-center gap-2 mb-3">
                <div style="font-size:11px;color:#374151;width:130px;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="{{ $r->route }}">{{ $r->route }}</div>
                <div style="flex:1">
                    <div style="height:7px;border-radius:4px;background:#3b82f6;width:{{ $pct }}%"></div>
                </div>
                <div style="font-size:11px;color:#6b7280;width:80px;text-align:right;flex-shrink:0">{{ idrm($r->total) }}</div>
            </div>
            @endforeach
            @else
            <div class="text-center py-4" style="color:#9ca3af;font-size:12px">Belum ada data periode ini</div>
            @endif
        </div>
    </div>

    {{-- Top Customers --}}
    <div class="col-md-4">
        <div class="chart-card">
            <div class="chart-title mb-3">Top Customers by Revenue</div>
            @forelse($topCustomers as $tc)
            <div class="d-flex align-items-center gap-2 mb-3">
                <div class="user-avatar" style="width:28px;height:28px;font-size:.62rem;border-radius:6px;flex-shrink:0">
                    {{ $tc['customer']->logo_initials }}
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:12px;font-weight:600;color:#0f1d35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $tc['customer']->company_name }}</div>
                    <div style="font-size:11px;color:#6b7280">{{ $tc['deals'] }} DO · {{ $tc['repeat'] ? 'Repeat' : 'New' }}</div>
                </div>
                <span style="font-size:12px;font-weight:600;color:var(--primary);flex-shrink:0">{{ idrm($tc['revenue']) }}</span>
            </div>
            @empty
            <div class="text-center py-4" style="color:#9ca3af;font-size:12px">Belum ada data</div>
            @endforelse
        </div>
    </div>
</div>

{{-- Row 4: Profit Analysis + Lead Source + Recent Deals --}}
<div class="row g-3">
    {{-- Profit Analysis --}}
    <div class="col-md-4">
        <div class="chart-card">
            <div class="chart-title mb-3">Profit Analysis (6 Bulan)</div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <div style="background:#f9fafb;border-radius:8px;padding:10px;text-align:center">
                        <div style="font-size:11px;color:#6b7280">Avg Gross Margin</div>
                        <div style="font-size:18px;font-weight:700;color:#10b981">32%</div>
                    </div>
                </div>
                <div class="col-6">
                    <div style="background:#f9fafb;border-radius:8px;padding:10px;text-align:center">
                        <div style="font-size:11px;color:#6b7280">Avg Nett Margin</div>
                        <div style="font-size:18px;font-weight:700;color:#3b82f6">19%</div>
                    </div>
                </div>
            </div>
            <canvas id="profitChart" height="160"></canvas>
        </div>
    </div>

    {{-- Lead Source --}}
    <div class="col-md-4">
        <div class="chart-card">
            <div class="chart-title mb-3">Lead Source Performance</div>
            @if($leadSources->count())
            <div class="d-flex align-items-center gap-3">
                <div style="width:120px;height:120px;flex-shrink:0;position:relative">
                <canvas id="leadSourceChart" style="width:100%;height:100%"></canvas>
                </div>
                <div style="flex:1">
                    @php $totalSrc = $leadSources->sum('count'); $srcColors=['#3b82f6','#10b981','#f59e0b','#f97316','#8b5cf6','#9ca3af']; @endphp
                    @foreach($leadSources as $idx => $src)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:8px;height:8px;border-radius:2px;background:{{ $srcColors[$idx%count($srcColors)] }};flex-shrink:0"></div>
                        <div style="flex:1;font-size:12px;color:#374151">{{ $src->lead_source ?: 'Tidak diketahui' }}</div>
                        <span style="font-size:12px;font-weight:600;color:#0f1d35">{{ $src->count }}</span>
                        <span style="font-size:11px;color:#6b7280">{{ $totalSrc > 0 ? round(($src->count/$totalSrc)*100,1) : 0 }}%</span>
                    </div>
                    @endforeach
                    <div class="d-flex gap-3 mt-2 pt-2" style="border-top:1px solid #f0f0f0">
                        <div><div style="font-size:11px;color:#6b7280">Total Leads</div><div style="font-size:16px;font-weight:700">{{ $leadSources->sum('count') }}</div></div>
                    </div>
                </div>
            </div>
            @else
            <div class="text-center py-4" style="color:#9ca3af;font-size:12px">Belum ada data lead</div>
            @endif
        </div>
    </div>

    {{-- Recent Deals Closed --}}
    <div class="col-md-4">
        <div class="chart-card">
            <div class="chart-title mb-3">Recent Deals Closed</div>
            @forelse($recentDeals as $deal)
            <div class="d-flex align-items-start gap-2 mb-3 pb-2" style="border-bottom:1px solid #f9fafb">
                <div style="width:30px;height:30px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fas fa-trophy" style="color:#16a34a;font-size:12px"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:600;color:#0f1d35">{{ $deal->company_name }}</div>
                    <div style="font-size:11px;color:#6b7280">{{ $deal->service_type ?: 'N/A' }} · {{ $deal->salesUser?->name ?? '-' }}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:12px;font-weight:600;color:#0f1d35">{{ idrm($deal->potensi_revenue) }}</div>
                    <div style="font-size:11px;color:#9ca3af">{{ $deal->updated_at->format('d M Y') }}</div>
                </div>
            </div>
            @empty
            <div class="text-center py-4" style="color:#9ca3af;font-size:12px">
                <i class="fas fa-trophy" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>
                Belum ada deal closed
            </div>
            @endforelse
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
@php
$trendLabels  = array_column($revenueTrend, 'label');
$trendRevenue = array_column($revenueTrend, 'value');
$trendProfit  = array_map(fn($v) => round($v*0.19,1), $trendRevenue);

$svcLabels = $revenueByService->pluck('service_type')->map(fn($s) => $s ?: 'Lainnya')->toArray();
$svcValues = $revenueByService->pluck('total')->map(fn($v) => (float)($v/1000000))->toArray();

$srcLabels = $leadSources->pluck('lead_source')->map(fn($s) => $s ?: 'Lainnya')->toArray();
$srcValues = $leadSources->pluck('count')->toArray();

$profitLabels   = array_column($profitAnalysis, 'label');
$profitRevenue  = array_column($profitAnalysis, 'revenue');
$profitCost     = array_column($profitAnalysis, 'cost');
$profitGross    = array_column($profitAnalysis, 'gross_profit');
$profitNet      = array_column($profitAnalysis, 'profit');
@endphp

const svcColors = ['#3b82f6','#10b981','#f59e0b','#f97316','#8b5cf6','#ec4899'];
const srcColors = ['#3b82f6','#10b981','#f59e0b','#f97316','#8b5cf6','#9ca3af'];

// Revenue Trend
new Chart(document.getElementById('revenueTrendChart'), {
    type: 'line',
    data: {
        labels: {!! json_encode($trendLabels) !!},
        datasets: [
            { label: 'Revenue', data: {!! json_encode($trendRevenue) !!}, borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,.08)', fill: true, tension: .4, borderWidth: 2, pointRadius: 3 },
            { label: 'Nett Profit', data: {!! json_encode($trendProfit) !!}, borderColor: '#10b981', backgroundColor: 'transparent', fill: false, tension: .4, borderWidth: 2, pointRadius: 3 }
        ]
    },
    options: {
        plugins: { legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } } },
        scales: {
            y: { ticks: { font: { size: 10 }, callback: v => v + ' Jt' }, grid: { color: '#f3f4f6' } },
            x: { ticks: { font: { size: 10 } }, grid: { display: false } }
        }
    }
});

// Service Donut
@if($revenueByService->count())
new Chart(document.getElementById('serviceDonutChart'), {
    type: 'doughnut',
    data: { labels: {!! json_encode($svcLabels) !!}, datasets: [{ data: {!! json_encode($svcValues) !!}, backgroundColor: svcColors, borderWidth: 0, hoverOffset: 4 }] },
    options: { cutout: '65%', maintainAspectRatio: false, plugins: { legend: { display: false } } }
});
@endif

// Lead Source Donut
@if($leadSources->count())
new Chart(document.getElementById('leadSourceChart'), {
    type: 'doughnut',
    data: { labels: {!! json_encode($srcLabels) !!}, datasets: [{ data: {!! json_encode($srcValues) !!}, backgroundColor: srcColors, borderWidth: 0, hoverOffset: 4 }] },
    options: {
        cutout: '60%',
        maintainAspectRatio: false,
        plugins: { legend: { display: false } }
    }
});
@endif

// Profit Analysis
new Chart(document.getElementById('profitChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($profitLabels) !!},
        datasets: [
            { label: 'Revenue', data: {!! json_encode($profitRevenue) !!}, backgroundColor: '#3b82f6', borderRadius: 3, barPercentage: .5 },
            { label: 'Total Cost', data: {!! json_encode($profitCost) !!}, backgroundColor: '#fca5a5', borderRadius: 3, barPercentage: .5 },
            { label: 'Gross Profit', data: {!! json_encode($profitGross) !!}, type: 'line', borderColor: '#10b981', backgroundColor: 'transparent', tension: .4, borderWidth: 2, pointRadius: 3 },
            { label: 'Nett Profit', data: {!! json_encode($profitNet) !!}, type: 'line', borderColor: '#7c3aed', backgroundColor: 'transparent', tension: .4, borderWidth: 2, pointRadius: 3, borderDash: [4,3] }
        ]
    },
    options: {
        plugins: { legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 10 } } },
        scales: {
            y: { ticks: { font: { size: 10 }, callback: v => v + ' Jt' }, grid: { color: '#f3f4f6' } },
            x: { ticks: { font: { size: 10 } }, grid: { display: false } }
        }
    }
});
</script>
@endpush