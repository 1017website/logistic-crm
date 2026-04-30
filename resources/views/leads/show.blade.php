@extends('layouts.app')

@section('title', $lead->company_name)
@section('page-title', '')
@section('page-subtitle', '')

@section('content')
{{-- Breadcrumb --}}
<nav style="font-size:.8rem;color:var(--text-muted)" class="mb-3">
    <a href="{{ route('leads.index') }}" style="color:var(--primary)">Leads</a>
    <span class="mx-2">&rsaquo;</span>
    <span>Detail Leads</span>
</nav>

{{-- Header --}}
<div class="d-flex align-items-center justify-content-between mb-4">
    <div class="d-flex align-items-center gap-3">
        <div class="user-avatar" style="width:50px;height:50px;font-size:1rem;border-radius:10px">
            {{ $lead->getLogoInitialsAttribute() ?? substr($lead->company_name, 0, 2) }}
        </div>
        <div>
            <h4 class="mb-1 fw-bold">{{ $lead->company_name }}</h4>
            <div class="d-flex gap-2">
                @php
                $stageMap = ['Identifying'=>'identifying','Approaching'=>'approaching','Follow Up'=>'follow-up','Closing'=>'closing','Won'=>'won','Lost'=>'lost'];
                $slug = $stageMap[$lead->pipeline_stage] ?? 'identifying';
                @endphp
                <span class="badge-stage badge-{{ $slug }}">{{ $lead->pipeline_stage }}</span>
                <span class="badge-{{ strtolower($lead->temperature) }}">{{ $lead->temperature }}</span>
                <span style="font-size:.75rem;color:var(--text-muted)">Lead ID: {{ $lead->lead_code }}</span>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('leads.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-edit me-1"></i> Edit Lead
        </a>
        <form method="POST" action="{{ route('leads.update', $lead) }}" class="d-inline">
            @csrf @method('PUT')
            <input type="hidden" name="pipeline_stage" value="Lost">
            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tandai sebagai Lost?')">
                <i class="fas fa-times-circle me-1"></i> Mark as Lost
            </button>
        </form>
        <form method="POST" action="{{ route('leads.update', $lead) }}" class="d-inline">
            @csrf @method('PUT')
            <input type="hidden" name="pipeline_stage" value="Won">
            <button type="submit" class="btn btn-sm btn-success" onclick="return confirm('Convert ke Deal?')">
                <i class="fas fa-check-circle me-1"></i> Convert to Deal
            </button>
        </form>
    </div>
</div>

{{-- Pipeline Steps --}}
<div class="card mb-4">
    <div class="card-body py-3 px-4">
        <div class="d-flex align-items-center justify-content-between">
            @php
            $stages = ['Identifying' => 'Mencari informasi', 'Approaching' => 'Menghubungi lead', 'Follow Up' => 'Follow up & penawaran', 'Closing' => 'Negosiasi / Closing'];
            $stageOrder = array_keys($stages);
            $currentIdx = array_search($lead->pipeline_stage, $stageOrder);
            @endphp
            @foreach($stages as $sn => $sd)
            @php
                $idx = array_search($sn, $stageOrder);
                $isDone = $idx < $currentIdx;
                $isCurrent = $sn === $lead->pipeline_stage;
            @endphp
            <div class="d-flex align-items-center gap-3">
                <div class="d-flex flex-column align-items-center">
                    <div style="width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                        background:{{ $isCurrent ? '#f59e0b' : ($isDone ? '#d1fae5' : '#f3f4f6') }};
                        border:2px solid {{ $isCurrent ? '#d97706' : ($isDone ? '#059669' : '#e5e7eb') }}">
                        @if($isDone)
                            <i class="fas fa-check" style="color:#059669;font-size:.7rem"></i>
                        @elseif($isCurrent)
                            <i class="fas fa-envelope" style="color:#d97706;font-size:.7rem"></i>
                        @else
                            <i class="fas fa-calendar" style="color:#9ca3af;font-size:.7rem"></i>
                        @endif
                    </div>
                    <div style="font-size:.75rem;font-weight:{{ $isCurrent ? '700' : '500' }};color:{{ $isCurrent ? '#d97706' : ($isDone ? '#059669' : '#9ca3af') }};margin-top:4px">{{ $sn }}</div>
                    <div style="font-size:.67rem;color:var(--text-muted)">{{ $sd }}</div>
                </div>
                @if(!$loop->last)
                <div style="flex:1;height:2px;background:{{ $isDone ? '#059669' : '#e5e7eb' }};min-width:40px"></div>
                @endif
            </div>
            @endforeach
        </div>
    </div>
</div>

<div class="row g-3">
    {{-- LEFT --}}
    <div class="col-lg-4">
        {{-- Company Info --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Informasi Company</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)"><i class="fas fa-edit me-1"></i>Edit</a>
            </div>
            <div class="card-body p-3">
                @php
                $fields = [
                    ['icon'=>'building','label'=>'Nama Perusahaan','value'=>$lead->company_name],
                    ['icon'=>'user','label'=>'PIC (Person in Charge)','value'=>$lead->pic_name],
                    ['icon'=>'briefcase','label'=>'Jabatan','value'=>$lead->pic_position ?? '-'],
                    ['icon'=>'phone','label'=>'Phone','value'=>$lead->phone ?? '-'],
                    ['icon'=>'envelope','label'=>'Email','value'=>$lead->email ?? '-'],
                    ['icon'=>'map-marker-alt','label'=>'Alamat','value'=>$lead->address ?? '-'],
                    ['icon'=>'industry','label'=>'Industry','value'=>$lead->industry ?? '-'],
                    ['icon'=>'globe','label'=>'Sumber Lead','value'=>$lead->lead_source ?? '-'],
                    ['icon'=>'calendar','label'=>'Tanggal Dibuat','value'=>$lead->created_at->format('d M Y')],
                ];
                @endphp
                @foreach($fields as $f)
                <div class="d-flex gap-2 mb-2">
                    <i class="fas fa-{{ $f['icon'] }}" style="width:16px;color:var(--text-muted);margin-top:2px;font-size:.8rem"></i>
                    <div>
                        <div style="font-size:.68rem;color:var(--text-muted)">{{ $f['label'] }}</div>
                        <div style="font-size:.8rem;font-weight:500">{{ $f['value'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Kebutuhan & Rute --}}
        @if($lead->service_type)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Kebutuhan & Rute</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)"><i class="fas fa-edit me-1"></i>Edit</a>
            </div>
            <div class="card-body p-3">
                @foreach([
                    ['icon'=>'ship','label'=>'Jenis Layanan','value'=>$lead->service_type],
                    ['icon'=>'route','label'=>'Rute','value'=>$lead->route ?? '-'],
                    ['icon'=>'box','label'=>'Commodity','value'=>$lead->commodity ?? '-'],
                    ['icon'=>'chart-bar','label'=>'Volume Estimasi','value'=>$lead->volume_estimate ?? '-'],
                    ['icon'=>'clock','label'=>'Timeline','value'=>$lead->timeline ?? '-'],
                    ['icon'=>'sticky-note','label'=>'Catatan Kebutuhan','value'=>$lead->notes_kebutuhan ?? '-'],
                ] as $f)
                <div class="d-flex gap-2 mb-2">
                    <i class="fas fa-{{ $f['icon'] }}" style="width:16px;color:var(--text-muted);margin-top:2px;font-size:.75rem"></i>
                    <div>
                        <div style="font-size:.68rem;color:var(--text-muted)">{{ $f['label'] }}</div>
                        <div style="font-size:.78rem">{{ $f['value'] }}</div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Lead Owner --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Lead Owner</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)"><i class="fas fa-edit me-1"></i>Edit</a>
            </div>
            <div class="card-body p-3 d-flex align-items-center gap-3">
                <div class="user-avatar" style="width:42px;height:42px">{{ $lead->salesUser ? substr($lead->salesUser->name, 0, 2) : 'SA' }}</div>
                <div>
                    <div style="font-weight:600">{{ $lead->salesUser?->name }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted)">{{ $lead->salesUser?->position }}</div>
                    <div style="font-size:.75rem;color:var(--text-muted)">{{ $lead->salesUser?->phone }}</div>
                    <div style="font-size:.75rem;color:var(--primary)">{{ $lead->salesUser?->email }}</div>
                </div>
            </div>
        </div>
    </div>

    {{-- MIDDLE: Activity Timeline --}}
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>Activity Timeline</span>
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem" data-bs-toggle="modal" data-bs-target="#addActivityModal">
                        <i class="fas fa-plus me-1"></i> Add Activity
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem">
                        <i class="fas fa-phone me-1"></i> Log Call
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" style="font-size:.72rem">
                        <i class="fas fa-building me-1"></i> Log Visit
                    </button>
                </div>
            </div>
            <div class="card-body p-3">
                <div class="activity-timeline">
                    @forelse($lead->activities->take(6) as $act)
                    <div class="activity-item">
                        <div class="activity-icon" style="background:{{ $act->type === 'Call' ? '#d1fae5' : ($act->type === 'Visit' ? '#dbeafe' : ($act->type === 'Email' ? '#fef3c7' : '#f3f4f6')) }}">
                            <i class="fas fa-{{ $act->type_icon }}" style="color:{{ $act->type === 'Call' ? '#059669' : ($act->type === 'Visit' ? '#2563eb' : ($act->type === 'Email' ? '#d97706' : '#6b7280')) }};font-size:.8rem"></i>
                        </div>
                        <div class="flex-1">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="activity-subject">{{ $act->type }}</span>
                                    <span class="ms-2 badge-{{ strtolower($act->status) }}">{{ $act->status }}</span>
                                </div>
                                <button class="btn btn-sm p-0" style="color:var(--text-muted)"><i class="fas fa-ellipsis-v"></i></button>
                            </div>
                            <div class="activity-desc mt-1">{{ $act->description }}</div>
                            <div class="activity-meta d-flex gap-3 mt-1">
                                <span><i class="fas fa-user me-1"></i>PIC: {{ $act->salesUser?->name }}</span>
                                <span><i class="fas fa-calendar me-1"></i>{{ $act->activity_at->format('d M Y, H:i') }}</span>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="text-center py-3 text-muted" style="font-size:.8rem">Belum ada aktivitas.</div>
                    @endforelse
                </div>
                @if($lead->activities->count() > 5)
                <div class="text-center mt-2">
                    <button class="btn btn-sm btn-outline-secondary" style="font-size:.75rem">
                        <i class="fas fa-chevron-down me-1"></i>Load More Activity
                    </button>
                </div>
                @endif
            </div>
        </div>

        {{-- Catatan Internal --}}
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Catatan Internal</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)"><i class="fas fa-edit me-1"></i>Edit</a>
            </div>
            <div class="card-body p-3">
                @if($lead->catatan_internal)
                    @foreach(explode("\n", $lead->catatan_internal) as $line)
                    @if(trim($line))
                    <div class="d-flex gap-2 mb-1">
                        <i class="fas fa-circle" style="font-size:.35rem;color:var(--primary);margin-top:6px;flex-shrink:0"></i>
                        <span style="font-size:.8rem">{{ trim($line) }}</span>
                    </div>
                    @endif
                    @endforeach
                @else
                    <p class="text-muted" style="font-size:.8rem">Belum ada catatan internal.</p>
                @endif
            </div>
        </div>
    </div>

    {{-- RIGHT --}}
    <div class="col-lg-3">
        {{-- Next Follow Up --}}
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Next Follow Up</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)"><i class="fas fa-edit me-1"></i>Edit</a>
            </div>
            <div class="card-body p-3">
                @if($lead->next_follow_up)
                <div class="d-flex align-items-center gap-2 mb-2">
                    <div style="width:32px;height:32px;background:#dbeafe;border-radius:7px;display:flex;align-items:center;justify-content:center">
                        <i class="fas fa-calendar" style="color:#2563eb;font-size:.75rem"></i>
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:.85rem">{{ $lead->next_follow_up->translatedFormat('l, d F Y') }}</div>
                        @if($lead->next_follow_up_time)
                        <div style="font-size:.75rem;color:var(--text-muted)"><i class="fas fa-clock me-1"></i>{{ substr($lead->next_follow_up_time, 0, 5) }}</div>
                        @endif
                    </div>
                </div>
                @if($lead->next_follow_up_notes)
                <div style="font-size:.78rem;color:#374151">{{ $lead->next_follow_up_notes }}</div>
                @endif
                <div class="mt-2">
                    <span class="badge-planned">Planned</span>
                    <div style="font-size:.7rem;color:var(--text-muted);margin-top:4px">Reminder akan dikirim 1 jam sebelum waktu follow up.</div>
                </div>
                @else
                <p class="text-muted" style="font-size:.8rem">Belum dijadwalkan.</p>
                @endif
            </div>
        </div>

        {{-- Status & Info --}}
        <div class="card mb-3">
            <div class="card-header">Status & Info</div>
            <div class="card-body p-3">
                @foreach([
                    ['label'=>'Status Lead','value'=>$lead->pipeline_stage,'color'=>true],
                    ['label'=>'Lead Score','value'=>$lead->lead_score . ' / 100','color'=>false,'special'=>'score'],
                    ['label'=>'Potensi Revenue','value'=>'Rp ' . number_format($lead->potensi_revenue, 0, ',', '.'), 'color'=>false],
                    ['label'=>'Probability Closing','value'=>$lead->probability . '%','color'=>false],
                    ['label'=>'Expected Closing','value'=>$lead->expected_closing ? $lead->expected_closing->format('M Y') : '-','color'=>false],
                    ['label'=>'Competitor','value'=>$lead->competitor ?? '-','color'=>false],
                ] as $f)
                <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom:1px solid #f3f4f6;font-size:.78rem">
                    <span style="color:var(--text-muted)">{{ $f['label'] }}</span>
                    @if(($f['special'] ?? '') === 'score')
                    <span style="color:#d97706;font-weight:700">{{ $f['value'] }}</span>
                    @else
                    <span style="font-weight:600">{{ $f['value'] }}</span>
                    @endif
                </div>
                @endforeach
            </div>
        </div>

        {{-- Latest Quotation --}}
        @if($lead->quotations->count())
        @php $latestQuot = $lead->quotations->last(); @endphp
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Quotation (Terakhir)</span>
                <a href="#" style="font-size:.75rem;color:var(--primary)">Lihat Semua</a>
            </div>
            <div class="card-body p-3" style="font-size:.78rem">
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-weight:600">Quotation #{{ $latestQuot->quotation_number }}</span>
                    <span class="badge-{{ strtolower($latestQuot->status) }}">{{ $latestQuot->status }}</span>
                </div>
                <div style="color:var(--text-muted)">Dikirim: {{ $latestQuot->sent_at?->format('d M Y') ?? '-' }}</div>
                <div class="mt-2">
                    <div>Layanan: {{ $latestQuot->service_type }}</div>
                    <div>Rute: {{ $latestQuot->route }}</div>
                    <div style="font-weight:700;margin-top:4px">Total: {{ $latestQuot->currency }} {{ number_format($latestQuot->total_price, 0, ',', '.') }}</div>
                    <div style="color:var(--text-muted)">Berlaku: {{ $latestQuot->valid_until?->format('d M Y') ?? '-' }}</div>
                </div>
            </div>
        </div>
        @endif

        {{-- Quick Actions --}}
        <div class="card">
            <div class="card-header">Quick Actions</div>
            <div class="card-body p-3">
                <div class="row g-2">
                    @foreach([
                        ['icon'=>'phone','label'=>'Call Client','color'=>'#d1fae5','ico'=>'#059669'],
                        ['icon'=>'envelope','label'=>'Send Email','color'=>'#fef3c7','ico'=>'#d97706'],
                        ['icon'=>'file-invoice','label'=>'Create Quotation','color'=>'#ede9fe','ico'=>'#7c3aed'],
                        ['icon'=>'building','label'=>'Schedule Visit','color'=>'#dbeafe','ico'=>'#2563eb'],
                        ['icon'=>'sticky-note','label'=>'Add Note','color'=>'#ccfbf1','ico'=>'#0d9488'],
                        ['icon'=>'bell','label'=>'Set Reminder','color'=>'#fee2e2','ico'=>'#dc2626'],
                    ] as $qa)
                    <div class="col-4">
                        <div class="quick-action-btn">
                            <div class="qa-icon" style="background:{{ $qa['color'] }}">
                                <i class="fas fa-{{ $qa['icon'] }}" style="color:{{ $qa['ico'] }};font-size:.8rem"></i>
                            </div>
                            <span class="qa-label">{{ $qa['label'] }}</span>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
