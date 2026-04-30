@extends('layouts.app')

@section('title', 'Pipeline')
@section('page-title', 'Pipeline')
@section('page-subtitle', 'Visualisasi proses penjualan dari Lead hingga Closing')

@section('content')
{{-- KPI --}}
<div class="row g-3 mb-4">
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dbeafe"><i class="fas fa-chart-bar" style="color:#2563eb"></i></div>
            <div>
                <div class="kpi-label">Total Pipeline Value</div>
                <div class="kpi-value" style="font-size:1.1rem">Rp {{ number_format($totalValue/1000000000,2) }}M</div>
                <div><span class="kpi-change up"><i class="fas fa-arrow-up"></i> 18.6%</span> <span class="kpi-vs">vs last month</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5"><i class="fas fa-envelope" style="color:#059669"></i></div>
            <div>
                <div class="kpi-label">Total Leads</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ $totalLeads }}</div>
                <div><span class="kpi-change up"><i class="fas fa-arrow-up"></i> 12.5%</span> <span class="kpi-vs">vs last month</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7"><i class="fas fa-envelope-open" style="color:#d97706"></i></div>
            <div>
                <div class="kpi-label">Potential Deals</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ $potentialDeals }}</div>
                <div><span class="kpi-change up"><i class="fas fa-arrow-up"></i> 10.3%</span> <span class="kpi-vs">vs last month</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#ede9fe"><i class="fas fa-handshake" style="color:#7c3aed"></i></div>
            <div>
                <div class="kpi-label">Win Rate (Closing)</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ $winRate }}%</div>
                <div><span class="kpi-change up"><i class="fas fa-arrow-up"></i> 5.2%</span> <span class="kpi-vs">vs last month</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#ccfbf1"><i class="fas fa-trophy" style="color:#0d9488"></i></div>
            <div>
                <div class="kpi-label">Expected Revenue</div>
                <div class="kpi-value" style="font-size:1.1rem">Rp {{ number_format($expectedRevenue/1000000000,2) }}M</div>
                <div><span class="kpi-change up"><i class="fas fa-arrow-up"></i> 15.7%</span> <span class="kpi-vs">vs last month</span></div>
            </div>
        </div>
    </div>
</div>

{{-- Kanban Board --}}
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-2">
            @php
            $stageConfig = [
                'Identifying' => ['slug'=>'identifying','num'=>'01','desc'=>'Mencari informasi'],
                'Approaching' => ['slug'=>'approaching','num'=>'02','desc'=>'Menghubungi lead'],
                'Follow Up'   => ['slug'=>'follow-up','num'=>'03','desc'=>'Follow up & penawaran'],
                'Closing'     => ['slug'=>'closing','num'=>'04','desc'=>'Negosiasi / Closing'],
                'Won'         => ['slug'=>'won','num'=>'05','desc'=>'Deal berhasil'],
            ];
            @endphp
            @foreach($pipeline as $stageName => $leads)
            @php $cfg = $stageConfig[$stageName] ?? ['slug'=>'identifying','num'=>'01','desc'=>'']; @endphp
            <div class="col">
                <div class="kanban-header kanban-{{ $cfg['slug'] }}">
                    <div>
                        <div>{{ $cfg['num'] }}. {{ $stageName }}</div>
                        <div style="font-size:.65rem;font-weight:400;opacity:.8">{{ $cfg['desc'] }}</div>
                    </div>
                    <span class="badge" style="background:rgba(0,0,0,.15);font-size:.65rem">{{ $leads->count() }}</span>
                </div>
                <div class="kanban-body" style="min-height:300px">
                    @php $stageValue = $leads->sum('potensi_revenue'); @endphp
                    <div style="font-size:.75rem;font-weight:700;color:#374151;margin-bottom:8px">Rp {{ number_format($stageValue/1000000,0) }}M · {{ $leads->count() }} Leads</div>

                    @foreach($leads as $lead)
                    <div class="kanban-card" onclick="window.location='{{ route('leads.show', $lead) }}'">
                        <div class="kc-company">{{ $lead->company_name }}</div>
                        <div class="kc-pic" style="font-size:.72rem">{{ $lead->pic_name }}</div>
                        <div class="kc-service">{{ $lead->service_type }}</div>
                        <div class="kc-footer">
                            <span class="badge-{{ strtolower($lead->temperature) }}">{{ $lead->temperature }}</span>
                            <div class="text-end">
                                <div class="kc-amount">Rp {{ number_format($lead->potensi_revenue/1000000,0) }}M</div>
                                <div style="font-size:.67rem;color:var(--text-muted)">{{ $lead->updated_at->format('d M Y') }}</div>
                            </div>
                        </div>
                    </div>
                    @endforeach

                    <a href="{{ route('leads.index', ['stage'=>$stageName]) }}" class="d-block text-center mt-2 py-1" style="font-size:.72rem;color:var(--primary);border:1px dashed #dbeafe;border-radius:6px;text-decoration:none">
                        <i class="fas fa-plus me-1"></i> Add Lead
                    </a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</div>

{{-- Bottom Summary --}}
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Pipeline Summary</div>
            <div class="card-body p-3">
                <canvas id="pipelinePie" height="160"></canvas>
                <div class="mt-3">
                    @foreach($pipeline as $sn => $leads)
                    @php
                    $colors = ['Identifying'=>'#3b82f6','Approaching'=>'#10b981','Follow Up'=>'#f59e0b','Closing'=>'#ef4444','Won'=>'#8b5cf6'];
                    @endphp
                    <div class="d-flex align-items-center justify-content-between py-1">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:10px;height:10px;border-radius:2px;background:{{ $colors[$sn] ?? '#999' }}"></div>
                            <span style="font-size:.75rem">{{ $sn }} ({{ $leads->count() }})</span>
                        </div>
                        <span style="font-size:.75rem;font-weight:600">Rp {{ number_format($leads->sum('potensi_revenue')/1000000,0) }}M</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Pipeline Trend (Expected Revenue)</div>
            <div class="card-body p-3">
                <canvas id="pipelineTrend" height="180"></canvas>
            </div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">Top Sales (By Expected Revenue)</div>
            <div class="card-body p-3">
                @foreach($topSales->take(5) as $s)
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="user-avatar" style="width:28px;height:28px;font-size:.65rem">{{ substr($s->name,0,2) }}</div>
                    <div class="flex-1">
                        <div style="font-size:.78rem;font-weight:600">{{ $s->name }}</div>
                        <div style="background:#e5e7eb;border-radius:20px;height:4px;margin-top:3px">
                            <div style="width:{{ min(($s->deals_closed * 10), 100) }}%;background:var(--primary);height:4px;border-radius:20px"></div>
                        </div>
                    </div>
                    <span style="font-size:.75rem;font-weight:600;color:var(--primary)">Rp {{ number_format($s->deals_closed * 50, 0) }}M</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const pipelineLabels = {!! json_encode(array_keys($pipeline)) !!};
const pipelineValues = {!! json_encode(array_map(fn($leads) => $leads->sum('potensi_revenue')/1000000, $pipeline)) !!};

new Chart(document.getElementById('pipelinePie'), {
    type: 'doughnut',
    data: {
        labels: pipelineLabels,
        datasets: [{ data: pipelineValues, backgroundColor: ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'], borderWidth: 0 }]
    },
    options: {
        plugins: {
            legend: { display: false },
            tooltip: { callbacks: { label: ctx => ` Rp ${ctx.parsed.toFixed(0)}M` } }
        },
        cutout: '65%'
    }
});

const months = ['Jan','Feb','Mar','Apr','May','Jun'];
new Chart(document.getElementById('pipelineTrend'), {
    type: 'line',
    data: {
        labels: months,
        datasets: [{
            label: 'Expected Revenue (M)',
            data: [420,480,520,660,784,900],
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37,99,235,.1)',
            fill: true,
            tension: .4,
            borderWidth: 2,
            pointRadius: 4,
            pointBackgroundColor: '#2563eb',
        }]
    },
    options: {
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: { grid: { color: '#f3f4f6' }, ticks: { font: { size: 10 }, callback: v => v + 'M' } }
        }
    }
});
</script>
@endpush
