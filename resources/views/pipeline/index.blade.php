@extends('layouts.app')

@section('title', 'Pipeline')
@section('page-title', 'Pipeline')
@section('page-subtitle', 'Visualisasi proses penjualan dari Lead hingga Won/Closing')

@section('content')

{{-- KPI --}}
<div class="row g-3 mb-4">
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#dbeafe"><i class="fas fa-chart-bar" style="color:#2563eb"></i></div>
            <div>
                <div class="kpi-label">Total Pipeline Value</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ idrm($totalValue) }}</div>
                <div><span class="kpi-vs">Semua stage aktif</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#d1fae5"><i class="fas fa-users" style="color:#059669"></i></div>
            <div>
                <div class="kpi-label">Total Leads</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ $totalLeads }}</div>
                <div><span class="kpi-vs">Semua stage</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#fef3c7"><i class="fas fa-fire" style="color:#d97706"></i></div>
            <div>
                <div class="kpi-label">Potential Deals</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ $potentialDeals }}</div>
                <div><span class="kpi-vs">Follow Up + Won/Closing</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#ede9fe"><i class="fas fa-handshake" style="color:#7c3aed"></i></div>
            <div>
                <div class="kpi-label">Win Rate</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ $winRate }}%</div>
                <div><span class="kpi-vs">vs total leads</span></div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="kpi-card">
            <div class="kpi-icon" style="background:#ccfbf1"><i class="fas fa-trophy" style="color:#0d9488"></i></div>
            <div>
                <div class="kpi-label">Won Revenue</div>
                <div class="kpi-value" style="font-size:1.1rem">{{ idrm($expectedRevenue) }}</div>
                <div><span class="kpi-vs">Total deal closed</span></div>
            </div>
        </div>
    </div>
</div>

{{-- Filter bar --}}
<div class="d-flex align-items-center justify-content-between mb-3">
    <div class="d-flex gap-2 align-items-center">
        <select class="form-select form-select-sm no-select2" style="width:160px;font-size:13px" onchange="filterSales(this.value)">
            <option value="">Semua Sales</option>
            @foreach(\App\Models\User::orderBy('name')->get() as $su)
            <option value="{{ $su->id }}">{{ $su->name }}</option>
            @endforeach
        </select>
    </div>
</div>

{{-- Kanban Board --}}
<div class="card mb-4">
    <div class="card-body p-3">
        <div class="row g-2" id="kanbanBoard">
            @php
            $stageConfig = [
            'Identifying' => ['slug'=>'identifying','num'=>'01','desc'=>'Mencari informasi'],
            'Approaching' => ['slug'=>'approaching','num'=>'02','desc'=>'Menghubungi lead'],
            'Follow Up' => ['slug'=>'follow-up','num'=>'03','desc'=>'Follow up & penawaran'],
            'Won' => ['slug'=>'won','num'=>'04','desc'=>'Negosiasi / Deal Closed'],
            'Maintaining' => ['slug'=>'maintaining','num'=>'05','desc'=>'Mempertahankan pelanggan'],
            ];
            @endphp
            @foreach($pipeline as $stageName => $leads)
            @php $cfg = $stageConfig[$stageName] ?? ['slug'=>'identifying','num'=>'01','desc'=>'']; @endphp
            <div class="col">
                <div class="kanban-header kanban-{{ $cfg['slug'] }}">
                    <div>
                        <div>{{ $cfg['num'] }}. {{ $stageName === 'Won' ? 'Won/Closing' : $stageName }}</div>
                        <div style="font-size:.65rem;font-weight:400;opacity:.8">{{ $cfg['desc'] }}</div>
                    </div>
                    <span class="badge" style="background:rgba(0,0,0,.15);font-size:.65rem">{{ $leads->count() }}</span>
                </div>
                <div class="kanban-body kanban-drop-zone" id="zone-{{ Str::slug($stageName) }}" data-stage="{{ $stageName }}" style="min-height:300px">
                    @php $stageValue = $leads->sum('potensi_revenue'); @endphp
                    <div style="font-size:.72rem;font-weight:700;color:#374151;margin-bottom:8px;padding:4px 0;border-bottom:1px solid #f0f0f0">
                        {{ idrm($stageValue) }} · {{ $leads->count() }} leads
                    </div>

                    @foreach($leads as $lead)
                    <div class="kanban-card" draggable="true"
                        data-id="{{ $lead->id }}"
                        data-stage="{{ $lead->pipeline_stage }}"
                        data-sales="{{ $lead->sales_user_id }}"
                        onclick="window.location='{{ route('leads.show', $lead) }}'">
                        <div class="kc-company">{{ $lead->company_name }}</div>
                        <div class="kc-pic" style="font-size:.7rem">
                            <i class="fas fa-user me-1" style="font-size:.6rem"></i>{{ $lead->pic_name }}
                            @if($lead->pic_position)
                            <span style="color:var(--text-muted)"> · {{ $lead->pic_position }}</span>
                            @endif
                        </div>
                        @if($lead->product_interest)
                        <div class="kc-service">
                            <i class="fas fa-flask me-1" style="font-size:.6rem"></i>{{ $lead->product_interest }}
                            
                        </div>
                        @endif
                        <div class="kc-footer mt-2">
                            <div style="font-size:.65rem;color:var(--text-muted)">
                                <i class="fas fa-user-tie me-1"></i>{{ $lead->salesUser?->name ?? '-' }}
                            </div>
                            <div class="text-end">
                                <div class="kc-amount">{{ idrm($lead->potensi_revenue) }}</div>
                                @if($lead->expected_closing)
                                <div style="font-size:.6rem;color:var(--text-muted)">
                                    <i class="fas fa-calendar me-1"></i>{{ $lead->expected_closing->format('d M') }}
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
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
                <canvas id="pipelinePie" height="150"></canvas>
                <div class="mt-3">
                    @foreach($pipeline as $sn => $leads)
                    @php $colors = ['Identifying'=>'#3b82f6','Approaching'=>'#10b981','Follow Up'=>'#f59e0b','Won'=>'#8b5cf6','Maintaining'=>'#6366f1']; @endphp
                    <div class="d-flex align-items-center justify-content-between py-1" style="border-bottom:1px solid #f9fafb">
                        <div class="d-flex align-items-center gap-2">
                            <div style="width:8px;height:8px;border-radius:2px;background:{{ $colors[$sn] ?? '#999' }}"></div>
                            <span style="font-size:.75rem">{{ $sn === 'Won' ? 'Won/Closing' : $sn }} ({{ $leads->count() }})</span>
                        </div>
                        <span style="font-size:.75rem;font-weight:600">{{ idrm($leads->sum('potensi_revenue')) }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Pipeline Trend (Expected Revenue per Bulan)</div>
            <div class="card-body p-3">
                <canvas id="pipelineTrend" height="180"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-3">
        <div class="card">
            <div class="card-header">Top Sales (Deal Closed)</div>
            <div class="card-body p-3">
                @forelse($topSales->take(5) as $s)
                @php
                $maxDeals = $topSales->max('deals_closed') ?: 1;
                $pct = $maxDeals > 0 ? min(($s->deals_closed / $maxDeals) * 100, 100) : 0;
                @endphp
                <div class="d-flex align-items-center gap-2 mb-3">
                    <div class="user-avatar" style="width:28px;height:28px;font-size:.65rem">{{ substr($s->name,0,2) }}</div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.78rem;font-weight:600">{{ $s->name }}</div>
                        <div style="background:#e5e7eb;border-radius:20px;height:4px;margin-top:3px">
                            <div style="width:{{ $pct }}%;background:var(--primary);height:4px;border-radius:20px"></div>
                        </div>
                    </div>
                    <span style="font-size:.72rem;font-weight:600;color:var(--primary)">{{ $s->deals_closed }} deals</span>
                </div>
                @empty
                <div class="text-center py-3" style="color:var(--text-muted);font-size:.8rem">Belum ada data</div>
                @endforelse
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
@php
$chartLabels = array_keys($pipeline);
$chartValues = array_values(array_map(fn($l) => (float)($l->sum('potensi_revenue') / 1000000), $pipeline));

// Trend: expected revenue per bulan (6 bulan terakhir) dari DB
$trendLabels = [];
$trendData = [];
for ($i = 5; $i >= 0; $i--) {
$month = now()->subMonths($i);
$trendLabels[] = $month->format('M Y');
$trendData[] = (float)(\App\Models\Lead::whereYear('created_at', $month->year)
->whereMonth('created_at', $month->month)
->sum('potensi_revenue') / 1000000);
}
@endphp
<script>
    // ── Pipeline Pie ──
    new Chart(document.getElementById('pipelinePie'), {
        type: 'doughnut',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                data: @json($chartValues),
                backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.label}: Rp ${ctx.parsed.toFixed(1)}M`
                    }
                }
            },
            cutout: '65%'
        }
    });

    // ── Pipeline Trend (data real dari DB) ──
    new Chart(document.getElementById('pipelineTrend'), {
        type: 'line',
        data: {
            labels: @json($trendLabels),
            datasets: [{
                label: 'Expected Revenue (Jt)',
                data: @json($trendData),
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
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 10
                        }
                    }
                },
                y: {
                    grid: {
                        color: '#f3f4f6'
                    },
                    ticks: {
                        font: {
                            size: 10
                        },
                        callback: v => v + ' Jt'
                    }
                }
            }
        }
    });

    // ── Drag & Drop pindah stage ──
    let dragId = null;
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', e => {
            dragId = card.dataset.id;
            card.style.opacity = '.4';
            e.dataTransfer.effectAllowed = 'move';
        });
        card.addEventListener('dragend', () => card.style.opacity = '1');
    });

    document.querySelectorAll('.kanban-drop-zone').forEach(zone => {
        zone.addEventListener('dragover', e => {
            e.preventDefault();
            zone.style.background = '#eff6ff';
            e.dataTransfer.dropEffect = 'move';
        });
        zone.addEventListener('dragleave', () => zone.style.background = '');
        zone.addEventListener('drop', e => {
            e.preventDefault();
            zone.style.background = '';
            const newStage = zone.dataset.stage;
            if (!dragId || !newStage) return;
            updateLeadStage(dragId, newStage);
        });
    });

    function updateLeadStage(leadId, newStage) {
        fetch(`/leads/${leadId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    'X-HTTP-Method-Override': 'PUT',
                },
                body: JSON.stringify({
                    pipeline_stage: newStage
                })
            })
            .then(r => {
                if (r.ok || r.redirected) location.reload();
            })
            .catch(() => location.reload());
    }

    // ── Filter client-side ──
    let filterSalesId = '';

    function filterSales(val) {
        filterSalesId = val;
        applyFilter();
    }

    function applyFilter() {
        document.querySelectorAll('.kanban-card').forEach(card => {
            const matchSales = !filterSalesId || card.dataset.sales == filterSalesId;
            card.style.display = matchSales ? '' : 'none';
        });
    }
</script>
@endpush