@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-subtitle', 'Laporan data bisnis dalam bentuk detail dan siap di-export')

@push('styles')
<style>
.report-tab { padding:8px 16px;border-radius:20px;border:1px solid #e5e7eb;font-size:13px;font-weight:500;cursor:pointer;background:#fff;color:#6b7280;text-decoration:none;transition:.15s; }
.report-tab.active { background:#0f1d35;color:#fff;border-color:#0f1d35; }
.report-tab:hover:not(.active) { background:#f9fafb;color:#374151; }
.summary-card { background:#fff;border-radius:10px;border:1px solid #f0f0f0;padding:16px 18px; }
.report-table { font-size:13px; }
.report-table th { font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;border-bottom:2px solid #f0f0f0;background:#f9fafb;white-space:nowrap; }
.report-table td { padding:11px 12px;border-bottom:1px solid #f9fafb;color:#374151;vertical-align:middle; }
.report-table tr:hover td { background:#fafbfc; }
</style>
@endpush

@section('content')

{{-- Filter --}}
<form method="GET" action="{{ route('reports.index') }}" id="reportForm">
<div class="row g-3 mb-4">
    <div class="col-md-9">
        <div class="card">
            <div class="card-body p-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="start_date" class="form-control" value="{{ $startDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control" value="{{ $endDate }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Sales PIC</label>
                        <select name="sales_user_id" class="form-select">
                            <option value="">Semua Sales</option>
                            @foreach($salesUsers as $su)
                            <option value="{{ $su->id }}" @selected($salesId==$su->id)>{{ $su->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status / Stage</label>
                        <select name="status" class="form-select">
                            <option value="">Semua Status</option>
                            @if(in_array($reportType, ['sales','pipeline']))
                                @foreach(['Identifying','Approaching','Follow Up','Closing','Won','Lost'] as $s)
                                <option value="{{ $s }}" @selected($status==$s)>{{ $s }}</option>
                                @endforeach
                            @elseif($reportType === 'do')
                                @foreach(['Pending','In Progress','Done','Cancelled'] as $s)
                                <option value="{{ $s }}" @selected($status==$s)>{{ $s }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Cari nama company, nomor DO..." value="{{ $search }}">
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search me-1"></i> Generate</button>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="{{ route('reports.index') }}" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-undo me-1"></i> Reset
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card h-100">
            <div class="card-body p-3">
                <div style="font-size:13px;font-weight:600;color:#0f1d35;margin-bottom:10px">Export Report</div>
                <a href="{{ route('reports.export', array_merge(request()->query(), ['report_type'=>$reportType])) }}"
                    class="d-flex align-items-center gap-2 p-2 mb-2" style="border:1px solid #bbf7d0;border-radius:8px;text-decoration:none;color:#16a34a;background:#f0fdf4;font-size:13px;font-weight:500">
                    <i class="fas fa-file-csv" style="font-size:16px"></i> Export CSV
                </a>
                <button type="button" onclick="window.print()"
                    class="d-flex align-items-center gap-2 p-2 w-100" style="border:1px solid #bfdbfe;border-radius:8px;color:#2563eb;background:#eff6ff;font-size:13px;font-weight:500;cursor:pointer">
                    <i class="fas fa-print" style="font-size:16px"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Report Type Tabs --}}
<div class="d-flex gap-2 mb-4 flex-wrap">
    @foreach(['sales'=>'Sales Report','customer'=>'Customer Report','pipeline'=>'Pipeline Report','performance'=>'Performance Report','do'=>'DO Report'] as $type => $label)
    <a href="{{ route('reports.index', array_merge(request()->except('report_type','page'), ['report_type'=>$type])) }}"
        class="report-tab {{ $reportType === $type ? 'active' : '' }}">
        {{ $label }}
    </a>
    @endforeach
</div>
</form>

{{-- Summary KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['bg'=>'#eff6ff','icon'=>'fas fa-chart-bar','color'=>'#3b82f6','label'=>'Total Revenue','value'=>idr($revenue)],
        ['bg'=>'#f0fdf4','icon'=>'fas fa-handshake','color'=>'#10b981','label'=>'Total Deals Won','value'=>$totalDeals],
        ['bg'=>'#fff7ed','icon'=>'fas fa-coins','color'=>'#f97316','label'=>'Avg Deal Value','value'=>idr($avgDealValue)],
        ['bg'=>'#faf5ff','icon'=>'fas fa-bullseye','color'=>'#7c3aed','label'=>'Conversion Rate','value'=>$conversionRate.'%'],
        ['bg'=>'#fef9c3','icon'=>'fas fa-trophy','color'=>'#ca8a04','label'=>'Win Rate','value'=>$winRate.'%'],
    ] as $k)
    <div class="col">
        <div class="summary-card d-flex align-items-center gap-3">
            <div style="width:42px;height:42px;border-radius:50%;background:{{ $k['bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0">
                <i class="{{ $k['icon'] }}" style="color:{{ $k['color'] }}"></i>
            </div>
            <div>
                <div style="font-size:12px;color:#6b7280">{{ $k['label'] }}</div>
                <div style="font-size:18px;font-weight:700;color:#0f1d35">{{ $k['value'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Report Table --}}
<div class="card">
    <div class="d-flex align-items-center justify-content-between p-3 pb-0">
        <div style="font-size:14px;font-weight:600;color:#0f1d35">
            @php $titles=['sales'=>'Sales Report Detail','customer'=>'Customer Report','pipeline'=>'Pipeline Report','performance'=>'Performance Report','do'=>'Delivery Order Report']; @endphp
            {{ $titles[$reportType] ?? 'Report Detail' }}
        </div>
        @if(method_exists($reportData,'total'))
        <div style="font-size:13px;color:#6b7280">{{ $reportData->total() }} data ditemukan</div>
        @endif
    </div>

    <div class="card-body p-0 mt-3">
        <div class="table-responsive">

        {{-- Sales Report --}}
        @if($reportType === 'sales')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>Tgl Dibuat</th><th>Company</th><th>PIC</th>
                <th>Sales PIC</th><th>Stage</th><th>Service</th><th>Route</th>
                <th>Potensi Revenue</th><th>Probability</th><th>Exp. Closing</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $lead)
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td style="font-size:12px;white-space:nowrap">{{ $lead->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" style="font-weight:600;color:#0f1d35;text-decoration:none;font-size:13px">{{ $lead->company_name }}</a>
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ $lead->pic_name }}</td>
                    <td style="font-size:12px">{{ $lead->salesUser?->name ?? '-' }}</td>
                    <td>
                        @php $stageMap=['Identifying'=>'identifying','Approaching'=>'approaching','Follow Up'=>'follow-up','Closing'=>'closing','Won'=>'won','Lost'=>'lost']; @endphp
                        <span class="badge-stage badge-{{ $stageMap[$lead->pipeline_stage]??'identifying' }}" style="font-size:11px">{{ $lead->pipeline_stage }}</span>
                    </td>
                    <td style="font-size:12px">{{ $lead->service_type ?? '-' }}</td>
                    <td style="font-size:12px">{{ $lead->route ?? '-' }}</td>
                    <td style="font-size:12px;font-weight:600;color:var(--primary)">{{ idrm($lead->potensi_revenue) }}</td>
                    <td style="font-size:12px;text-align:center">{{ $lead->probability ?? 0 }}%</td>
                    <td style="font-size:12px;color:#6b7280">{{ $lead->expected_closing?->format('d M Y') ?? '-' }}</td>
                </tr>
                @empty
                <tr><td colspan="11" class="text-center py-4" style="color:#9ca3af">
                    <i class="fas fa-inbox" style="font-size:2rem;display:block;margin-bottom:8px;opacity:.2"></i>
                    Tidak ada data untuk periode ini
                </td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Customer Report --}}
        @elseif($reportType === 'customer')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>Company</th><th>PIC</th><th>Phone</th>
                <th>Industry</th><th>Status</th><th>Sales PIC</th><th>Customer Since</th><th>Total Revenue</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $cust)
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td style="font-weight:600;font-size:13px">{{ $cust->company_name }}</td>
                    <td style="font-size:12px">{{ $cust->pic_name }}</td>
                    <td style="font-size:12px">{{ $cust->phone }}</td>
                    <td style="font-size:12px">{{ $cust->industry ?? '-' }}</td>
                    <td><span class="badge-{{ strtolower($cust->status) }}" style="font-size:11px">{{ $cust->status }}</span></td>
                    <td style="font-size:12px">{{ $cust->salesUser?->name ?? '-' }}</td>
                    <td style="font-size:12px;color:#6b7280">{{ $cust->customer_since?->format('d M Y') ?? '-' }}</td>
                    <td style="font-size:12px;font-weight:600;color:var(--primary)">{{ idrm($cust->total_revenue) }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center py-4" style="color:#9ca3af">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pipeline Report --}}
        @elseif($reportType === 'pipeline')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>Company</th><th>Stage</th><th>Temperature</th>
                <th>Service</th><th>Route</th><th>Revenue</th><th>Probability</th><th>Sales PIC</th><th>Last Updated</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $lead)
                @php $stageMap=['Identifying'=>'identifying','Approaching'=>'approaching','Follow Up'=>'follow-up','Closing'=>'closing','Won'=>'won','Lost'=>'lost']; @endphp
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" style="font-weight:600;color:#0f1d35;text-decoration:none;font-size:13px">{{ $lead->company_name }}</a>
                        <div style="font-size:11px;color:#6b7280">{{ $lead->pic_name }}</div>
                    </td>
                    <td><span class="badge-stage badge-{{ $stageMap[$lead->pipeline_stage]??'identifying' }}" style="font-size:11px">{{ $lead->pipeline_stage }}</span></td>
                    <td><span class="badge-{{ strtolower($lead->temperature) }}" style="font-size:11px">{{ $lead->temperature }}</span></td>
                    <td style="font-size:12px">{{ $lead->service_type ?? '-' }}</td>
                    <td style="font-size:12px">{{ $lead->route ?? '-' }}</td>
                    <td style="font-size:12px;font-weight:600;color:var(--primary)">{{ idrm($lead->potensi_revenue) }}</td>
                    <td style="font-size:12px;text-align:center">{{ $lead->probability ?? 0 }}%</td>
                    <td style="font-size:12px">{{ $lead->salesUser?->name ?? '-' }}</td>
                    <td style="font-size:12px;color:#6b7280">{{ $lead->updated_at->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center py-4" style="color:#9ca3af">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- Performance Report --}}
        @elseif($reportType === 'performance')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>Sales PIC</th><th>Role</th><th>Total Leads</th>
                <th>Deals Won</th><th>Conversion Rate</th><th>Revenue (Won)</th><th>Progress Target</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $row)
                @php
                $target = $row['sales']->target ?? 500000000;
                $pct = $target > 0 ? min(round(($row['revenue']/$target)*100), 100) : 0;
                $barColor = $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#f59e0b' : '#ef4444');
                @endphp
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $i + 1 }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="user-avatar" style="width:28px;height:28px;font-size:.62rem">{{ strtoupper(substr($row['sales']->name,0,2)) }}</div>
                            <span style="font-weight:600;font-size:13px">{{ $row['sales']->name }}</span>
                        </div>
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ $row['sales']->role ?? '-' }}</td>
                    <td style="font-size:13px;font-weight:600;text-align:center">{{ $row['total'] }}</td>
                    <td style="font-size:13px;font-weight:600;text-align:center;color:#16a34a">{{ $row['won'] }}</td>
                    <td style="font-size:13px;font-weight:600;text-align:center">{{ $row['conversion'] }}%</td>
                    <td style="font-size:12px;font-weight:600;color:var(--primary)">{{ idrm($row['revenue']) }}</td>
                    <td style="min-width:120px">
                        <div style="font-size:11px;color:#6b7280;margin-bottom:3px">{{ $pct }}% dari {{ idrm($target) }}</div>
                        <div style="background:#e5e7eb;border-radius:3px;height:5px">
                            <div style="width:{{ $pct }}%;height:5px;border-radius:3px;background:{{ $barColor }}"></div>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-4" style="color:#9ca3af">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>

        {{-- DO Report --}}
        @elseif($reportType === 'do')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>No. DO</th><th>Customer</th><th>Vendor</th>
                <th>Service Type</th><th>Route</th><th>Amount</th><th>Currency</th><th>Status</th><th>Tgl Order</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $do)
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td style="font-weight:600;font-size:12px">{{ $do->do_number }}</td>
                    <td style="font-size:12px">{{ $do->customer?->company_name ?? '-' }}</td>
                    <td style="font-size:12px;color:#6b7280">{{ $do->vendor?->vendor_name ?? '-' }}</td>
                    <td style="font-size:12px">{{ $do->service_type }}</td>
                    <td style="font-size:12px">{{ $do->route }}</td>
                    <td style="font-size:12px;font-weight:600;color:var(--primary)">{{ idrm($do->amount) }}</td>
                    <td style="font-size:12px">{{ $do->currency }}</td>
                    <td>
                        @php $sc=['Done'=>'badge-won','In Progress'=>'badge-follow-up','Pending'=>'badge-approaching','Cancelled'=>'badge-lost']; @endphp
                        <span class="{{ $sc[$do->status]??'badge-stage' }}" style="font-size:11px">{{ $do->status }}</span>
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($do->order_date)->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="10" class="text-center py-4" style="color:#9ca3af">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
        @endif

        </div>
    </div>

    {{-- Pagination --}}
    @if(isset($reportData) && method_exists($reportData, 'links') && $reportData->hasPages())
    <div class="card-footer p-3 d-flex justify-content-between align-items-center">
        <span style="font-size:13px;color:#6b7280">
            Showing {{ $reportData->firstItem() }}–{{ $reportData->lastItem() }} of {{ $reportData->total() }}
        </span>
        {{ $reportData->withQueryString()->links('pagination::bootstrap-5') }}
    </div>
    @endif

    {{-- Disclaimer --}}
    <div class="p-3 pt-0">
        <p style="font-size:12px;color:#9ca3af;margin:0">
            <i class="fas fa-info-circle me-1"></i>
            Data berdasarkan filter periode <strong>{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong>
            s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong>.
        </p>
    </div>
</div>

@endsection
