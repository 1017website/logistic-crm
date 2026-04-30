@extends('layouts.app')

@section('title', 'Analytics')

@push('styles')
<style>
    .kpi-card { background: #fff; border-radius: 12px; padding: 20px 24px; border: 1px solid #f0f0f0; }
    .kpi-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .kpi-value { font-size: 22px; font-weight: 700; color: #0f1d35; margin: 4px 0 2px; }
    .kpi-label { font-size: 12px; color: #6b7280; }
    .kpi-growth { font-size: 12px; font-weight: 600; }
    .kpi-growth.up { color: #10b981; }
    .kpi-growth.down { color: #ef4444; }

    .chart-card { background: #fff; border-radius: 12px; border: 1px solid #f0f0f0; padding: 20px 24px; }
    .chart-card-title { font-size: 14px; font-weight: 600; color: #0f1d35; margin-bottom: 4px; }

    .filter-bar { background: #fff; border-radius: 12px; padding: 16px 20px; border: 1px solid #f0f0f0; margin-bottom: 20px; }
    .filter-bar .form-select { font-size: 13px; border-radius: 8px; border: 1px solid #e5e7eb; padding: 6px 12px; height: auto; }

    /* Funnel */
    .funnel-wrap { display: flex; flex-direction: column; gap: 6px; margin-top: 12px; }
    .funnel-row { display: flex; align-items: center; gap: 12px; }
    .funnel-bar-wrap { flex: 1; position: relative; height: 36px; display: flex; align-items: center; }
    .funnel-bar { height: 36px; border-radius: 4px; display: flex; align-items: center; padding-left: 12px; font-size: 12px; font-weight: 600; color: #fff; transition: width 0.5s; }
    .funnel-label { font-size: 12px; color: #374151; width: 90px; flex-shrink: 0; }
    .funnel-count { font-size: 12px; color: #6b7280; width: 90px; text-align: right; }
    .funnel-arrow { text-align: center; font-size: 11px; color: #9ca3af; margin-left: 90px; padding: 2px 0; }
    .conversion-badge { background: #f0fdf4; color: #16a34a; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; border: 1px solid #bbf7d0; }

    /* Tables */
    .analytics-table { font-size: 13px; }
    .analytics-table th { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid #f0f0f0; padding: 8px 12px; }
    .analytics-table td { padding: 10px 12px; border-bottom: 1px solid #f9fafb; color: #374151; vertical-align: middle; }
    .analytics-table tbody tr:last-child td { border-bottom: none; }
    .progress-bar-inline { height: 6px; border-radius: 3px; background: #e5e7eb; overflow: hidden; }
    .progress-fill { height: 100%; border-radius: 3px; }

    /* Route bars */
    .route-item { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .route-name { font-size: 13px; color: #374151; width: 160px; flex-shrink: 0; }
    .route-bar-wrap { flex: 1; }
    .route-bar { height: 8px; border-radius: 4px; background: #3b82f6; }
    .route-amount { font-size: 12px; color: #6b7280; width: 130px; text-align: right; flex-shrink: 0; }

    /* Deal closed table */
    .deal-row td { font-size: 12px; }
    .badge-status { font-size: 11px; padding: 2px 8px; border-radius: 20px; font-weight: 600; }
    .badge-won { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .badge-inprogress { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .badge-lost { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
</style>
@endpush

@section('content')
<!-- Header -->
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f1d35">Analytics</h4>
        <p class="text-muted mb-0" style="font-size:13px">Monitor performa bisnis dan penjualan secara real-time</p>
    </div>
    <div class="d-flex gap-2 align-items-center">
        <button class="btn btn-light btn-sm d-flex align-items-center gap-1" style="font-size:13px; border:1px solid #e5e7eb; border-radius:8px;">
            <i class="fas fa-calendar-alt text-muted"></i>
            <span>01 Mei 2025 – 31 Mei 2025</span>
            <i class="fas fa-chevron-down text-muted ms-1" style="font-size:10px"></i>
        </button>
        <select class="form-select form-select-sm" style="width:140px; font-size:13px; border-radius:8px;">
            <option>Semua Sales</option>
            <option>Budi Santoso</option>
            <option>Rina Anita</option>
        </select>
        <select class="form-select form-select-sm" style="width:150px; font-size:13px; border-radius:8px;">
            <option>Semua Customer</option>
        </select>
        <select class="form-select form-select-sm" style="width:150px; font-size:13px; border-radius:8px;">
            <option>Semua Service</option>
            <option>Sea Freight</option>
            <option>Air Freight</option>
            <option>Trucking</option>
        </select>
        <button class="btn btn-primary btn-sm" style="border-radius:8px; font-size:13px;">
            <i class="fas fa-filter me-1"></i> Filter
        </button>
    </div>
</div>

<!-- KPI Row -->
<div class="row g-3 mb-4">
    <div class="col">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#eff6ff">
                    <i class="fas fa-dollar-sign" style="color:#3b82f6"></i>
                </div>
                <div>
                    <div class="kpi-label">Revenue (Omzet)</div>
                    <div class="kpi-value" style="font-size:18px">Rp 2.450.000.000</div>
                    <span class="kpi-growth up"><i class="fas fa-arrow-up" style="font-size:10px"></i> 18.6% vs Apr 2025</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#f0fdf4">
                    <i class="fas fa-chart-line" style="color:#10b981"></i>
                </div>
                <div>
                    <div class="kpi-label">Gross Profit</div>
                    <div class="kpi-value" style="font-size:18px">Rp 785.000.000</div>
                    <span class="kpi-growth up"><i class="fas fa-arrow-up" style="font-size:10px"></i> 20.4% vs Apr 2025</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#faf5ff">
                    <i class="fas fa-wallet" style="color:#7c3aed"></i>
                </div>
                <div>
                    <div class="kpi-label">Nett Profit</div>
                    <div class="kpi-value" style="font-size:18px">Rp 472.000.000</div>
                    <span class="kpi-growth up"><i class="fas fa-arrow-up" style="font-size:10px"></i> 22.1% vs Apr 2025</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#fff7ed">
                    <i class="fas fa-briefcase" style="color:#f97316"></i>
                </div>
                <div>
                    <div class="kpi-label">Total Deals Closed</div>
                    <div class="kpi-value">86</div>
                    <span class="kpi-growth up"><i class="fas fa-arrow-up" style="font-size:10px"></i> 16.2% vs Apr 2025</span>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="d-flex align-items-center gap-3">
                <div class="kpi-icon" style="background:#fef9c3">
                    <i class="fas fa-bullseye" style="color:#ca8a04"></i>
                </div>
                <div>
                    <div class="kpi-label">Conversion Rate</div>
                    <div class="kpi-value">34.7%</div>
                    <span class="kpi-growth up"><i class="fas fa-arrow-up" style="font-size:10px"></i> 4.8% vs Apr 2025</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Row 2: Revenue Trend + Pipeline Funnel + Sales Performance -->
<div class="row g-3 mb-3">
    <!-- Revenue & Profit Trend -->
    <div class="col-md-5">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Revenue & Profit Trend</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>Monthly</option>
                    <option>Weekly</option>
                </select>
            </div>
            <canvas id="revenueProfitChart" height="180"></canvas>
        </div>
    </div>

    <!-- Pipeline Conversion Funnel -->
    <div class="col-md-4">
        <div class="chart-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="chart-card-title">Pipeline Conversion</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                    <option>Last Month</option>
                </select>
            </div>
            <div class="funnel-wrap mt-2">
                @php
                $funnelStages = [
                    ['name'=>'Identifying','count'=>248,'pct'=>100,'color'=>'#3b82f6','width'=>'100%'],
                    ['name'=>'Approaching','count'=>132,'pct'=>53.2,'color'=>'#10b981','width'=>'82%'],
                    ['name'=>'Follow Up','count'=>86,'pct'=>34.7,'color'=>'#f59e0b','width'=>'64%'],
                    ['name'=>'Closing','count'=>46,'pct'=>18.5,'color'=>'#f97316','width'=>'46%'],
                    ['name'=>'Won (Success)','count'=>86,'pct'=>34.7,'color'=>'#16a34a','width'=>'34%'],
                ];
                @endphp
                @foreach($funnelStages as $i => $stage)
                    <div class="funnel-row">
                        <div class="funnel-label">{{ $stage['name'] }}</div>
                        <div class="funnel-bar-wrap">
                            <div class="funnel-bar" style="width:{{ $stage['width'] }}; background:{{ $stage['color'] }}">
                                {{ $stage['count'] }} ({{ $stage['pct'] }}%)
                            </div>
                        </div>
                    </div>
                    @if(!$last = ($i === count($funnelStages)-1))
                        <div class="funnel-arrow">↓</div>
                    @endif
                @endforeach
            </div>
            <div class="mt-3 text-center">
                <span style="font-size:12px; color:#6b7280">Conversion Rate</span>
                <div style="font-size:22px; font-weight:700; color:#16a34a">34.7%</div>
                <span class="kpi-growth up" style="font-size:12px"><i class="fas fa-arrow-up" style="font-size:10px"></i> 4.8% vs Apr 2025</span>
            </div>
        </div>
    </div>

    <!-- Sales Performance -->
    <div class="col-md-3">
        <div class="chart-card h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Sales Performance</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                </select>
            </div>
            <div style="font-size:11px; color:#9ca3af; font-weight:600; display:grid; grid-template-columns:1fr auto auto auto; gap:4px; padding-bottom:8px; border-bottom:1px solid #f0f0f0; margin-bottom:8px;">
                <span>Sales</span><span>Revenue</span><span>Deals</span><span>Conv.</span>
            </div>
            @php
            $salesPerf = [
                ['name'=>'Budi Santoso','revenue'=>'850M','deals'=>32,'conv'=>'40.5%','pct'=>85,'img'=>'BS'],
                ['name'=>'Rina Anita','revenue'=>'620M','deals'=>21,'conv'=>'35.6%','pct'=>62,'img'=>'RA'],
                ['name'=>'Dedi Suhendra','revenue'=>'450M','deals'=>15,'conv'=>'32.6%','pct'=>45,'img'=>'DS'],
                ['name'=>'Steven','revenue'=>'300M','deals'=>10,'conv'=>'28.2%','pct'=>30,'img'=>'SV'],
                ['name'=>'Fajar','revenue'=>'230M','deals'=>8,'conv'=>'25.3%','pct'=>23,'img'=>'FJ'],
            ];
            @endphp
            @foreach($salesPerf as $s)
            <div class="d-flex align-items-center gap-2 mb-2 pb-2" style="border-bottom:1px solid #f9fafb">
                <div style="width:28px;height:28px;border-radius:50%;background:#3b82f6;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;flex-shrink:0">{{ $s['img'] }}</div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:12px;font-weight:600;color:#0f1d35;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">{{ $s['name'] }}</div>
                    <div class="progress-bar-inline" style="margin-top:4px">
                        <div class="progress-fill" style="width:{{ $s['pct'] }}%; background:#3b82f6"></div>
                    </div>
                </div>
                <div style="font-size:11px;color:#374151;text-align:right;flex-shrink:0">
                    <div>Rp {{ $s['revenue'] }}</div>
                    <div style="color:#6b7280">{{ $s['deals'] }} | {{ $s['conv'] }}</div>
                </div>
            </div>
            @endforeach
            <a href="#" class="d-block text-center mt-2" style="font-size:12px;color:#3b82f6;text-decoration:none">Lihat Semua Sales Performance →</a>
        </div>
    </div>
</div>

<!-- Row 3: Revenue by Service + Revenue by Route + Top Customers -->
<div class="row g-3 mb-3">
    <!-- Revenue by Service Type -->
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Revenue by Service Type</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                </select>
            </div>
            <div class="d-flex align-items-center gap-3">
                <canvas id="serviceDonutChart" width="120" height="120" style="max-width:120px"></canvas>
                <div style="flex:1">
                    @php
                    $services = [
                        ['name'=>'Trucking','amount'=>'Rp 1.120.000.000','pct'=>'45.7%','color'=>'#3b82f6'],
                        ['name'=>'Sea Freight','amount'=>'Rp 880.000.000','pct'=>'35.9%','color'=>'#10b981'],
                        ['name'=>'Air Freight','amount'=>'Rp 450.000.000','pct'=>'18.4%','color'=>'#f59e0b'],
                    ];
                    @endphp
                    @foreach($services as $sv)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:10px;height:10px;border-radius:2px;background:{{ $sv['color'] }};flex-shrink:0"></div>
                        <div style="flex:1">
                            <div style="font-size:12px;font-weight:600;color:#374151">{{ $sv['name'] }}</div>
                            <div style="font-size:11px;color:#6b7280">{{ $sv['amount'] }}</div>
                        </div>
                        <span style="font-size:12px;font-weight:600;color:#0f1d35">{{ $sv['pct'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue by Route -->
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Revenue by Route (Top 5)</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                </select>
            </div>
            @php
            $routes = [
                ['route'=>'Jakarta – Surabaya','amount'=>'Rp 620.000.000','pct'=>100],
                ['route'=>'Surabaya – Jakarta','amount'=>'Rp 480.000.000','pct'=>77],
                ['route'=>'Jakarta – Medan','amount'=>'Rp 320.000.000','pct'=>52],
                ['route'=>'Jakarta – Makassar','amount'=>'Rp 280.000.000','pct'=>45],
                ['route'=>'Jakarta – Balikpapan','amount'=>'Rp 210.000.000','pct'=>34],
            ];
            @endphp
            @foreach($routes as $r)
            <div class="route-item">
                <div class="route-name">{{ $r['route'] }}</div>
                <div class="route-bar-wrap">
                    <div class="route-bar" style="width:{{ $r['pct'] }}%"></div>
                </div>
                <div class="route-amount">{{ $r['amount'] }}</div>
            </div>
            @endforeach
        </div>
    </div>

    <!-- Top Customers -->
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Top Customers by Revenue</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                </select>
            </div>
            <table class="table analytics-table mb-0">
                <thead>
                    <tr>
                        <th>Customer</th>
                        <th>Revenue</th>
                        <th>Deals</th>
                        <th>Repeat</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $topCust = [
                        ['name'=>'PT. Maju Bersama','rev'=>'Rp 320.000.000','deals'=>6,'repeat'=>true],
                        ['name'=>'PT. Global Indo','rev'=>'Rp 280.000.000','deals'=>5,'repeat'=>true],
                        ['name'=>'PT. Prima Sukses','rev'=>'Rp 220.000.000','deals'=>4,'repeat'=>true],
                        ['name'=>'PT. Samudera Indonesia','rev'=>'Rp 210.000.000','deals'=>4,'repeat'=>false],
                        ['name'=>'PT. Damai Sejahtera','rev'=>'Rp 180.000.000','deals'=>3,'repeat'=>true],
                    ];
                    @endphp
                    @foreach($topCust as $c)
                    <tr>
                        <td style="font-size:12px;font-weight:600">{{ $c['name'] }}</td>
                        <td style="font-size:12px;white-space:nowrap">{{ $c['rev'] }}</td>
                        <td style="font-size:12px;text-align:center">{{ $c['deals'] }}</td>
                        <td>
                            @if($c['repeat'])
                                <span class="badge-status badge-won">Yes</span>
                            @else
                                <span class="badge-status badge-lost">No</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            <a href="#" class="d-block text-center mt-2" style="font-size:12px;color:#3b82f6;text-decoration:none">Lihat Semua Customer →</a>
        </div>
    </div>
</div>

<!-- Row 4: Profit Analysis + Lead Source + Recent Deals Closed -->
<div class="row g-3">
    <!-- Profit Analysis -->
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Profit Analysis</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                </select>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-4">
                    <div style="background:#f9fafb; border-radius:8px; padding:10px; text-align:center">
                        <div style="font-size:11px; color:#6b7280; margin-bottom:2px">Avg Profit Margin</div>
                        <div style="font-size:18px; font-weight:700; color:#10b981">19.3%</div>
                        <div style="font-size:11px; color:#10b981"><i class="fas fa-arrow-up" style="font-size:9px"></i> 3.2%</div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="background:#f9fafb; border-radius:8px; padding:10px; text-align:center">
                        <div style="font-size:11px; color:#6b7280; margin-bottom:2px">Avg Profit/Deal</div>
                        <div style="font-size:14px; font-weight:700; color:#0f1d35">Rp 5,4M</div>
                        <div style="font-size:11px; color:#10b981"><i class="fas fa-arrow-up" style="font-size:9px"></i> 8.1%</div>
                    </div>
                </div>
                <div class="col-4">
                    <div style="background:#f9fafb; border-radius:8px; padding:10px; text-align:center">
                        <div style="font-size:11px; color:#6b7280; margin-bottom:2px">Highest Deal</div>
                        <div style="font-size:14px; font-weight:700; color:#3b82f6">Rp 25M</div>
                        <div style="font-size:10px; color:#3b82f6">PT. Maju Bersama</div>
                    </div>
                </div>
            </div>
            <canvas id="profitAnalysisChart" height="160"></canvas>
        </div>
    </div>

    <!-- Lead Source Performance -->
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Lead Source Performance</div>
                <select class="form-select form-select-sm" style="width:110px; font-size:12px; border-radius:8px;">
                    <option>This Month</option>
                </select>
            </div>
            <div class="d-flex align-items-center gap-3">
                <canvas id="leadSourceChart" width="130" height="130" style="max-width:130px"></canvas>
                <div style="flex:1">
                    @php
                    $sources = [
                        ['name'=>'Referral','count'=>104,'pct'=>'41.9%','color'=>'#3b82f6'],
                        ['name'=>'Website','count'=>68,'pct'=>'27.4%','color'=>'#10b981'],
                        ['name'=>'Cold Call','count'=>45,'pct'=>'18.1%','color'=>'#f59e0b'],
                        ['name'=>'Email Campaign','count'=>21,'pct'=>'8.5%','color'=>'#f97316'],
                        ['name'=>'Lainnya','count'=>10,'pct'=>'4.0%','color'=>'#9ca3af'],
                    ];
                    @endphp
                    @foreach($sources as $src)
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div style="width:10px;height:10px;border-radius:2px;background:{{ $src['color'] }};flex-shrink:0"></div>
                        <div style="flex:1; font-size:12px; color:#374151">{{ $src['name'] }}</div>
                        <span style="font-size:12px; font-weight:600; color:#0f1d35">{{ $src['count'] }}</span>
                        <span style="font-size:11px; color:#6b7280">{{ $src['pct'] }}</span>
                    </div>
                    @endforeach
                    <div class="d-flex gap-3 mt-2 pt-2" style="border-top:1px solid #f0f0f0">
                        <div>
                            <div style="font-size:11px;color:#6b7280">Total Leads</div>
                            <div style="font-size:16px;font-weight:700;color:#0f1d35">248</div>
                        </div>
                        <div>
                            <div style="font-size:11px;color:#6b7280">Cost per Lead</div>
                            <div style="font-size:16px;font-weight:700;color:#0f1d35">Rp 28.226</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Deals Closed -->
    <div class="col-md-4">
        <div class="chart-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="chart-card-title">Recent Deals Closed</div>
            </div>
            @php
            $recentDeals = [
                ['customer'=>'PT. Maju Bersama','service'=>'Import Sea Freight','sales'=>'Budi Santoso','value'=>'Rp 120.000.000','date'=>'30 Mei 2025'],
                ['customer'=>'PT. Global Indo','service'=>'Trucking Domestic','sales'=>'Rina Anita','value'=>'Rp 95.000.000','date'=>'29 Mei 2025'],
                ['customer'=>'PT. Prima Sukses','service'=>'Import Air Freight','sales'=>'Dedi Suhendra','value'=>'Rp 85.000.000','date'=>'28 Mei 2025'],
                ['customer'=>'PT. Damai Sejahtera','service'=>'Trucking Domestic','sales'=>'Steven','value'=>'Rp 70.000.000','date'=>'27 Mei 2025'],
                ['customer'=>'PT. Mitra Mandiri','service'=>'Export Sea Freight','sales'=>'Fajar','value'=>'Rp 65.000.000','date'=>'26 Mei 2025'],
            ];
            @endphp
            @foreach($recentDeals as $deal)
            <div class="d-flex align-items-start gap-2 mb-3 pb-3" style="border-bottom:1px solid #f9fafb">
                <div style="width:32px;height:32px;border-radius:8px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                    <i class="fas fa-trophy" style="color:#16a34a;font-size:13px"></i>
                </div>
                <div style="flex:1;min-width:0">
                    <div style="font-size:13px;font-weight:600;color:#0f1d35">{{ $deal['customer'] }}</div>
                    <div style="font-size:11px;color:#6b7280">{{ $deal['service'] }} · {{ $deal['sales'] }}</div>
                </div>
                <div style="text-align:right;flex-shrink:0">
                    <div style="font-size:12px;font-weight:600;color:#0f1d35">{{ $deal['value'] }}</div>
                    <div style="font-size:11px;color:#9ca3af">{{ $deal['date'] }}</div>
                </div>
            </div>
            @endforeach
            <a href="#" class="d-block text-center" style="font-size:12px;color:#3b82f6;text-decoration:none">Lihat Semua Deals Closed →</a>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue & Profit Trend Chart
    const months = ['Des 2024','Jan 2025','Feb 2025','Mar 2025','Apr 2025','Mei 2025'];
    new Chart(document.getElementById('revenueProfitChart'), {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue (Omzet)',
                    data: [620, 710, 810, 1020, 1160, 1250],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59,130,246,0.08)',
                    fill: true,
                    tension: 0.4,
                    pointRadius: 4,
                    borderWidth: 2,
                },
                {
                    label: 'Nett Profit',
                    data: [180, 210, 260, 340, 390, 472],
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    fill: false,
                    tension: 0.4,
                    pointRadius: 4,
                    borderWidth: 2,
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 11 }, boxWidth: 12 } }
            },
            scales: {
                y: {
                    ticks: { font: { size: 10 }, callback: v => v + 'M' },
                    grid: { color: '#f3f4f6' }
                },
                x: { ticks: { font: { size: 10 } }, grid: { display: false } }
            }
        }
    });

    // Service Donut
    new Chart(document.getElementById('serviceDonutChart'), {
        type: 'doughnut',
        data: {
            labels: ['Trucking','Sea Freight','Air Freight'],
            datasets: [{
                data: [45.7, 35.9, 18.4],
                backgroundColor: ['#3b82f6','#10b981','#f59e0b'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            cutout: '68%',
            plugins: { legend: { display: false } }
        }
    });

    // Lead Source Donut
    new Chart(document.getElementById('leadSourceChart'), {
        type: 'doughnut',
        data: {
            labels: ['Referral','Website','Cold Call','Email','Lainnya'],
            datasets: [{
                data: [41.9, 27.4, 18.1, 8.5, 4.0],
                backgroundColor: ['#3b82f6','#10b981','#f59e0b','#f97316','#9ca3af'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            cutout: '60%',
            plugins: { legend: { display: false } }
        }
    });

    // Profit Analysis Chart
    new Chart(document.getElementById('profitAnalysisChart'), {
        type: 'bar',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Revenue',
                    data: [620, 710, 810, 1020, 1160, 1250],
                    backgroundColor: '#3b82f6',
                    borderRadius: 4,
                    barPercentage: 0.5
                },
                {
                    label: 'Cost',
                    data: [440, 500, 550, 680, 770, 778],
                    backgroundColor: '#fca5a5',
                    borderRadius: 4,
                    barPercentage: 0.5
                },
                {
                    label: 'Profit',
                    type: 'line',
                    data: [180, 210, 260, 340, 390, 472],
                    borderColor: '#10b981',
                    backgroundColor: 'transparent',
                    pointRadius: 4,
                    borderWidth: 2,
                    tension: 0.4
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top', labels: { font: { size: 10 }, boxWidth: 10 } }
            },
            scales: {
                y: { ticks: { font: { size: 10 }, callback: v => v + 'M' }, grid: { color: '#f3f4f6' } },
                x: { ticks: { font: { size: 9 } }, grid: { display: false } }
            }
        }
    });
});
</script>
@endpush
