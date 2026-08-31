@extends('layouts.app')
@section('title', 'Request DO')
@section('page-title', 'Request DO')
@section('page-subtitle', 'Alur: Request DO → Sales Admin → Finance & DP → Sales Manager')

@section('content')
    <div class="row g-3">
        <div class="col-12">

            {{-- Header + KPI --}}
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPoModal">
                        <i class="fas fa-plus me-1"></i> Tambah Request DO
                    </button>
                    <a href="{{ route('request-orders.export', request()->query()) }}"
                        class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-download me-1"></i> Export Excel
                    </a>
                </div>
                <div class="d-flex gap-3 flex-wrap">
                    @foreach([[$volumeDo, 'Total DO Dibuat', '#111'], [$activeRequestCount, 'Request Aktif', '#2563eb'], [$issuedDoCount, 'DO Terbit', '#059669'], [$cancelledRequestCount, 'Request Batal', '#dc2626'], [$revenue, 'Revenue', '#111111'], [$grossProfit, 'Gross Profit', '#10b981']] as $s)
                        <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}"
                            style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                            <div style="font-size:{{ $loop->index >= 1 ? '1rem' : '1.2rem' }};font-weight:800;color:{{ $s[2] }}">
                                {{ $loop->index >= 4 ? idr($s[0]) : $s[0] }}
                            </div>
                            <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
                        </div>
                    @endforeach
                    <div class="text-center ps-3" style="border-left:1px solid var(--border-color)">
                        <div style="font-size:1rem;font-weight:800;color:#059669">{{ $dpTakenCount }} DO</div>
                        <div style="font-size:.68rem;color:var(--text-muted)">DP Terambil</div>
                        <div style="font-size:.65rem;color:#059669">{{ idr($dpTakenAmount) }}</div>
                    </div>
                    <div class="text-center ps-3" style="border-left:1px solid var(--border-color)">
                        <div style="font-size:1rem;font-weight:800;color:#6b7280">{{ $dpNotTakenCount }} DO</div>
                        <div style="font-size:.68rem;color:var(--text-muted)">DP Tidak Terambil</div>
                        <div style="font-size:.65rem;color:#6b7280">{{ idr($dpNotTakenAmount) }}</div>
                    </div>
                </div>
            </div>

            <ul class="nav nav-pills mb-3" style="gap:6px">
                <li class="nav-item"><a class="nav-link {{ $tab === 'active' ? 'active' : '' }}" href="{{ route('request-orders.index', array_merge(request()->query(), ['tab'=>'active', 'page'=>null])) }}">Request Aktif</a></li>
                <li class="nav-item"><a class="nav-link {{ $tab === 'cancelled' ? 'active bg-danger' : '' }}" href="{{ route('request-orders.index', array_merge(request()->query(), ['tab'=>'cancelled', 'page'=>null])) }}">Request Batal <span class="badge bg-light text-dark ms-1">{{ $cancelledRequestCount }}</span></a></li>
            </ul>

            {{-- Filter --}}
            <form method="GET" action="{{ route('request-orders.index') }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm"
                                    value="{{ $startDate }}"></div>
                            <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm"
                                    value="{{ $endDate }}"></div>
                            <div class="col-md-2">
                                <select name="flow" class="form-select form-select-sm">
                                    <option value="all">Semua Tahap</option>
                                    @foreach($flowOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($flow == $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="operational_status" class="form-select form-select-sm">
                                    <option value="all">Semua Status DO</option>
                                    @foreach($operationalStatusOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($operationalStatus == $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <select name="dp_status" class="form-select form-select-sm">
                                    <option value="all">Semua Status DP</option>
                                    @foreach($dpStatusOptions as $key => $label)
                                        <option value="{{ $key }}" @selected($dpStatus == $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="text" name="search" class="form-control form-control-sm"
                                    placeholder="Cari DO, customer, layanan..." value="{{ $search }}">
                            </div>
                            <div class="col-md-1"><button type="submit" class="btn btn-primary btn-sm w-100"><i
                                        class="fas fa-search"></i></button></div>
                        </div>
                    </div>
                </div>
            </form>

            {{-- Table --}}
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:13px">
                            <thead style="background:#f8f9fa">
                                <tr>
                                    <th class="px-3 py-2" style="width:28px"></th>
                                    <th class="px-3 py-2">No. DO</th>
                                    <th class="py-2">Customer</th>
                                    <th class="py-2">Sales PIC</th>
                                    <th class="py-2">Revenue</th>
                                    <th class="py-2">HPP</th>
                                    <th class="py-2">Gross Profit</th>
                                    <th class="py-2">Margin</th>
                                    <th class="py-2">Status DO</th>
                                    <th class="py-2">DP</th>
                                    <th class="py-2">Tahap Flow</th>
                                    <th class="py-2">Tgl Order</th>
                                    <th class="py-2">Service Type</th>
                                    <th class="py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($dos as $po)
                                    @php
                                        $sc = ['Done' => ['#d1fae5', '#059669'], 'In Progress' => ['#e5e5e5', '#111111'], 'Cancelled' => ['#fee2e2', '#dc2626']];
                                        $c = $sc[$po->status] ?? ['#f3f4f6', '#6b7280'];
                                    @endphp
                                    <tr id="po-row-{{ $po->id }}">
                                        <td class="px-3 py-2" style="text-align:center">
                                            <button class="btn btn-sm" style="padding:2px 6px;border:none;background:none;color:#6b7280"
                                                onclick="toggleDetail({{ $po->id }}, this)" title="Lihat detail item">
                                                <i class="fas fa-chevron-right" style="font-size:10px;transition:.2s"></i>
                                            </button>
                                        </td>
                                        <td class="px-3 py-2" style="font-weight:700;color:var(--primary)">{{ $po->do_number }}</td>
                                        <td class="py-2" style="font-size:12px">{{ $po->customer?->company_name ?? '-' }}</td>
                                        <td class="py-2" style="font-size:12px;font-weight:600">{{ $po->salesUser?->name ?? '-' }}</td>
                                        <td class="py-2" style="font-weight:600;color:var(--primary);white-space:nowrap">{{ idr($po->total_revenue) }}</td>
                                        <td class="py-2" style="color:#dc2626;font-size:12px;white-space:nowrap">{{ idr($po->total_cost) }}</td>
                                        <td class="py-2" style="font-weight:600;color:#10b981;white-space:nowrap">{{ idr($po->gross_profit) }}</td>
                                        <td class="py-2" style="font-size:12px;color:#6b7280">{{ $po->gross_margin }}%</td>
                                        <td class="py-2" style="min-width:150px;max-width:190px">
                                            <span class="badge bg-{{ $po->operational_status_color }}">{{ $po->operational_status_label }}</span>
                                            @if($po->rescheduled_for)
                                                <div style="font-size:10px;color:#0369a1;margin-top:3px"><i class="fas fa-calendar-alt me-1"></i>{{ $po->rescheduled_for->format('d M Y') }}</div>
                                            @endif
                                            @if($po->operational_note)
                                                <div style="font-size:10px;color:#6b7280;line-height:1.25;margin-top:3px;white-space:normal" title="{{ $po->operational_note }}">
                                                    {{ \Illuminate\Support\Str::limit($po->operational_note, 60) }}
                                                </div>
                                            @endif
                                            @if($po->cancel_reason)
                                                <div style="font-size:10px;color:#b91c1c;line-height:1.25;margin-top:3px;white-space:normal"><b>Alasan batal:</b> {{ \Illuminate\Support\Str::limit($po->cancel_reason, 80) }}</div>
                                            @endif
                                        </td>
                                        <td class="py-2" style="min-width:130px">
                                            <span class="badge {{ $po->dp_request_active ? 'bg-'.$po->dp_status_color : 'bg-dark' }}">{{ $po->dp_request_active ? $po->dp_status_label : 'DP Nonaktif' }}</span>
                                            @if(($po->dp_amount ?? 0) > 0)
                                                <div style="font-size:10px;font-weight:600;color:#374151;margin-top:3px">{{ idr($po->dp_amount) }}</div>
                                            @endif
                                            @if(auth()->user()->canAccess('finance_dp_review') && in_array($po->request_status, ['finance', 'approval', 'assigned']))
                                                <div class="mt-1">
                                                    <a href="{{ route('request-orders.show', $po) }}#dp-management"
                                                        class="btn btn-sm btn-outline-info" style="font-size:10px;padding:2px 6px;white-space:nowrap"
                                                        title="{{ $po->dp_status === 'pending' ? 'Input data DP' : 'Perbarui data DP' }}">
                                                        <i class="fas fa-coins me-1"></i>{{ $po->dp_status === 'pending' ? 'Input DP' : 'Update DP' }}
                                                    </a>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            @php
                                                $flowColors = [
                                                    'draft' => ['#f3f4f6','#6b7280'], 'verifikasi' => ['#fef3c7','#d97706'],
                                                    'finance' => ['#e0f2fe','#0369a1'],
                                                    'dispatch' => ['#dbeafe','#2563eb'], 'approval' => ['#ede9fe','#7c3aed'],
                                                    'assigned' => ['#d1fae5','#059669'], 'rejected' => ['#fee2e2','#dc2626'],
                                                    'cancelled' => ['#fee2e2','#dc2626'],
                                                ];
                                                $fc = $flowColors[$po->request_status] ?? ['#f3f4f6','#6b7280'];
                                            @endphp
                                            <span style="font-size:11px;padding:2px 8px;border-radius:20px;font-weight:600;background:{{ $fc[0] }};color:{{ $fc[1] }}">{{ $po->flow_label }}</span>
                                        </td>
                                        <td class="py-2" style="color:#6b7280;font-size:12px">{{ $po->order_date?->format('d M Y') }}</td>
                                        <td class="py-2" style="color:#6b7280;font-size:11px">
                                            {{ $po->delivery_type ? ucwords($po->delivery_type) : '-' }}<br>
                                            @if($po->tracking_number)
                                            <span style="font-size:10px;color:#111111">{{ $po->tracking_number }}</span>
                                            @endif
                                        </td>
                                        <td class="py-2">
                                            <a href="{{ route('request-orders.show', $po->id) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px" title="Detail & Flow">
                                                <i class="fas fa-stream"></i>
                                            </a>
                                            @if(!in_array($po->request_status, ['assigned']))
                                            <button class="btn btn-sm btn-outline-secondary" style="padding:3px 7px" onclick="openEditPo({{ $po->id }})" title="{{ $po->request_status === 'approval' ? 'Edit & ajukan ulang' : 'Edit Request DO' }}">
                                                <i class="fas fa-pencil-alt"></i>
                                            </button>
                                            @endif
                                            @if(auth()->user()->canAccess('operational_status'))
                                            <button class="btn btn-sm btn-outline-{{ $po->operational_status_color }}" style="padding:3px 7px"
                                                onclick="openOperationalStatus({{ $po->id }})" title="Ubah status jalan/pending/reschedule/cancel">
                                                <i class="fas fa-exchange-alt"></i>
                                            </button>
                                            @endif
                                            @if($po->request_status !== 'cancelled' && in_array(auth()->user()->role, ['Admin','Sales Admin']))
                                            <form method="POST" action="{{ route('request-orders.cancel', $po) }}" class="d-inline cancel-request-form">@csrf
                                                <input type="hidden" name="reason">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" style="padding:3px 7px" title="Batalkan Request DO"><i class="fas fa-ban"></i></button>
                                            </form>
                                            @elseif($po->request_status === 'cancelled' && in_array(auth()->user()->role, ['Admin','Sales Admin']))
                                            <form method="POST" action="{{ route('request-orders.reactivate', $po) }}" class="d-inline">@csrf
                                                <button class="btn btn-sm btn-outline-success" style="padding:3px 7px" onclick="return confirm('Aktifkan kembali request ini?')" title="Aktifkan kembali"><i class="fas fa-undo"></i></button>
                                            </form>
                                            @endif
                                            @include('components.delete-request-button', [
                                                'module'  => 'request-orders',
                                                'id'      => $po->id,
                                                'label'   => $po->do_number,
                                                'pending' => in_array($po->id, $pendingDeletionDoIds ?? []),
                                            ])
                                        </td>
                                    </tr>
                                    {{-- Detail row (collapsed) --}}
                                    <tr id="po-detail-{{ $po->id }}" style="display:none;background:#f8faff">
                                        <td></td>
                                        <td colspan="13" class="px-3 py-2">
                                            <div style="font-size:11px;font-weight:600;color:#6b7280;margin-bottom:6px;text-transform:uppercase;letter-spacing:.5px">
                                                Detail Item — {{ $po->do_number }}
                                                @if($po->notes) <span style="font-weight:400;color:#9ca3af;margin-left:8px"><i class="fas fa-sticky-note me-1"></i>{{ $po->notes }}</span> @endif
                                            </div>
                                            <table style="width:100%;font-size:12px;border-collapse:collapse">
                                                <thead>
                                                    <tr style="background:#e8f0fe">
                                                        <th style="padding:5px 8px;text-align:left;font-size:11px;color:#3b4a6b">Layanan</th>
                                                        <th style="padding:5px 8px;text-align:center;font-size:11px;color:#3b4a6b">Satuan</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Tonase</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Qty</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Harga Beli</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Harga Jual</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Subtotal Revenue</th>
                                                        <th style="padding:5px 8px;text-align:right;font-size:11px;color:#3b4a6b">Gross Profit</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($po->items as $item)
                                                    <tr style="border-bottom:1px solid #e5e7eb">
                                                        <td style="padding:5px 8px;font-weight:600">{{ $item->service_name }}</td>
                                                        <td style="padding:5px 8px;text-align:center;color:#6b7280">{{ $item->unit }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:#6b7280">{{ $item->tonnage !== null ? number_format($item->tonnage, 2, ',', '.') : '-' }}</td>
                                                        <td style="padding:5px 8px;text-align:right">{{ number_format($item->qty, 2, ',', '.') }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:#dc2626">{{ idr($item->buy_price) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:var(--primary)">{{ idr($item->sell_price) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;font-weight:600;color:var(--primary)">{{ idr($item->qty * $item->sell_price) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;font-weight:600;color:#10b981">{{ idr(($item->sell_price - $item->buy_price) * $item->qty) }}</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr style="background:#f0f4ff;font-weight:700">
                                                        <td colspan="6" style="padding:5px 8px;text-align:right;font-size:11px;color:#6b7280">TOTAL</td>
                                                        <td style="padding:5px 8px;text-align:right;color:var(--primary)">{{ idr($po->total_revenue) }}</td>
                                                        <td style="padding:5px 8px;text-align:right;color:#10b981">{{ idr($po->gross_profit) }}</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="14" class="text-center py-4" style="color:#9ca3af">Belum ada data DO pada
                                            periode ini</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($dos->hasPages())
                    <div class="px-3 py-2">{{ $dos->links() }}</div>@endif
                </div>
            </div>
        </div>
    </div>

    @if(auth()->user()->canAccess('operational_status'))
    <div class="modal fade" id="operationalStatusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" id="operationalStatusForm">
                    @csrf
                    <div class="modal-header">
                        <div>
                            <h6 class="modal-title fw-bold mb-1">Ubah Status Operasional DO</h6>
                            <div id="operationalStatusDoNumber" class="text-muted" style="font-size:12px"></div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status DO <span class="text-danger">*</span></label>
                            <select name="operational_status" id="operationalStatusSelect" class="form-select" onchange="toggleRescheduleDate()" required>
                                @foreach($operationalStatusOptions as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Status ini terpisah dari Tahap Flow approval.</div>
                        </div>
                        <div class="mb-3 d-none" id="rescheduleDateWrap">
                            <label class="form-label">Jadwal Baru <span class="text-danger">*</span></label>
                            <input type="date" name="rescheduled_for" id="operationalRescheduledFor" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Keterangan <span id="operationalNoteRequired" class="text-danger">*</span></label>
                            <textarea name="operational_note" id="operationalNote" class="form-control" rows="3" maxlength="1000" placeholder="Alasan pending, reschedule, atau cancel"></textarea>
                            <div class="form-text">Wajib diisi jika DO tidak berjalan.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light btn-sm" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    @endif


    @push('styles')
        <style>
            .request-do-dialog { max-width:min(1380px, 94vw); height:calc(100dvh - 3rem); margin:1.5rem auto; }
            .request-do-modal .modal-content { border:0; border-radius:18px; overflow:hidden; box-shadow:0 28px 70px rgba(15,23,42,.18); max-height:100%; display:flex; flex-direction:column; }
            .request-do-modal .modal-content > form { flex:1 1 auto; min-height:0; display:flex; flex-direction:column; overflow:hidden; }
            .request-do-modal .modal-header { flex:0 0 auto; border-bottom:1px solid #e5e7eb; padding:1rem 1.25rem; background:#fff; }
            .request-do-modal .modal-footer { flex:0 0 auto; border-top:1px solid #e5e7eb; padding:.8rem 1.25rem; background:#fff; display:flex; align-items:center; }
            .request-do-modal .modal-body { flex:1 1 auto; min-height:0; overflow-y:auto; background:#f8fafc; padding:1rem 1.25rem 1.25rem; }
            .request-form-guide { display:flex; align-items:center; gap:.5rem; border:1px solid #dbeafe; background:#eff6ff; color:#1e3a8a; border-radius:12px; padding:.65rem .8rem; font-size:.75rem; margin-bottom:.9rem; }
            .request-guide-step { display:inline-flex; align-items:center; gap:.38rem; white-space:nowrap; }
            .request-guide-step b { width:20px; height:20px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#1d4ed8; color:#fff; font-size:.65rem; }
            .request-guide-arrow { color:#93c5fd; font-size:.65rem; }
            .request-required-note { margin-left:auto; color:#475569; white-space:nowrap; }
            .request-primary-grid { display:grid; grid-template-columns:minmax(0, 1.12fr) minmax(0, .88fr); gap:.9rem; align-items:stretch; margin-bottom:.9rem; }
            .request-primary-grid .request-section-card { height:100%; margin-bottom:0; }
            .request-primary-grid .row > [class*="col-lg-"] { width:50%; }
            .request-primary-grid .row > .col-12 { width:100%; }
            .request-section-card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:1rem; margin-bottom:.9rem; box-shadow:0 8px 22px rgba(15,23,42,.04); }
            .request-section-title, .request-summary { display:flex; align-items:center; gap:.6rem; color:#111827; font-weight:800; font-size:.82rem; letter-spacing:.02em; margin-bottom:.85rem; }
            .request-section-title small, .request-summary small { color:#6b7280; font-weight:500; letter-spacing:0; }
            .request-section-number { width:26px; height:26px; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; background:#111827; color:#fff; font-size:.72rem; flex:0 0 26px; }
            .request-do-modal .form-label { margin-bottom:.32rem; color:#374151; font-size:.75rem; font-weight:700; }
            .request-do-modal .form-control, .request-do-modal .form-select { border-radius:10px; border-color:#e5e7eb; font-size:.82rem; }
            .request-do-modal .form-control:focus, .request-do-modal .form-select:focus { border-color:#111827; box-shadow:0 0 0 .18rem rgba(17,24,39,.08); }
            .request-readonly-field { background:#f9fafb !important; cursor:default; color:#374151; }
            .request-optional-section { padding:0; }
            .request-optional-section > summary { list-style:none; cursor:pointer; padding:1rem; margin:0; }
            .request-optional-section > summary::-webkit-details-marker { display:none; }
            .request-optional-section > summary::after { content:'\f078'; font-family:'Font Awesome 6 Free'; font-weight:900; font-size:.72rem; color:#6b7280; margin-left:.35rem; transition:transform .2s ease; }
            .request-optional-section[open] > summary::after { transform:rotate(180deg); }
            .request-optional-body { padding:0 1rem 1rem; }
            .request-optional-badge { margin-left:auto; border:1px solid #bfdbfe; background:#eff6ff; color:#1d4ed8; border-radius:999px; padding:.2rem .55rem; font-size:.65rem; font-weight:700; }
            .request-ops-grid { display:grid; grid-template-columns:repeat(2, minmax(0, 1fr)); gap:.75rem; }
            .request-ops-group { min-width:0; background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:.8rem; }
            .request-ops-group .row { --bs-gutter-x:.75rem; --bs-gutter-y:.7rem; }
            .request-ops-group .row > [class*="col-"] { width:50%; }
            .request-ops-group .row > .request-full-field { width:100%; }
            .request-subsection-label { color:#475569; font-size:.68rem; font-weight:800; letter-spacing:.06em; text-transform:uppercase; border-bottom:1px dashed #cbd5e1; padding-bottom:.4rem; margin:0 0 .65rem; }
            .request-footer-note { color:#64748b; font-size:.72rem; }
            .request-items-head { display:flex; justify-content:space-between; align-items:center; gap:.75rem; margin-bottom:.85rem; }
            .request-items-table { min-width:1080px; font-size:.76rem; }
            .request-items-table thead th { background:#f8fafc; color:#374151; font-size:.72rem; vertical-align:middle; white-space:nowrap; }
            .request-items-table td { vertical-align:middle; }
            .request-items-table tfoot td { background:#f8fafc; font-weight:800; }
            .request-items-help { color:#6b7280; font-size:.72rem; }
            @media (max-width:1199.98px) {
                .request-primary-grid, .request-ops-grid { grid-template-columns:1fr; }
            }
            @media (max-width:991.98px) {
                .request-do-dialog { max-width:100vw; height:calc(100vh - 1rem); margin:.5rem; }
                .request-do-modal .modal-body { padding:.85rem; }
                .request-section-card { padding:.85rem; }
                .request-form-guide { overflow-x:auto; }
                .request-required-note { display:none; }
            }
            @media (max-width:575.98px) {
                .request-primary-grid .row > [class*="col-"], .request-ops-group .row > [class*="col-"] { width:100%; }
                .request-footer-note { display:none; }
            }
        </style>
    @endpush
    {{-- Modal Tambah Request DO --}}
    <div class="modal fade request-do-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="addPoModal" tabindex="-1">
        <div class="modal-dialog modal-xl request-do-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h6 class="modal-title fw-bold mb-1">Tambah Request DO</h6>
                        <div class="text-muted" style="font-size:.75rem">Lengkapi data utama dan rute. Detail operasional dapat diisi jika sudah tersedia.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="{{ route('request-orders.store') }}" id="addDoForm">
                    @csrf
                    <div class="modal-body">
                        <div class="request-form-guide" aria-label="Urutan pengisian Request DO">
                            <span class="request-guide-step"><b>1</b> Data utama</span>
                            <i class="fas fa-chevron-right request-guide-arrow"></i>
                            <span class="request-guide-step"><b>2</b> Rute & jadwal</span>
                            <i class="fas fa-chevron-right request-guide-arrow"></i>
                            <span class="request-guide-step"><b>3</b> Operasional <span class="text-muted">(opsional)</span></span>
                            <span class="request-required-note"><span class="text-danger fw-bold">*</span> wajib diisi</span>
                        </div>

                        <div class="request-primary-grid">
                        <div class="request-section-card">
                            <div class="request-section-title">
                                <span class="request-section-number">1</span>
                                <div>Informasi Customer & Penugasan <small>data utama request</small></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-6 col-md-6">
                                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                                    <select name="customer_id" id="addCustomerSelect" class="form-select" onchange="onCustomerChange(this,'addLeadDisplay'); setDefaultSalesPic(this,'addSalesPicSelect')" required>
                                        <option value="">-- Pilih Customer --</option>
                                        @foreach($customers as $c)
                                            <option value="{{ $c->id }}" data-name="{{ strtolower(trim($c->company_name)) }}" data-user-id="{{ $c->user_id }}">{{ $c->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-6 col-md-6">
                                    <label class="form-label">Sales PIC <span class="text-danger">*</span></label>
                                    <select name="user_id" id="addSalesPicSelect" class="form-select" required>
                                        <option value="">-- Pilih Sales PIC --</option>
                                        @foreach($salesUsers as $u)
                                            <option value="{{ $u->id }}" @selected(auth()->id() === $u->id)>{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Tgl Order <span class="text-danger">*</span></label>
                                    <input type="date" name="order_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" class="form-select">
                                        <option value="IDR">IDR</option>
                                        <option value="USD">USD</option>
                                        <option value="SGD">SGD</option>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-md-12">
                                    <label class="form-label">Linked Lead</label>
                                    <input type="text" id="addLeadDisplay" class="form-control request-readonly-field" placeholder="Otomatis dari Customer" readonly>
                                    <input type="hidden" name="lead_id" id="addLeadHidden" value="">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan</label>
                                    <input type="text" name="notes" class="form-control" placeholder="Contoh: permintaan khusus customer, instruksi operasional, atau catatan internal">
                                </div>
                            </div>
                        </div>

                        <div class="request-section-card">
                            <div class="request-section-title">
                                <span class="request-section-number">2</span>
                                <div>Rute & Jadwal Pengiriman <small>informasi perjalanan</small></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Service Type</label>
                                    <select name="delivery_type" id="addDeliveryType" class="form-select">
                                        <option value="">- Pilih -</option>
                                        @foreach(\App\Models\Vendor::serviceTypeOptions() as $dt)
                                            <option value="{{ ucwords($dt) }}">{{ ucwords($dt) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Origin</label>
                                    <input type="text" name="origin" class="form-control" placeholder="Kota asal / titik pickup">
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Destination</label>
                                    <input type="text" name="destination" class="form-control" placeholder="Kota tujuan / titik drop">
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Estimasi Tiba (ETA)</label>
                                    <input type="date" name="estimated_arrival" class="form-control">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Tracking Number</label>
                                    <input type="text" name="tracking_number" class="form-control" placeholder="Nomor resi / tracking jika ada">
                                </div>
                            </div>
                        </div>
                        </div>

                        <details class="request-section-card request-optional-section mb-3" id="addOperationalDetails">
                            <summary class="request-summary">
                                <span class="request-section-number">3</span>
                                <div>Detail Operasional Muatan <small>armada, container, dan driver</small></div>
                                <span class="request-optional-badge">Opsional · klik untuk buka</span>
                            </summary>
                            <div class="request-optional-body">
                                <div class="request-ops-grid">
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Armada & Muatan</div>
                                <div class="row">
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Checker</label><input type="text" name="checker" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Jenis Truck</label><input type="text" name="jenis_truck" class="form-control" placeholder="Trailer 20'/40'"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">No. Polisi</label><input type="text" name="no_pol" class="form-control" placeholder="B 1234 XXX"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Komoditi</label><input type="text" name="komoditi" class="form-control"></div>
                                </div>
                                </section>
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Lokasi Muat & Bongkar</div>
                                <div class="row">
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Depo</label><input type="text" name="depo" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Muat</label><input type="text" name="muat" class="form-control" placeholder="Lokasi muat"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Tgl Muat</label><input type="date" name="tgl_muat" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Bongkar</label><input type="text" name="bongkar" class="form-control" placeholder="Lokasi bongkar"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Tgl Bongkar</label><input type="date" name="tgl_bongkar" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Tujuan</label><input type="text" name="tujuan" class="form-control"></div>
                                </div>
                                </section>
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Container & Area</div>
                                <div class="row">
                                    <div class="col-lg-3 col-md-6"><label class="form-label">No. Container</label><input type="text" name="no_container" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">No. Seal</label><input type="text" name="no_seal" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Grade</label><input type="text" name="grade" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Sektor</label><input type="text" name="sektor" class="form-control"></div>
                                </div>
                                </section>
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Driver & Detail Alamat</div>
                                <div class="row">
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Supir</label><input type="text" name="supir" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">HP Supir</label><input type="text" name="hp_supir" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Kota</label><input type="text" name="kota" class="form-control"></div>
                                    <div class="col-12 request-full-field"><label class="form-label">Alamat</label><textarea name="alamat" class="form-control" rows="2" placeholder="Alamat lengkap operasional"></textarea></div>
                                </div>
                                </section>
                                </div>
                            </div>
                        </details>
                    </div>
                    <div class="modal-footer">
                        <div class="request-footer-note"><i class="fas fa-circle-info me-1"></i>Item layanan dan harga dilengkapi Accounting setelah request disimpan.</div>
                        <button type="button" class="btn btn-light btn-sm ms-auto" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm">Simpan Request DO</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal Edit DO --}}
    <div class="modal fade request-do-modal" data-bs-backdrop="static" data-bs-keyboard="false" id="editPoModal" tabindex="-1">
        <div class="modal-dialog modal-xl request-do-dialog modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h6 class="modal-title fw-bold mb-1">Edit Request DO — <span id="editPoNumber"></span></h6>
                        <div class="text-muted" id="editPoHelp" style="font-size:.75rem">Perbarui data utama, rute, atau detail operasional Request DO.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editDoForm">
                    @csrf @method('PUT')
                    <div class="modal-body">
                        <div class="request-form-guide" aria-label="Urutan pengisian Request DO">
                            <span class="request-guide-step"><b>1</b> Data utama</span>
                            <i class="fas fa-chevron-right request-guide-arrow"></i>
                            <span class="request-guide-step"><b>2</b> Rute & jadwal</span>
                            <i class="fas fa-chevron-right request-guide-arrow"></i>
                            <span class="request-guide-step"><b>3</b> Operasional <span class="text-muted">(opsional)</span></span>
                            <span class="request-required-note"><span class="text-danger fw-bold">*</span> wajib diisi</span>
                        </div>

                        <div class="request-primary-grid">
                        <div class="request-section-card">
                            <div class="request-section-title">
                                <span class="request-section-number">1</span>
                                <div>Informasi Customer & Penugasan <small>data utama request</small></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-6 col-md-6">
                                    <label class="form-label">Customer <span class="text-danger">*</span></label>
                                    <select name="customer_id" id="epCustomer" class="form-select" onchange="onCustomerChange(this,'epLeadDisplay'); setDefaultSalesPic(this,'epSalesPicSelect')" required>
                                        <option value="">-- Pilih Customer --</option>
                                        @foreach($customers as $c)
                                            <option value="{{ $c->id }}" data-name="{{ strtolower(trim($c->company_name)) }}" data-user-id="{{ $c->user_id }}">{{ $c->company_name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-6 col-md-6">
                                    <label class="form-label">Sales PIC <span class="text-danger">*</span></label>
                                    <select name="user_id" id="epSalesPicSelect" class="form-select" required>
                                        <option value="">-- Pilih Sales PIC --</option>
                                        @foreach($salesUsers as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Tgl Order <span class="text-danger">*</span></label>
                                    <input type="date" name="order_date" id="epDate" class="form-control" required>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Currency</label>
                                    <select name="currency" id="epCurrency" class="form-select">
                                        <option value="IDR">IDR</option>
                                        <option value="USD">USD</option>
                                        <option value="SGD">SGD</option>
                                    </select>
                                </div>
                                <div class="col-lg-6 col-md-12">
                                    <label class="form-label">Linked Lead</label>
                                    <input type="text" id="epLeadDisplay" class="form-control request-readonly-field" placeholder="Otomatis dari Customer" readonly>
                                    <input type="hidden" name="lead_id" id="epLeadHidden" value="">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Catatan</label>
                                    <input type="text" name="notes" id="epNotes" class="form-control" placeholder="Catatan tambahan">
                                </div>
                            </div>
                        </div>

                        <div class="request-section-card">
                            <div class="request-section-title">
                                <span class="request-section-number">2</span>
                                <div>Rute & Jadwal Pengiriman <small>informasi perjalanan</small></div>
                            </div>
                            <div class="row g-3">
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Service Type</label>
                                    <select name="delivery_type" id="epDeliveryType" class="form-select">
                                        <option value="">- Pilih -</option>
                                        @foreach(\App\Models\Vendor::serviceTypeOptions() as $dt)
                                            <option value="{{ ucwords($dt) }}">{{ ucwords($dt) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Origin</label>
                                    <input type="text" name="origin" id="epOrigin" class="form-control" placeholder="Kota asal / titik pickup">
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Destination</label>
                                    <input type="text" name="destination" id="epDestination" class="form-control" placeholder="Kota tujuan / titik drop">
                                </div>
                                <div class="col-lg-3 col-md-6">
                                    <label class="form-label">Estimasi Tiba (ETA)</label>
                                    <input type="date" name="estimated_arrival" id="epEta" class="form-control">
                                </div>
                                <div class="col-lg-4 col-md-6">
                                    <label class="form-label">Tracking Number</label>
                                    <input type="text" name="tracking_number" id="epTracking" class="form-control" placeholder="Nomor resi / tracking jika ada">
                                </div>
                            </div>
                        </div>
                        </div>

                        <details class="request-section-card request-optional-section mb-3" id="editOperationalDetails">
                            <summary class="request-summary">
                                <span class="request-section-number">3</span>
                                <div>Detail Operasional Muatan <small>armada, container, dan driver</small></div>
                                <span class="request-optional-badge">Opsional · klik untuk buka</span>
                            </summary>
                            <div class="request-optional-body">
                                <div class="request-ops-grid">
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Armada & Muatan</div>
                                <div class="row">
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Checker</label><input type="text" name="checker" id="epChecker" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Jenis Truck</label><input type="text" name="jenis_truck" id="epJenisTruck" class="form-control" placeholder="Trailer 20'/40'"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">No. Polisi</label><input type="text" name="no_pol" id="epNoPol" class="form-control" placeholder="B 1234 XXX"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Komoditi</label><input type="text" name="komoditi" id="epKomoditi" class="form-control"></div>
                                </div>
                                </section>
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Lokasi Muat & Bongkar</div>
                                <div class="row">
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Depo</label><input type="text" name="depo" id="epDepo" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Muat</label><input type="text" name="muat" id="epMuat" class="form-control" placeholder="Lokasi muat"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Tgl Muat</label><input type="date" name="tgl_muat" id="epTglMuat" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Bongkar</label><input type="text" name="bongkar" id="epBongkar" class="form-control" placeholder="Lokasi bongkar"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Tgl Bongkar</label><input type="date" name="tgl_bongkar" id="epTglBongkar" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Tujuan</label><input type="text" name="tujuan" id="epTujuan" class="form-control"></div>
                                </div>
                                </section>
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Container & Area</div>
                                <div class="row">
                                    <div class="col-lg-3 col-md-6"><label class="form-label">No. Container</label><input type="text" name="no_container" id="epNoContainer" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">No. Seal</label><input type="text" name="no_seal" id="epNoSeal" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Grade</label><input type="text" name="grade" id="epGrade" class="form-control"></div>
                                    <div class="col-lg-3 col-md-6"><label class="form-label">Sektor</label><input type="text" name="sektor" id="epSektor" class="form-control"></div>
                                </div>
                                </section>
                                <section class="request-ops-group">
                                <div class="request-subsection-label">Driver & Detail Alamat</div>
                                <div class="row">
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Supir</label><input type="text" name="supir" id="epSupir" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">HP Supir</label><input type="text" name="hp_supir" id="epHpSupir" class="form-control"></div>
                                    <div class="col-lg-4 col-md-6"><label class="form-label">Kota</label><input type="text" name="kota" id="epKota" class="form-control"></div>
                                    <div class="col-12 request-full-field"><label class="form-label">Alamat</label><textarea name="alamat" id="epAlamat" class="form-control" rows="2" placeholder="Alamat lengkap operasional"></textarea></div>
                                </div>
                                </section>
                                </div>
                            </div>
                        </details>
                    </div>
                    <div class="modal-footer">
                        <div class="request-footer-note"><i class="fas fa-circle-info me-1"></i>Item layanan dan harga dikelola Accounting dari halaman detail.</div>
                        <button type="button" class="btn btn-light btn-sm ms-auto" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary btn-sm" id="editPoSubmit">Simpan Perubahan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            let itemIndex = 1;

            function formatNum(n) {
                if (!n && n !== 0) return '';
                return Math.round(n).toLocaleString('id-ID');
            }

            function parseNum(str) {
                if (!str) return 0;
                return parseFloat(String(str).replace(/\./g, '').replace(',', '.')) || 0;
            }

            function formatPriceInput(el) {
                const raw = parseNum(el.value);
                if (raw > 0) el.value = formatNum(raw);
                calcRow(el);
            }

            function formatRp(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }

            function syncHidden(el, hiddenClass) {
                const row = el.closest('tr');
                const hidden = row.querySelector('.' + hiddenClass);
                // Simpan posisi cursor
                const pos = el.selectionStart;
                const raw = el.value.replace(/\./g, '').replace(/[^0-9]/g, '');
                const formatted = raw ? parseInt(raw).toLocaleString('id-ID') : '';
                const diff = formatted.length - el.value.length;
                el.value = formatted;
                // Restore cursor
                try { el.setSelectionRange(pos + diff, pos + diff); } catch (e) { }
                if (hidden) hidden.value = raw || 0;
            }

            function calcRow(el) {
                const row = el.closest('tr');
                const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                const buy = parseNum(row.querySelector('.item-buy')?.value);
                const sell = parseNum(row.querySelector('.item-sell')?.value);
                const profit = (sell - buy) * qty;
                row.querySelector('.item-profit').textContent = formatRp(profit);
                row.querySelector('.item-profit').style.color = profit >= 0 ? '#10b981' : '#dc2626';
                recalcTotal(row.closest('tbody').id);
            }

            function recalcTotal(bodyId) {
                const body = document.getElementById(bodyId);
                const prefix = bodyId === 'addItemsBody' ? 'add' : 'edit';
                let revenue = 0, profit = 0;
                body.querySelectorAll('tr').forEach(row => {
                    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                    const buy = parseNum(row.querySelector('.item-buy')?.value);
                    const sell = parseNum(row.querySelector('.item-sell')?.value);
                    revenue += qty * sell;
                    profit += (sell - buy) * qty;
                });
                document.getElementById(prefix + 'TotalRevenue').textContent = formatRp(revenue);
                document.getElementById(prefix + 'TotalProfit').textContent = formatRp(profit);
                document.getElementById(prefix + 'TotalProfit').style.color = profit >= 0 ? '#10b981' : '#dc2626';
            }

            // Item layanan dan harga dilengkapi Finance setelah request dibuat.
            const vendorServicesMap = {};
            const operationalStatusData = @js($operationalStatusData);
            const operationalStatusBaseUrl = @js(url('/request-orders'));

            function openOperationalStatus(id) {
                const data = operationalStatusData[id];
                if (!data) return;

                document.getElementById('operationalStatusForm').action = operationalStatusBaseUrl + '/' + id + '/operational-status';
                document.getElementById('operationalStatusDoNumber').textContent = data.number;
                document.getElementById('operationalStatusSelect').value = data.status || 'running';
                document.getElementById('operationalNote').value = data.note || '';
                document.getElementById('operationalRescheduledFor').value = data.rescheduled_for || '';
                toggleRescheduleDate();

                bootstrap.Modal.getOrCreateInstance(document.getElementById('operationalStatusModal')).show();
            }

            function toggleRescheduleDate() {
                const status = document.getElementById('operationalStatusSelect')?.value || 'running';
                const dateWrap = document.getElementById('rescheduleDateWrap');
                const dateInput = document.getElementById('operationalRescheduledFor');
                const noteInput = document.getElementById('operationalNote');
                const noteRequired = document.getElementById('operationalNoteRequired');
                const isRescheduled = status === 'rescheduled';
                const needsNote = status !== 'running';

                dateWrap?.classList.toggle('d-none', !isRescheduled);
                if (dateInput) dateInput.required = isRescheduled;
                if (noteInput) noteInput.required = needsNote;
                noteRequired?.classList.toggle('d-none', !needsNote);
            }

            // Map customer_id & company_name → lead info untuk Linked Lead
            const leadsByCustomerId = {};
            const leadsByName       = {};
            @foreach($leads as $l)
            @if($l->customer_id)
            leadsByCustomerId['{{ $l->customer_id }}'] = { id: {{ $l->id }}, label: '[{{ $l->lead_code }}] {{ addslashes($l->company_name) }}' };
            @endif
            leadsByName['{{ strtolower(trim($l->company_name)) }}'] = { id: {{ $l->id }}, label: '[{{ $l->lead_code }}] {{ addslashes($l->company_name) }}' };
            @endforeach

            function onVendorChange(sel, bodyId) {
                // Reset dropdown produk di semua rows body tersebut
                const vendorId = sel.value;
                const body = document.getElementById(bodyId);
                if (!body) return;
                body.querySelectorAll('.po-product-select').forEach(function(s) {
                    const svcs = vendorId && vendorServicesMap[vendorId] ? vendorServicesMap[vendorId] : [];
                    const tr = s.closest('tr');
                    const hidden = tr.querySelector('.po-product-hidden');
                    const unitInput = tr.querySelector('.po-unit-input');
                    const prevName = hidden ? (hidden.value || '') : '';

                    // Rebuild options
                    s.innerHTML = '<option value="">-- Pilih atau ketik --</option>';
                    svcs.forEach(p => {
                        const o = document.createElement('option');
                        o.value = p.service_name;
                        o.dataset.unit = p.unit || '';
                        o.textContent = p.service_name;
                        s.appendChild(o);
                    });
                    // Jika sebelumnya sudah ada nama (mis. ketik manual / dari edit) dan
                    // tidak ada di daftar vendor baru, pertahankan sebagai opsi agar tidak hilang.
                    if (prevName && prevName !== '__manual__' && !Array.from(s.options).some(o => o.value === prevName)) {
                        const keep = new Option(prevName, prevName, true, true);
                        s.appendChild(keep);
                    }
                    // Tambah opsi manual
                    const manualOpt = document.createElement('option');
                    manualOpt.value = '__manual__';
                    manualOpt.textContent = '+ Ketik manual...';
                    s.appendChild(manualOpt);

                    // Restore selection + sinkron hidden (hindari desync hidden vs select)
                    if (prevName && prevName !== '__manual__') {
                        s.value = prevName;
                        if (hidden) hidden.value = prevName;
                    } else {
                        if (hidden) hidden.value = '';
                    }
                    if (window.jQuery && $(s).data('select2')) {
                        $(s).val(s.value).trigger('change.select2');
                    }
                });
            }

            function onProductSelect(sel) {
                const tr = sel.closest('tr');
                const hiddenInput = tr.querySelector('.po-product-hidden');
                const unitInput = tr.querySelector('.po-unit-input');

                if (!hiddenInput) return;

                if (sel.value === '__manual__') {
                    const manual = prompt('Nama service:', '');
                    const productName = manual ? manual.trim() : '';

                    if (productName !== '') {
                        hiddenInput.value = productName;

                        let existingOpt = Array.from(sel.options).find(o => o.value === productName);
                        if (!existingOpt) {
                            existingOpt = new Option(productName, productName, true, true);
                            const manualOpt = Array.from(sel.options).find(o => o.value === '__manual__');
                            if (manualOpt) {
                                sel.insertBefore(existingOpt, manualOpt);
                            } else {
                                sel.appendChild(existingOpt);
                            }
                        }

                        existingOpt.selected = true;
                        sel.value = productName;

                        if (unitInput && !unitInput.value) {
                            unitInput.value = 'unit';
                        }

                        // Penting: trigger "change" penuh agar Select2 refresh tampilan text-nya.
                        if (window.jQuery && $(sel).data('select2')) {
                            $(sel).val(productName).trigger('change');
                            $(sel).select2('close');
                        }
                    } else {
                        hiddenInput.value = '';
                        sel.value = '';

                        if (window.jQuery && $(sel).data('select2')) {
                            $(sel).val('').trigger('change');
                            $(sel).select2('close');
                        }
                    }

                    return;
                }

                hiddenInput.value = sel.value || '';

                const opt = sel.options[sel.selectedIndex];
                if (opt && opt.dataset.unit && unitInput) {
                    unitInput.value = opt.dataset.unit;
                }
            }

            function addItemRow(bodyId, data = {}) {
                const idx = itemIndex++;
                const body = document.getElementById(bodyId);
                const prefix = bodyId === 'addItemsBody' ? 'items' : 'items';
                // Cari vendor yang dipilih
                const vendorSel = bodyId === 'addItemsBody' ? document.getElementById('addVendorSelect') : document.getElementById('epVendor');
                const vendorId = vendorSel ? vendorSel.value : null;
                const svcs = vendorId && vendorServicesMap[vendorId] ? vendorServicesMap[vendorId] : [];

                // Build service options
                let productOptions = '<option value="">-- Pilih atau ketik --</option>';
                let productExists = false;
                svcs.forEach(p => {
                    const selected = data.service_name === p.service_name ? 'selected' : '';
                    if (data.service_name === p.service_name) productExists = true;
                    productOptions += `<option value="${p.service_name}" data-unit="${p.unit||''}" ${selected}>${p.service_name}</option>`;
                });

                // Jika service berasal dari input manual / data lama, tetap tampilkan di dropdown saat edit.
                if (data.service_name && !productExists) {
                    productOptions += `<option value="${data.service_name}" selected>${data.service_name}</option>`;
                }

                productOptions += '<option value="__manual__">+ Ketik manual...</option>';

                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>
                    <input type="hidden" name="${prefix}[${idx}][service_name]" class="po-product-hidden" value="${data.service_name || ''}" required>
                    <select class="form-select form-select-sm po-product-select" onchange="onProductSelect(this)" data-hidden-name="${prefix}[${idx}][service_name]">
                        ${productOptions}
                    </select>
                </td>
                <td><input type="text" name="${prefix}[${idx}][unit]" class="form-control form-control-sm po-unit-input" value="${data.unit || 'unit'}"></td>
                <td><input type="number" name="${prefix}[${idx}][tonnage]" class="form-control form-control-sm item-tonnage" step="0.001" min="0" value="${data.tonnage ?? ''}" placeholder="0"></td>
                <td><input type="number" name="${prefix}[${idx}][qty]" class="form-control form-control-sm item-qty" step="0.001" min="0" value="${data.qty || ''}" required oninput="calcRow(this)"></td>
                <td>
                    <input type="hidden" name="${prefix}[${idx}][buy_price]" class="item-buy-hidden" value="${data.buy_price || 0}">
                    <input type="text" class="form-control form-control-sm item-buy" value="${data.buy_price ? formatNum(data.buy_price) : ''}" placeholder="0"
                        oninput="syncHidden(this,'item-buy-hidden');calcRow(this)"
                        onblur="formatPriceInput(this)">
                </td>
                <td>
                    <input type="hidden" name="${prefix}[${idx}][sell_price]" class="item-sell-hidden" value="${data.sell_price || 0}">
                    <input type="text" class="form-control form-control-sm item-sell" value="${data.sell_price ? formatNum(data.sell_price) : ''}" placeholder="0"
                        oninput="syncHidden(this,'item-sell-hidden');calcRow(this)"
                        onblur="formatPriceInput(this)">
                </td>
                <td class="item-profit text-end" style="font-weight:600;color:#10b981;vertical-align:middle">Rp 0</td>
                <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)" style="padding:2px 6px"><i class="fas fa-times"></i></button></td>
            `;
                body.appendChild(tr);

                // Row ditambahkan secara dinamis, jadi Select2 perlu di-init ulang khusus row baru.
                if (typeof initSelect2 === 'function') {
                    initSelect2(tr);
                }

                // Jika row berasal dari edit / manual product, paksa Select2 menampilkan value yang sudah ada.
                if (data.service_name) {
                    const productSelect = tr.querySelector('.po-product-select');
                    const hiddenInput = tr.querySelector('.po-product-hidden');

                    if (productSelect) {
                        let opt = Array.from(productSelect.options).find(o => o.value === data.service_name);
                        if (!opt) {
                            opt = new Option(data.service_name, data.service_name, true, true);
                            productSelect.appendChild(opt);
                        }

                        opt.selected = true;
                        productSelect.value = data.service_name;
                    }

                    if (hiddenInput) {
                        hiddenInput.value = data.service_name;
                    }

                    if (window.jQuery && productSelect && $(productSelect).data('select2')) {
                        $(productSelect).val(data.service_name).trigger('change');
                    }
                }

                if (data.qty && data.buy_price && data.sell_price) {
                    calcRow(tr.querySelector('.item-qty'));
                }
            }

            function removeRow(btn) {
                const row = btn.closest('tr');
                const body = row.closest('tbody');
                if (body.querySelectorAll('tr').length <= 1) { alert('Minimal 1 item layanan'); return; }
                row.remove();
                recalcTotal(body.id);
            }

            function normalizeDateForInput(value) {
                if (!value) return '';
                const str = String(value).trim();

                // Jika dari backend sudah Y-m-d, langsung pakai.
                const ymd = str.match(/^(\d{4})-(\d{2})-(\d{2})/);
                if (ymd) return `${ymd[1]}-${ymd[2]}-${ymd[3]}`;

                // Fallback untuk format seperti "24 May 2026" / Date string browser.
                const parsed = new Date(str);
                if (!isNaN(parsed.getTime())) {
                    const yyyy = parsed.getFullYear();
                    const mm = String(parsed.getMonth() + 1).padStart(2, '0');
                    const dd = String(parsed.getDate()).padStart(2, '0');
                    return `${yyyy}-${mm}-${dd}`;
                }

                return '';
            }

            function setDateInputValue(id, value) {
                const el = document.getElementById(id);
                if (!el) return;

                const dateValue = normalizeDateForInput(value);

                // Set native input value
                el.value = dateValue;
                el.setAttribute('value', dateValue);
                el.dataset.pendingDate = dateValue;

                // Air Datepicker: set lewat instance agar UI ikut terisi.
                if (el._airDatepicker && dateValue) {
                    el._airDatepicker.selectDate(new Date(dateValue));
                }

                // Fallback untuk datepicker lain yang mendengar event input/change.
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            }

            async function openEditPo(id) {
                const res = await fetch(`/request-orders/${id}/edit`);
                const po  = await res.json();

                document.getElementById('editDoForm').action = `/request-orders/${id}`;
                document.getElementById('editPoNumber').textContent = po.do_number;
                const awaitingManager = po.request_status === 'approval';
                const editForm = document.getElementById('editDoForm');
                editForm.dataset.awaitingManager = awaitingManager ? '1' : '0';
                document.getElementById('editPoHelp').textContent = awaitingManager
                    ? 'Request sedang menunggu approval. Perubahan akan dicatat dan diajukan ulang ke Sales Manager.'
                    : 'Perbarui data utama, rute, atau detail operasional Request DO.';
                document.getElementById('editPoSubmit').textContent = awaitingManager
                    ? 'Simpan & Ajukan Ulang'
                    : 'Simpan Perubahan';

                document.getElementById('epNotes').value    = po.notes || '';
                if (document.getElementById('epOrigin')) document.getElementById('epOrigin').value = po.origin || '';
                if (document.getElementById('epDestination')) document.getElementById('epDestination').value = po.destination || '';
                if (document.getElementById('epTracking')) document.getElementById('epTracking').value = po.tracking_number || '';
                if (document.getElementById('epEta')) document.getElementById('epEta').value = po.estimated_arrival || '';

                // Field operasional muatan (disamakan dengan modal Tambah)
                const opMap = {
                    epChecker:'checker', epJenisTruck:'jenis_truck', epNoPol:'no_pol', epKomoditi:'komoditi',
                    epDepo:'depo', epMuat:'muat', epTglMuat:'tgl_muat', epBongkar:'bongkar', epTglBongkar:'tgl_bongkar',
                    epTujuan:'tujuan', epNoContainer:'no_container', epNoSeal:'no_seal', epGrade:'grade', epSektor:'sektor',
                    epSupir:'supir', epHpSupir:'hp_supir', epKota:'kota', epAlamat:'alamat'
                };
                Object.keys(opMap).forEach(function(elId){
                    const el = document.getElementById(elId);
                    if (!el) return;
                    let val = po[opMap[elId]] ?? '';
                    // Normalisasi tanggal (kolom tgl_muat / tgl_bongkar bisa berformat ISO datetime)
                    if ((elId === 'epTglMuat' || elId === 'epTglBongkar') && val) {
                        val = String(val).substring(0, 10);
                    }
                    el.value = val;
                });

                // Detail operasional tetap ringkas, tetapi otomatis terbuka jika data sudah pernah diisi.
                const editOperationalDetails = document.getElementById('editOperationalDetails');
                if (editOperationalDetails) {
                    editOperationalDetails.open = Object.values(opMap).some(field => {
                        const value = po[field];
                        return value !== null && value !== undefined && String(value).trim() !== '';
                    });
                }

                const setSelect2 = (elId, val) => {
                    const el = document.getElementById(elId);
                    if (!el) return;
                    const v = (val === null || val === undefined) ? '' : String(val);
                    // Jika value tidak ada di daftar opsi, sisipkan opsi sementara agar tetap tampil.
                    if (v !== '' && !Array.from(el.options).some(o => String(o.value) === v)) {
                        const tmp = document.createElement('option');
                        tmp.value = v;
                        tmp.textContent = v;
                        el.appendChild(tmp);
                    }
                    // Init Select2 bila belum (agar tampilan ter-update saat val di-set)
                    if (window.jQuery && typeof initSelect2 === 'function' && !$(el).data('select2')) {
                        initSelect2(el.closest('.modal') || document);
                    }
                    if (window.jQuery && $(el).data('select2')) {
                        $(el).val(v || null).trigger('change');
                    } else {
                        el.value = v;
                    }
                };

                // Normalisasi service type ke ucwords agar cocok dengan opsi dropdown
                var _dt = (po.delivery_type || '').toLowerCase().replace(/\b\w/g, function(c){ return c.toUpperCase(); });

                // Simpan nilai untuk re-apply setelah modal tampil (Select2 perlu modal visible)
                const _fillSelects = () => {
                    setSelect2('epCurrency', po.currency);
                    setSelect2('epDeliveryType', _dt);
                    setSelect2('epCustomer', po.customer_id);
                    setSelect2('epSalesPicSelect', po.user_id);
                };
                _fillSelects();

                // Auto-fill linked lead berdasarkan customer
                const epCustEl = document.getElementById('epCustomer');
                if (epCustEl) onCustomerChange(epCustEl, 'epLeadDisplay');
                // Override jika DO punya lead_id spesifik
                if (po.lead_id) {
                    const epLeadHid  = document.getElementById('epLeadHidden');
                    const epLeadDisp = document.getElementById('epLeadDisplay');
                    if (epLeadHid) epLeadHid.value = po.lead_id;
                    // Cari label lead
                    let label = '';
                    for (const key in leadsByCustomerId) {
                        if (String(leadsByCustomerId[key].id) === String(po.lead_id)) {
                            label = leadsByCustomerId[key].label; break;
                        }
                    }
                    if (!label) {
                        for (const key in leadsByName) {
                            if (String(leadsByName[key].id) === String(po.lead_id)) {
                                label = leadsByName[key].label; break;
                            }
                        }
                    }
                    if (epLeadDisp && label) epLeadDisp.value = label;
                }

                // Tgl Order harus di-set langsung sebelum modal tampil dan setelah modal tampil.
                // Ini menghindari case input date kosong karena re-render modal / plugin select2.
                setDateInputValue('epDate', po.order_date);

                const modalEl = document.getElementById('editPoModal');
                const modal = bootstrap.Modal.getOrCreateInstance(modalEl);

                const oldHandler = modalEl._shownHandler;
                if (oldHandler) modalEl.removeEventListener('shown.bs.modal', oldHandler);

                const shownHandler = function () {
                    // Re-apply Select2 values setelah modal benar-benar tampil
                    // (Select2 sering gagal render value saat di-set ketika modal masih hidden).
                    setSelect2('epCurrency', po.currency);
                    setSelect2('epDeliveryType', _dt);
                    setSelect2('epCustomer', po.customer_id);
                    setSelect2('epSalesPicSelect', po.user_id);
                    setDateInputValue('epDate', po.order_date);
                    setTimeout(function () {
                        setSelect2('epCurrency', po.currency);
                        setSelect2('epDeliveryType', _dt);
                        setSelect2('epCustomer', po.customer_id);
                        setSelect2('epSalesPicSelect', po.user_id);
                        setDateInputValue('epDate', po.order_date);
                    }, 60);
                };

                modalEl._shownHandler = shownHandler;
                modalEl.addEventListener('shown.bs.modal', shownHandler, { once: true });

                modal.show();
            }


            function setDefaultSalesPic(custSel, salesSelectId) {
                const salesSelect = document.getElementById(salesSelectId);
                if (!salesSelect || !custSel) return;
                const opt = custSel.options[custSel.selectedIndex];
                const userId = opt?.dataset?.userId || '';
                if (userId) {
                    if (window.jQuery && $(salesSelect).data('select2')) {
                        $(salesSelect).val(userId).trigger('change');
                    } else {
                        salesSelect.value = userId;
                    }
                }
            }

            // ── Filter Linked Lead berdasarkan Customer yang dipilih ──
            function onCustomerChange(custSel, displayId) {
                const customerId   = String(custSel.value || '').trim();
                const customerName = String(custSel.options[custSel.selectedIndex]?.dataset?.name || '').trim();
                const isAdd        = displayId === 'addLeadDisplay';
                const display      = document.getElementById(displayId);
                const hiddenId     = isAdd ? 'addLeadHidden' : 'epLeadHidden';
                const hidden       = document.getElementById(hiddenId);

                if (!customerId) {
                    if (display) { display.value = ''; display.placeholder = 'Otomatis dari Customer'; }
                    if (hidden)  hidden.value = '';
                    return;
                }

                // Cari lead: by customer_id dulu, fallback by nama
                let lead = leadsByCustomerId[customerId]
                        || leadsByName[customerName]
                        || null;

                if (lead) {
                    if (display) display.value = lead.label;
                    if (hidden)  hidden.value  = lead.id;
                } else {
                    if (display) { display.value = ''; display.placeholder = 'Tidak ada lead terkait'; }
                    if (hidden)  hidden.value = '';
                }
            }

            // ── Expand/collapse detail row DO ──
            function toggleDetail(poId, btn) {
                const detail = document.getElementById('po-detail-' + poId);
                const icon   = btn.querySelector('i');
                if (!detail) return;
                if (detail.style.display === 'none') {
                    detail.style.display = 'table-row';
                    icon.style.transform = 'rotate(90deg)';
                } else {
                    detail.style.display = 'none';
                    icon.style.transform = 'rotate(0deg)';
                }
            }

            // ── Init modal Add DO: tambah 1 row kosong saat modal dibuka, reset saat ditutup ──
            document.addEventListener('DOMContentLoaded', function () {
                const addModal = document.getElementById('addPoModal');
                if (!addModal) return;

                addModal.addEventListener('show.bs.modal', function () {
                    const body = document.getElementById('addItemsBody');
                    if (body && body.querySelectorAll('tr').length === 0) {
                        addItemRow('addItemsBody');
                    }
                });

                addModal.addEventListener('hidden.bs.modal', function () {
                    // Reset tbody saat modal ditutup
                    const body = document.getElementById('addItemsBody');
                    if (body) body.innerHTML = '';
                    itemIndex = 0;
                });
            });

            // ── FIX SAVE DO: sinkronkan hidden + bersihkan baris kosong sebelum submit ──
            // Penyebab "kadang tidak save": hidden service_name / harga tidak ter-sync
            // dari select/input (mis. quirk Select2, ganti vendor), sehingga validasi
            // server (items.*.service_name required) gagal tanpa pesan jelas.
            function prepareDoSubmit(form, bodyId) {
                const body = document.getElementById(bodyId);
                if (!body) return true;

                let validCount = 0;

                body.querySelectorAll('tr').forEach(function (row) {
                    const hidden = row.querySelector('.po-product-hidden');
                    const select = row.querySelector('.po-product-select');

                    // 1. Sinkron nama layanan: utamakan nilai select aktif
                    if (hidden) {
                        let name = '';
                        if (select && select.value && select.value !== '__manual__') {
                            name = select.value;
                        } else if (hidden.value && hidden.value !== '__manual__') {
                            name = hidden.value;
                        }
                        hidden.value = (name || '').trim();
                    }

                    // 2. Sinkron harga hidden dari input text (jaga-jaga belum ter-sync)
                    const buyTxt  = row.querySelector('.item-buy');
                    const buyHid  = row.querySelector('.item-buy-hidden');
                    const sellTxt = row.querySelector('.item-sell');
                    const sellHid = row.querySelector('.item-sell-hidden');
                    if (buyHid)  buyHid.value  = parseNum(buyTxt ? buyTxt.value : 0)  || 0;
                    if (sellHid) sellHid.value = parseNum(sellTxt ? sellTxt.value : 0) || 0;

                    // 3. Tentukan baris valid (punya nama + qty > 0)
                    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                    const hasName = hidden && hidden.value !== '';

                    if (!hasName || qty <= 0) {
                        // Baris kosong / tak lengkap → buang agar tidak menggagalkan validasi
                        if (!hasName && qty <= 0) {
                            row.remove();
                            return;
                        }
                        // Jika sebagian terisi tapi tak lengkap, tandai untuk pesan
                        if (!hasName) {
                            row.querySelector('.po-product-select')?.classList.add('is-invalid');
                        }
                    } else {
                        validCount++;
                    }
                });

                if (validCount === 0) {
                    alert('Minimal 1 item layanan dengan Nama Layanan dan Qty harus diisi.');
                    return false;
                }

                // Pastikan ada baris bernama tapi qty 0 / sebaliknya tidak lolos diam-diam
                let incomplete = false;
                body.querySelectorAll('tr').forEach(function (row) {
                    const hidden = row.querySelector('.po-product-hidden');
                    const qty = parseFloat(row.querySelector('.item-qty')?.value) || 0;
                    if (hidden && hidden.value !== '' && qty <= 0) incomplete = true;
                    if (hidden && hidden.value === '' && qty > 0) incomplete = true;
                });
                if (incomplete) {
                    alert('Ada item yang belum lengkap (Nama Layanan & Qty wajib diisi bersamaan). Periksa kembali.');
                    return false;
                }

                return true;
            }

            (function attachDoSubmitGuards() {
                const addForm = document.getElementById('addDoForm');
                if (addForm) {
                    addForm.addEventListener('submit', function (e) {
                        if (!prepareDoSubmit(addForm, 'addItemsBody')) e.preventDefault();
                    });
                }
                const editForm = document.getElementById('editDoForm');
                if (editForm) {
                    editForm.addEventListener('submit', function (e) {
                        if (!prepareDoSubmit(editForm, 'editItemsBody')) e.preventDefault();
                    });
                }
            })();

            document.querySelectorAll('.cancel-request-form').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    const reason = window.prompt('Tuliskan alasan pembatalan Request DO:');
                    if (!reason || !reason.trim()) {
                        event.preventDefault();
                        return;
                    }
                    form.querySelector('[name="reason"]').value = reason.trim();
                });
            });
        </script>
    @endpush
@endsection
