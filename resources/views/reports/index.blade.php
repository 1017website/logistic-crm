@extends('layouts.app')
@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-subtitle', 'Laporan data bisnis dalam bentuk detail dan siap di-export')

@push('styles')
<style>
.report-tab { padding:8px 16px;border-radius:20px;border:1px solid #e5e7eb;font-size:13px;font-weight:500;cursor:pointer;background:#fff;color:#6b7280;text-decoration:none;transition:.15s; }
.report-tab.active { background:#111827;color:#fff;border-color:#111827; }
.report-tab:hover:not(.active) { background:#f9fafb;color:#374151; }
.summary-card { background:#fff;border-radius:10px;border:1px solid #f0f0f0;padding:16px 18px; }
.report-table { font-size:13px; }
.report-table th { font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px;border-bottom:2px solid #f0f0f0;background:#f9fafb;white-space:nowrap; }
.report-table td { padding:11px 12px;border-bottom:1px solid #f9fafb;color:#374151;vertical-align:middle; }
.report-table tr:hover td { background:#fafbfc; }

/* ── PRINT STYLES ── */
.print-header { display: none; }
.print-kpi-grid { display: none; }

@media print {
    /* Sembunyikan semua elemen UI */
    .sidebar, nav.sidebar,
    .topbar, header,
    .report-filters-section,
    .report-tabs-section,
    .export-section,
    .pagination,
    nav[aria-label="pagination"],
    .card-footer,
    a.report-tab,
    button { display: none !important; }

    /* Tampilkan print-only elements */
    .print-header   { display: block !important; }
    .print-kpi-grid { display: grid !important; }

    /* Reset body & layout */
    * { -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important; }
    html, body { background: #fff !important; font-family: Arial, sans-serif !important; font-size: 10pt; }
    body > * { margin: 0 !important; padding: 0 !important; }
    .main-content, main, .content-wrapper, #app { margin: 0 !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }

    /* Sembunyikan summary cards bawaan (kita ganti dengan print-kpi-grid) */
    .row.g-3.mb-4 { display: none !important; }

    /* Card wrapper */
    .card { border: none !important; box-shadow: none !important; margin: 0 !important; }
    .card-body { padding: 0 !important; }
    .table-responsive { overflow: visible !important; }

    /* Tabel */
    .report-table {
        width: 100% !important;
        border-collapse: collapse !important;
        font-size: 8pt !important;
        margin-top: 6px !important;
    }
    .report-table thead tr { background: #1e3a5f !important; }
    .report-table th {
        background: #1e3a5f !important;
        color: #fff !important;
        padding: 5px 6px !important;
        font-size: 7.5pt !important;
        font-weight: 700 !important;
        border: 1px solid #1e3a5f !important;
        white-space: nowrap;
    }
    .report-table td {
        padding: 4px 6px !important;
        border: 1px solid #d1d5db !important;
        font-size: 8pt !important;
        vertical-align: middle !important;
        color: #111 !important;
    }
    .report-table tr:nth-child(even) td { background: #f9fafb !important; }
    .report-table a { color: #111827 !important; text-decoration: none !important; font-weight: 600; }

    /* Badges */
    span[class*="badge"] {
        border: 1px solid #ccc !important;
        border-radius: 4px !important;
        padding: 1px 5px !important;
        font-size: 7pt !important;
        font-weight: 600 !important;
        background: #f3f4f6 !important;
        color: #374151 !important;
    }

    /* Disclaimer */
    .p-3.pt-0 { padding: 4px 0 0 0 !important; }

    @page { size: A4 landscape; margin: 1.2cm 1cm; }
}

/* ── KPI grid khusus print ── */
.print-kpi-grid {
    display: none;
    grid-template-columns: repeat(6, 1fr);
    gap: 8px;
    margin-bottom: 12px;
}
.print-kpi-grid .pkpi {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    padding: 8px 10px;
    text-align: center;
}
.print-kpi-grid .pkpi-label { font-size: 8pt; color: #6b7280; margin-bottom: 2px; }
.print-kpi-grid .pkpi-value { font-size: 11pt; font-weight: 700; color: #111827; }
</style>
@endpush

@section('content')

{{-- Filter --}}
<form method="GET" action="{{ route('reports.index') }}" id="reportForm" class="report-filters-section">
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
                        <select name="user_id" class="form-select">
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
                                @foreach(['Identifying','Approaching','Follow Up','Won','Lost','Maintaining'] as $s)
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
                        <input type="text" name="search" class="form-control" placeholder="Cari nama company, nomor PO..." value="{{ $search }}">
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
    <div class="col-md-3 export-section">
        <div class="card h-100">
            <div class="card-body p-3">
                <div style="font-size:13px;font-weight:600;color:#111827;margin-bottom:10px">Export Report</div>
                <a href="{{ route('reports.export', array_merge(request()->query(), ['report_type'=>$reportType])) }}"
                    class="d-flex align-items-center gap-2 p-2 mb-2" style="border:1px solid #bbf7d0;border-radius:8px;text-decoration:none;color:#16a34a;background:#f0fdf4;font-size:13px;font-weight:500">
                    <i class="fas fa-file-excel" style="font-size:16px"></i> Export Excel
                </a>
                <button type="button" onclick="window.print()"
                    class="d-flex align-items-center gap-2 p-2 w-100" style="border:1px solid #d4d4d4;border-radius:8px;color:#111111;background:#f2f2f2;font-size:13px;font-weight:500;cursor:pointer">
                    <i class="fas fa-print" style="font-size:16px"></i> Print Report
                </button>
            </div>
        </div>
    </div>
</div>

{{-- Report Type Tabs --}}
<div class="d-flex gap-2 mb-4 flex-wrap report-tabs-section">
    @foreach(['sales'=>'Sales Report','customer'=>'Customer Report','pipeline'=>'Pipeline Report','performance'=>'Performance Report','po'=>'PO Report'] as $type => $label)
    <a href="{{ route('reports.index', array_merge(request()->except('report_type','page'), ['report_type'=>$type])) }}"
        class="report-tab {{ $reportType === $type ? 'active' : '' }}">
        {{ $label }}
    </a>
    @endforeach
</div>
</form>

{{-- Print Header (hanya muncul saat print) --}}
<div class="print-header" style="margin-bottom:10px;padding-bottom:10px;border-bottom:2px solid #1e3a5f">
    <div style="display:flex;justify-content:space-between;align-items:center">
        <div style="display:flex;align-items:center;gap:12px">
            <div style="width:42px;height:42px;background:#1e3a5f;border-radius:8px;display:flex;align-items:center;justify-content:center">
                <span style="color:#fff;font-weight:800;font-size:14pt">C</span>
            </div>
            <div>
                <div style="font-size:14pt;font-weight:700;color:#1e3a5f">{{ \App\Models\Setting::get('company_name', 'Logistic CRM') }}</div>
                <div style="font-size:9pt;color:#6b7280">Laporan: <strong>{{ ['sales'=>'Sales Report','customer'=>'Customer Report','pipeline'=>'Pipeline Report','performance'=>'Performance Report','po'=>'PO Report'][$reportType] ?? $reportType }}</strong></div>
            </div>
        </div>
        <div style="text-align:right;font-size:9pt;color:#6b7280;line-height:1.6">
            <div>Periode: <strong>{{ \Carbon\Carbon::parse($startDate)->format('d M Y') }}</strong> s/d <strong>{{ \Carbon\Carbon::parse($endDate)->format('d M Y') }}</strong></div>
            <div>Dicetak: {{ now()->format('d M Y, H:i') }}</div>
        </div>
    </div>
</div>

{{-- Print KPI Grid (hanya muncul saat print, menggantikan summary cards) --}}
<div class="print-kpi-grid">
    <div class="pkpi"><div class="pkpi-label">Total Revenue</div><div class="pkpi-value">{{ idrm($revenue) }}</div></div>
    <div class="pkpi"><div class="pkpi-label">Gross Profit</div><div class="pkpi-value">{{ idrm($grossProfit ?? 0) }}</div></div>
    <div class="pkpi"><div class="pkpi-label">Nett Profit</div><div class="pkpi-value">{{ idrm($nettProfit ?? 0) }}</div></div>
    <div class="pkpi"><div class="pkpi-label">Avg Deal Value</div><div class="pkpi-value">{{ idrm($avgDealValue) }}</div></div>
    <div class="pkpi"><div class="pkpi-label">Conversion Rate</div><div class="pkpi-value">{{ $conversionRate }}%</div></div>
    <div class="pkpi"><div class="pkpi-label">Win Rate</div><div class="pkpi-value">{{ $winRate }}%</div></div>
</div>

{{-- Summary KPI --}}
<div class="row g-3 mb-4">
    @foreach([
        ['bg'=>'#f2f2f2','icon'=>'fas fa-chart-bar','color'=>'#111111','label'=>'Total Revenue','value'=>idr($revenue)],
        ['bg'=>'#f0fdf4','icon'=>'fas fa-chart-line','color'=>'#10b981','label'=>'Gross Profit','value'=>idr($grossProfit ?? 0)],
        ['bg'=>'#faf5ff','icon'=>'fas fa-wallet','color'=>'#7c3aed','label'=>'Nett Profit','value'=>idr($nettProfit ?? 0)],
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
                <div style="font-size:18px;font-weight:700;color:#111827">{{ $k['value'] }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

{{-- Report Table --}}
<div class="card">
    <div class="d-flex align-items-center justify-content-between p-3 pb-0">
        <div style="font-size:14px;font-weight:600;color:#111827">
            @php $titles=['sales'=>'Sales Report Detail','customer'=>'Customer Report','pipeline'=>'Pipeline Report','performance'=>'Performance Report','po'=>'PO Report']; @endphp
            {{ $titles[$reportType] ?? 'Report Detail' }}
        </div>
        @if(method_exists($reportData,'total'))
        <div style="font-size:13px;color:#6b7280">{{ $reportData->total() }} data ditemukan</div>
        @endif
    </div>

    <div class="card-body p-0 mt-3">
        {{-- Section title saat print --}}
        <div class="print-header" style="font-size:10pt;font-weight:700;color:#1e3a5f;margin-bottom:4px;padding-bottom:4px;border-bottom:1px solid #e5e7eb">
            {{ ['sales'=>'Sales Report Detail','customer'=>'Customer Report','pipeline'=>'Pipeline Report','performance'=>'Performance Report','po'=>'PO Report'][$reportType] ?? 'Report Detail' }}
            @if(method_exists($reportData,'total'))
            <span style="font-size:9pt;color:#6b7280;font-weight:400"> — {{ $reportData->total() }} data</span>
            @endif
        </div>
        <div class="table-responsive">

        {{-- Sales Report --}}
        @if($reportType === 'sales')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>Tgl Dibuat</th><th>Company</th><th>PIC</th>
                <th>Sales PIC</th><th>Stage</th><th>Service</th><th>Volume Est.</th>
                <th>Potensi Revenue</th><th>Probability</th><th>Exp. Closing</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $lead)
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td style="font-size:12px;white-space:nowrap">{{ $lead->created_at->format('d M Y') }}</td>
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" style="font-weight:600;color:#111827;text-decoration:none;font-size:13px">{{ $lead->company_name }}</a>
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ $lead->pic_name }}</td>
                    <td style="font-size:12px">{{ $lead->salesUser?->name ?? '-' }}</td>
                    <td>
                        @php $stageMap=['Identifying'=>'identifying','Approaching'=>'approaching','Follow Up'=>'follow-up','Won'=>'won','Lost'=>'lost']; @endphp
                        <span class="badge-stage badge-{{ $stageMap[$lead->pipeline_stage]??'identifying' }}" style="font-size:11px">{{ $lead->pipeline_stage }}</span>
                    </td>
                    <td style="font-size:12px">{{ $lead->product_interest ?? "-" }}</td>
                    <td style="font-size:12px">{{ $lead->volume_estimate ?? '-' }}</td>
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
                <th>Service</th><th>Volume Est.</th><th>Revenue</th><th>Probability</th><th>Sales PIC</th><th>Last Updated</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $lead)
                @php $stageMap=['Identifying'=>'identifying','Approaching'=>'approaching','Follow Up'=>'follow-up','Won'=>'won','Lost'=>'lost']; @endphp
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td>
                        <a href="{{ route('leads.show', $lead) }}" style="font-weight:600;color:#111827;text-decoration:none;font-size:13px">{{ $lead->company_name }}</a>
                        <div style="font-size:11px;color:#6b7280">{{ $lead->pic_name }}</div>
                    </td>
                    <td><span class="badge-stage badge-{{ $stageMap[$lead->pipeline_stage]??'identifying' }}" style="font-size:11px">{{ $lead->pipeline_stage }}</span></td>
                    <td><span class="badge-{{ strtolower($lead->temperature) }}" style="font-size:11px">{{ $lead->temperature }}</span></td>
                    <td style="font-size:12px">{{ $lead->product_interest ?? "-" }}</td>
                    <td style="font-size:12px">{{ $lead->volume_estimate ?? '-' }}</td>
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

        {{-- PO Report --}}
        @elseif($reportType === 'po')
        <table class="table report-table mb-0">
            <thead><tr>
                <th>No</th><th>No. DO</th><th>Customer</th><th>Vendor</th>
                <th>Produk Utama</th><th>Revenue</th><th>Gross Profit</th><th>Status</th><th>Tgl Order</th>
            </tr></thead>
            <tbody>
                @forelse($reportData as $i => $po)
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $reportData->firstItem() + $i }}</td>
                    <td style="font-weight:600;font-size:12px">{{ $po->do_number }}</td>
                    <td style="font-size:12px">{{ $po->customer?->company_name ?? '-' }}</td>
                    <td style="font-size:12px;color:#6b7280">{{ $po->vendor?->vendor_name ?? '-' }}</td>
                    <td style="font-size:12px">{{ $po->items->first()?->product_name ?? '-' }}
                        @if($po->items->count() > 1)<span style="color:#9ca3af"> +{{ $po->items->count()-1 }}</span>@endif
                    </td>
                    <td style="font-size:12px;font-weight:600;color:var(--primary)">{{ idrm($po->total_revenue) }}</td>
                    <td style="font-size:12px;font-weight:600;color:#10b981">{{ idrm($po->gross_profit) }}</td>
                    <td>
                        @php $sc=['Done'=>'badge-won','In Progress'=>'badge-follow-up','Cancelled'=>'badge-lost']; @endphp
                        <span class="{{ $sc[$po->status]??'badge-stage' }}" style="font-size:11px">{{ $po->status }}</span>
                    </td>
                    <td style="font-size:12px;color:#6b7280">{{ \Carbon\Carbon::parse($po->order_date)->format('d M Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center py-4" style="color:#9ca3af">Tidak ada data</td></tr>
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
