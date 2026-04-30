@extends('layouts.app')

@section('title', 'Reports')

@push('styles')
<style>
    .filter-card { background: #fff; border-radius: 12px; border: 1px solid #f0f0f0; padding: 20px 24px; margin-bottom: 20px; }
    .filter-card .form-label { font-size: 12px; font-weight: 600; color: #374151; margin-bottom: 4px; }
    .filter-card .form-control,
    .filter-card .form-select { font-size: 13px; border-radius: 8px; border: 1px solid #e5e7eb; height: 38px; }

    .report-tabs { border-bottom: 2px solid #f0f0f0; margin-bottom: 20px; }
    .report-tabs .nav-link { font-size: 13px; font-weight: 500; color: #6b7280; padding: 10px 20px; border: none; background: transparent; border-bottom: 2px solid transparent; margin-bottom: -2px; cursor: pointer; }
    .report-tabs .nav-link.active { color: #3b82f6; border-bottom: 2px solid #3b82f6; font-weight: 600; }
    .report-tabs .nav-link:hover { color: #374151; }

    .summary-card { background: #fff; border-radius: 12px; border: 1px solid #f0f0f0; padding: 20px; }
    .summary-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .summary-label { font-size: 12px; color: #6b7280; }
    .summary-value { font-size: 18px; font-weight: 700; color: #0f1d35; margin: 2px 0; }
    .summary-growth { font-size: 12px; font-weight: 600; }
    .summary-growth.up { color: #10b981; }

    .report-table-card { background: #fff; border-radius: 12px; border: 1px solid #f0f0f0; padding: 20px 24px; }
    .report-table { font-size: 13px; width: 100%; }
    .report-table thead th { font-size: 11px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; padding: 10px 12px; border-bottom: 2px solid #f0f0f0; white-space: nowrap; background: #f9fafb; }
    .report-table tbody td { padding: 11px 12px; border-bottom: 1px solid #f9fafb; color: #374151; vertical-align: middle; }
    .report-table tbody tr:last-child td { border-bottom: none; }
    .report-table tbody tr:hover { background: #fafbfc; }

    .badge-stage { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; white-space: nowrap; }
    .stage-identifying { background: #eff6ff; color: #2563eb; }
    .stage-approaching { background: #fff7ed; color: #c2410c; }
    .stage-followup { background: #f5f3ff; color: #7c3aed; }
    .stage-closing { background: #fef3c7; color: #92400e; }
    .stage-won { background: #f0fdf4; color: #16a34a; }

    .badge-deal-status { font-size: 11px; padding: 3px 10px; border-radius: 20px; font-weight: 600; }
    .status-won { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .status-inprogress { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .status-lost { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

    .export-card { background: #fff; border-radius: 12px; border: 1px solid #f0f0f0; padding: 16px 20px; }
    .btn-export { display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 600; border: 1px solid #e5e7eb; background: #fff; color: #374151; cursor: pointer; transition: all 0.2s; margin-bottom: 8px; }
    .btn-export:hover { background: #f9fafb; }
    .btn-export.excel { border-color: #bbf7d0; color: #16a34a; }
    .btn-export.excel:hover { background: #f0fdf4; }
    .btn-export.pdf { border-color: #fecaca; color: #dc2626; }
    .btn-export.pdf:hover { background: #fef2f2; }
    .btn-export.print { border-color: #bfdbfe; color: #2563eb; }
    .btn-export.print:hover { background: #eff6ff; }

    .pagination-wrap { display: flex; align-items: center; justify-content: space-between; padding-top: 16px; border-top: 1px solid #f0f0f0; }
    .pagination-info { font-size: 12px; color: #6b7280; }
    .pagination-btns .page-btn { width: 32px; height: 32px; border-radius: 6px; border: 1px solid #e5e7eb; display: inline-flex; align-items: center; justify-content: center; font-size: 12px; color: #374151; cursor: pointer; margin: 0 1px; }
    .pagination-btns .page-btn.active { background: #3b82f6; color: #fff; border-color: #3b82f6; }
    .pagination-btns .page-btn:hover:not(.active) { background: #f9fafb; }
</style>
@endpush

@section('content')
<!-- Header -->
<div class="d-flex align-items-start justify-content-between mb-4">
    <div>
        <h4 class="fw-bold mb-1" style="color:#0f1d35">Reports</h4>
        <p class="text-muted mb-0" style="font-size:13px">Laporan data bisnis dalam bentuk detail dan siap di-export</p>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-light btn-sm" style="font-size:13px; border:1px solid #e5e7eb; border-radius:8px;">
            <i class="fas fa-question-circle text-muted me-1"></i> Help
        </button>
    </div>
</div>

<!-- Filter + Export Side by Side -->
<div class="row g-3 mb-3">
    <!-- Filters -->
    <div class="col-md-9">
        <div class="filter-card">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date Range</label>
                    <div class="input-group">
                        <input type="text" class="form-control" value="01 Mei 2025 – 31 Mei 2025" style="font-size:13px;border-radius:8px 0 0 8px">
                        <span class="input-group-text" style="background:#fff;border-radius:0 8px 8px 0;border-left:0"><i class="fas fa-calendar-alt text-muted" style="font-size:12px"></i></span>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Report Type</label>
                    <select class="form-select" id="reportTypeSelect">
                        <option selected>Sales Report</option>
                        <option>Customer Report</option>
                        <option>Pipeline Report</option>
                        <option>Performance Report</option>
                        <option>Profit Report</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Sales PIC</label>
                    <select class="form-select">
                        <option>Semua Sales</option>
                        <option>Budi Santoso</option>
                        <option>Rina Anita</option>
                        <option>Dedi Suhendra</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Customer</label>
                    <select class="form-select">
                        <option>Semua Customer</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Service Type</label>
                    <select class="form-select">
                        <option>Semua Service</option>
                        <option>Sea Freight</option>
                        <option>Air Freight</option>
                        <option>Trucking</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end">
                    <button class="btn btn-light btn-sm w-100" style="border-radius:8px; font-size:12px; height:38px; border:1px solid #e5e7eb;">
                        <i class="fas fa-undo" style="font-size:11px"></i>
                    </button>
                </div>
            </div>
            <div class="row g-3 mt-1">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select class="form-select">
                        <option>Semua Status</option>
                        <option>Won</option>
                        <option>In Progress</option>
                        <option>Lost</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Route</label>
                    <input type="text" class="form-control" placeholder="Semua Route" style="font-size:13px">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-primary w-100" style="border-radius:8px; font-size:13px; height:38px;">
                        <i class="fas fa-search me-1"></i> Generate Report
                    </button>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button class="btn btn-light w-100" style="border-radius:8px; font-size:13px; height:38px; border:1px solid #e5e7eb; color:#6b7280;">
                        <i class="fas fa-sync-alt me-1"></i> Reset Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Card -->
    <div class="col-md-3">
        <div class="export-card">
            <div style="font-size:13px; font-weight:600; color:#0f1d35; margin-bottom:12px">Export Report</div>
            <button class="btn-export excel">
                <i class="fas fa-file-excel" style="font-size:16px"></i>
                <span>Export Excel</span>
            </button>
            <button class="btn-export pdf">
                <i class="fas fa-file-pdf" style="font-size:16px"></i>
                <span>Export PDF</span>
            </button>
            <button class="btn-export print" style="margin-bottom:0">
                <i class="fas fa-print" style="font-size:16px"></i>
                <span>Print Report</span>
            </button>
        </div>
    </div>
</div>

<!-- Report Tabs -->
<div class="report-table-card">
    <div class="report-tabs d-flex">
        <button class="nav-link active" data-tab="sales">Sales Report</button>
        <button class="nav-link" data-tab="customer">Customer Report</button>
        <button class="nav-link" data-tab="pipeline">Pipeline Report</button>
        <button class="nav-link" data-tab="performance">Performance Report</button>
        <button class="nav-link" data-tab="profit">Profit Report</button>
    </div>

    <!-- Summary KPI -->
    <div class="row g-3 mb-4" id="summaryRow">
        <div class="col">
            <div class="summary-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="summary-icon" style="background:#eff6ff"><i class="fas fa-chart-bar" style="color:#3b82f6"></i></div>
                    <div>
                        <div class="summary-label">Total Revenue (IDR)</div>
                        <div class="summary-value" style="font-size:16px">Rp 2.450.000.000</div>
                        <span class="summary-growth up"><i class="fas fa-arrow-up" style="font-size:9px"></i> 18.6% vs Apr 2025</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="summary-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="summary-icon" style="background:#f0fdf4"><i class="fas fa-handshake" style="color:#10b981"></i></div>
                    <div>
                        <div class="summary-label">Total Deals</div>
                        <div class="summary-value">86</div>
                        <span class="summary-growth up"><i class="fas fa-arrow-up" style="font-size:9px"></i> 16.2% vs Apr 2025</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="summary-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="summary-icon" style="background:#fff7ed"><i class="fas fa-coins" style="color:#f97316"></i></div>
                    <div>
                        <div class="summary-label">Avg Deal Value</div>
                        <div class="summary-value" style="font-size:16px">Rp 28.488.372</div>
                        <span class="summary-growth up"><i class="fas fa-arrow-up" style="font-size:9px"></i> 2.1% vs Apr 2025</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="summary-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="summary-icon" style="background:#faf5ff"><i class="fas fa-bullseye" style="color:#7c3aed"></i></div>
                    <div>
                        <div class="summary-label">Conversion Rate</div>
                        <div class="summary-value">34.7%</div>
                        <span class="summary-growth up"><i class="fas fa-arrow-up" style="font-size:9px"></i> 4.8% vs Apr 2025</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="summary-card">
                <div class="d-flex align-items-center gap-3">
                    <div class="summary-icon" style="background:#fef9c3"><i class="fas fa-trophy" style="color:#ca8a04"></i></div>
                    <div>
                        <div class="summary-label">Win Rate (Closing)</div>
                        <div class="summary-value">32%</div>
                        <span class="summary-growth up"><i class="fas fa-arrow-up" style="font-size:9px"></i> 5.2% vs Apr 2025</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Table Header Controls -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div class="d-flex align-items-center gap-2">
            <span style="font-size:13px; font-weight:600; color:#0f1d35">Sales Report Detail</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="d-flex align-items-center gap-2" style="font-size:13px; color:#6b7280">
                Show
                <select class="form-select form-select-sm" style="width:65px; font-size:13px; border-radius:6px;">
                    <option>10</option>
                    <option>25</option>
                    <option>50</option>
                </select>
                entries
            </div>
            <div class="input-group" style="width:220px">
                <span class="input-group-text" style="background:#fff; border-right:0; border-radius:8px 0 0 8px; border-color:#e5e7eb">
                    <i class="fas fa-search text-muted" style="font-size:12px"></i>
                </span>
                <input type="text" class="form-control form-control-sm" placeholder="Search..." style="border-left:0; border-radius:0 8px 8px 0; font-size:13px; border-color:#e5e7eb">
            </div>
            <button class="btn btn-light btn-sm d-flex align-items-center gap-1" style="font-size:13px; border:1px solid #e5e7eb; border-radius:8px; padding:6px 12px;">
                <i class="fas fa-columns" style="font-size:11px"></i> Column
            </button>
        </div>
    </div>

    <!-- Sales Report Table -->
    <div class="table-responsive">
        <table class="report-table" id="salesReportTable">
            <thead>
                <tr>
                    <th style="width:40px">No</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>PIC</th>
                    <th>Sales PIC</th>
                    <th>Pipeline Stage</th>
                    <th>Service Type</th>
                    <th>Route</th>
                    <th>Deal Value (IDR)</th>
                    <th>Status</th>
                    <th>Probability</th>
                    <th>Expected Close</th>
                    <th>Created At</th>
                    <th style="width:40px"></th>
                </tr>
            </thead>
            <tbody>
                @php
                $reportData = [
                    ['no'=>1,'date'=>'20 Mei 2025','customer'=>'PT. Maju Bersama','pic'=>'Rina Anita','sales'=>'Budi Santoso','stage'=>'Closing','stage_class'=>'stage-closing','service'=>'Sea Freight','route'=>'Shanghai – Surabaya','value'=>'Rp 120.000.000','status'=>'Won','status_class'=>'status-won','prob'=>'100%','expected'=>'20 Mei 2025','created'=>'15 Mei 2025 09:30'],
                    ['no'=>2,'date'=>'19 Mei 2025','customer'=>'PT. Global Indo','pic'=>'Dedi Suhendra','sales'=>'Rina Anita','stage'=>'Follow Up','stage_class'=>'stage-followup','service'=>'Trucking','route'=>'Jakarta – Surabaya','value'=>'Rp 95.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'70%','expected'=>'28 Mei 2025','created'=>'14 Mei 2025 10:15'],
                    ['no'=>3,'date'=>'18 Mei 2025','customer'=>'PT. Prima Sukses','pic'=>'Jason','sales'=>'Dedi Suhendra','stage'=>'Approaching','stage_class'=>'stage-approaching','service'=>'Air Freight','route'=>'Jakarta – Singapore','value'=>'Rp 85.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'40%','expected'=>'30 Mei 2025','created'=>'13 Mei 2025 11:20'],
                    ['no'=>4,'date'=>'17 Mei 2025','customer'=>'PT. Damai Sejahtera','pic'=>'Eko Prasetyo','sales'=>'Steven','stage'=>'Closing','stage_class'=>'stage-closing','service'=>'Trucking','route'=>'Makassar – Surabaya','value'=>'Rp 70.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'80%','expected'=>'27 Mei 2025','created'=>'12 Mei 2025 14:05'],
                    ['no'=>5,'date'=>'16 Mei 2025','customer'=>'PT. Mitra Mandiri','pic'=>'Sari Dewi','sales'=>'Fajar','stage'=>'Identifying','stage_class'=>'stage-identifying','service'=>'Sea Freight','route'=>'Jakarta – Shanghai','value'=>'Rp 65.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'20%','expected'=>'05 Jun 2025','created'=>'11 Mei 2025 16:40'],
                    ['no'=>6,'date'=>'15 Mei 2025','customer'=>'PT. Sumber Makmur','pic'=>'Budi Hartono','sales'=>'Rina Anita','stage'=>'Follow Up','stage_class'=>'stage-followup','service'=>'Trucking','route'=>'Jakarta – Medan','value'=>'Rp 60.000.000','status'=>'Lost','status_class'=>'status-lost','prob'=>'30%','expected'=>'—','created'=>'10 Mei 2025 09:10'],
                    ['no'=>7,'date'=>'14 Mei 2025','customer'=>'PT. Armada Transport','pic'=>'Yoga Pratama','sales'=>'Steven','stage'=>'Approaching','stage_class'=>'stage-approaching','service'=>'Air Freight','route'=>'Jakarta – Dubai','value'=>'Rp 55.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'50%','expected'=>'02 Jun 2025','created'=>'09 Mei 2025 13:25'],
                    ['no'=>8,'date'=>'13 Mei 2025','customer'=>'PT. Berkat Abadi','pic'=>'Dina Lestari','sales'=>'Dedi Suhendra','stage'=>'Identifying','stage_class'=>'stage-identifying','service'=>'Sea Freight','route'=>'Surabaya – Singapore','value'=>'Rp 45.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'10%','expected'=>'10 Jun 2025','created'=>'08 Mei 2025 10:05'],
                    ['no'=>9,'date'=>'12 Mei 2025','customer'=>'PT. Karya Utama','pic'=>'Fajar','sales'=>'Fajar','stage'=>'Identifying','stage_class'=>'stage-identifying','service'=>'Trucking','route'=>'Surabaya – Jakarta','value'=>'Rp 40.000.000','status'=>'In Progress','status_class'=>'status-inprogress','prob'=>'10%','expected'=>'12 Jun 2025','created'=>'07 Mei 2025 11:30'],
                    ['no'=>10,'date'=>'11 Mei 2025','customer'=>'PT. Indotech','pic'=>'Yudi','sales'=>'Budi Santoso','stage'=>'Follow Up','stage_class'=>'stage-followup','service'=>'Air Freight','route'=>'Jakarta – Hongkong','value'=>'Rp 35.000.000','status'=>'Lost','status_class'=>'status-lost','prob'=>'25%','expected'=>'—','created'=>'06 Mei 2025 09:15'],
                ];
                @endphp
                @foreach($reportData as $row)
                <tr>
                    <td style="color:#9ca3af;font-size:12px">{{ $row['no'] }}</td>
                    <td style="white-space:nowrap;font-size:12px">{{ $row['date'] }}</td>
                    <td style="font-weight:600;font-size:13px">{{ $row['customer'] }}</td>
                    <td style="font-size:12px;color:#6b7280">{{ $row['pic'] }}</td>
                    <td style="font-size:12px">{{ $row['sales'] }}</td>
                    <td><span class="badge-stage {{ $row['stage_class'] }}">{{ $row['stage'] }}</span></td>
                    <td style="font-size:12px">{{ $row['service'] }}</td>
                    <td style="font-size:12px;white-space:nowrap">{{ $row['route'] }}</td>
                    <td style="font-size:12px;font-weight:600;white-space:nowrap">{{ $row['value'] }}</td>
                    <td><span class="badge-deal-status {{ $row['status_class'] }}">{{ $row['status'] }}</span></td>
                    <td style="font-size:12px;text-align:center">{{ $row['prob'] }}</td>
                    <td style="font-size:12px;white-space:nowrap;color:#6b7280">{{ $row['expected'] }}</td>
                    <td style="font-size:11px;white-space:nowrap;color:#9ca3af">{{ $row['created'] }}</td>
                    <td>
                        <button class="btn btn-sm" style="width:28px;height:28px;padding:0;border:1px solid #e5e7eb;border-radius:6px;display:flex;align-items:center;justify-content:center">
                            <i class="fas fa-ellipsis-v" style="font-size:11px;color:#6b7280"></i>
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <div class="pagination-wrap">
        <div class="pagination-info">Showing 1 to 10 of 86 entries</div>
        <div class="pagination-btns d-flex">
            <button class="page-btn"><i class="fas fa-chevron-left" style="font-size:10px"></i></button>
            <button class="page-btn active">1</button>
            <button class="page-btn">2</button>
            <button class="page-btn">3</button>
            <button class="page-btn">4</button>
            <button class="page-btn">5</button>
            <button class="page-btn" style="width:auto;padding:0 10px">...</button>
            <button class="page-btn">9</button>
            <button class="page-btn"><i class="fas fa-chevron-right" style="font-size:10px"></i></button>
        </div>
    </div>

    <!-- Disclaimer -->
    <div class="mt-3 pt-3" style="border-top:1px solid #f0f0f0">
        <p style="font-size:12px; color:#6b7280; margin:0">
            <span style="color:#dc2626; font-weight:600">Disclaimer:</span>
            Data laporan berdasarkan periode dan filter yang dipilih. Pastikan filter sudah sesuai sebelum export data.
        </p>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tab switching
    document.querySelectorAll('.report-tabs .nav-link').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.report-tabs .nav-link').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // Update table title
            const labels = {
                'sales': 'Sales Report Detail',
                'customer': 'Customer Report Detail',
                'pipeline': 'Pipeline Report Detail',
                'performance': 'Performance Report Detail',
                'profit': 'Profit Report Detail',
            };
            const tab = this.dataset.tab;
            document.querySelector('#salesReportTable').closest('.report-table-card').querySelector('.fw-600, span[style*="font-weight:600"]').textContent = labels[tab] || 'Report Detail';
        });
    });
});
</script>
@endpush
