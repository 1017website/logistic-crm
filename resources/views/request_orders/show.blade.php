@extends('layouts.app')
@section('title', 'Detail Request DO')
@section('page-title', $requestOrder->do_number)
@section('page-subtitle', 'Alur: Sales Admin → Finance & DP → Sales Manager')

@section('content')
@php
    $u = auth()->user();
    // Aturan boleh-edit harga dipusatkan di model agar controller dan tampilan
    // tidak pernah berbeda pendapat.
    $canEditRequestPricing = $u->canAccess('request_item_pricing') && $requestOrder->pricing_editable;
    $canEditJobDetails = $u->canAccess('job_details') && $requestOrder->pricing_editable;
    $itemRevenue = $requestOrder->items->sum(fn ($item) => $item->subtotal_revenue);
@endphp
@if(session('success'))<div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>@endif
@if(session('warning'))<div class="alert alert-warning py-2" style="font-size:13px"><i class="fas fa-exclamation-triangle me-2"></i>{{ session('warning') }}</div>@endif
@foreach($errors->all() as $e)<div class="alert alert-danger py-2" style="font-size:13px">{{ $e }}</div>@endforeach
@if($requestOrder->price_correction_open && $requestOrder->request_status === 'assigned')
<div class="alert alert-warning py-2" style="font-size:13px">
    <i class="fas fa-unlock me-2"></i><b>Koreksi harga dibuka.</b>
    Finance dapat memperbaiki item layanan &amp; rincian pekerjaan. Setelah selesai, harga perlu di-approve ulang Sales Manager
    karena DO tidak dapat ditutup selama harga belum disetujui.
</div>
@elseif($requestOrder->request_status === 'assigned' && !$requestOrder->do_approved)
<div class="alert alert-warning py-2" style="font-size:13px">
    <i class="fas fa-exclamation-triangle me-2"></i><b>Harga DO belum disetujui.</b>
    DO tidak dapat ditutup sebelum harga di-approve. Bila harganya keliru, Sales Manager dapat menekan Unapprove untuk membuka koreksi harga.
</div>
@endif
<div class="row g-3">
    {{-- ─────────── Kolom kiri: info & item ─────────── --}}
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1" style="font-weight:800">{{ $requestOrder->do_number }}</h5>
                        <span class="badge bg-{{ $requestOrder->flow_color }}">{{ $requestOrder->flow_label }}</span>
                        <span class="badge bg-{{ $requestOrder->operational_status_color }}">{{ $requestOrder->operational_status_label }}</span>
                        <span class="badge {{ $requestOrder->dp_request_active ? 'bg-'.$requestOrder->dp_status_color : 'bg-dark' }}">{{ $requestOrder->dp_request_active ? $requestOrder->dp_status_label : 'DP Nonaktif' }}</span>
                        <span class="badge bg-light text-dark border">{{ $requestOrder->status }}</span>
                    </div>
                    <a href="{{ route('request-orders.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>

                <div class="row g-2" style="font-size:13px">
                    <div class="col-md-6"><span class="text-muted">Customer</span><br><b>{{ $requestOrder->customer?->company_name ?? '-' }}</b></div>
                    <div class="col-md-6"><span class="text-muted">Sales PIC</span><br><b>{{ $requestOrder->salesUser?->name ?? '-' }}</b></div>
                    <div class="col-md-6"><span class="text-muted">Origin</span><br>{{ $requestOrder->origin ?? '-' }}</div>
                    <div class="col-md-6"><span class="text-muted">Destination</span><br>{{ $requestOrder->destination ?? '-' }}</div>
                    <div class="col-md-6"><span class="text-muted">Tipe Pengiriman</span><br>{{ $requestOrder->delivery_type ?? '-' }}</div>
                    <div class="col-md-6"><span class="text-muted">Tgl Order</span><br>{{ $requestOrder->order_date?->format('d M Y') ?? '-' }}</div>
                    @if($requestOrder->notes)
                    <div class="col-12"><span class="text-muted">Catatan</span><br>{{ $requestOrder->notes }}</div>
                    @endif
                    @if($requestOrder->operational_note)
                    <div class="col-12"><span class="text-muted">Keterangan Status DO</span><br>{{ $requestOrder->operational_note }}</div>
                    @endif
                    @if($requestOrder->rescheduled_for)
                    <div class="col-md-6"><span class="text-muted">Jadwal Reschedule</span><br><b>{{ $requestOrder->rescheduled_for->format('d M Y') }}</b></div>
                    @endif
                    @if(!$requestOrder->dp_request_active)
                    <div class="col-md-6"><span class="text-muted">Request DP</span><br><b>Nonaktif</b></div>
                    @elseif($requestOrder->dp_status !== 'pending')
                    <div class="col-md-6"><span class="text-muted">Status DP</span><br><b>{{ $requestOrder->dp_status_label }}</b> · {{ idr($requestOrder->dp_amount) }}</div>
                    <div class="col-md-6"><span class="text-muted">Review Finance</span><br>{{ $requestOrder->dpReviewer?->name ?? '-' }} · {{ $requestOrder->dp_reviewed_at?->format('d M Y H:i') }}</div>
                    @if($requestOrder->dp_note)<div class="col-12"><span class="text-muted">Catatan DP</span><br>{{ $requestOrder->dp_note }}</div>@endif
                    @endif
                </div>
            </div>
        </div>

        {{-- Item layanan --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <div>
                        <h6 class="mb-0" style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Item Layanan & Harga</h6>
                        <small class="text-muted">Dilengkapi oleh Accounting setelah request dibuat.</small>
                    </div>
                    @if($canEditRequestPricing)
                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#itemModal" onclick="openAddItem()">
                        <i class="fas fa-plus me-1"></i> Tambah Item
                    </button>
                    @endif
                </div>
                <table class="table table-sm mb-0" style="font-size:12px">
                    <thead><tr>
                        <th>Layanan</th><th class="text-end">Qty</th><th class="text-end">Beli</th>
                        <th class="text-end">Jual</th><th class="text-end">Subtotal</th>
                        @if($canEditRequestPricing)<th></th>@endif
                    </tr></thead>
                    <tbody>
                        @forelse($requestOrder->items as $it)
                        <tr>
                            <td>{{ $it->service_name }} <span class="text-muted">{{ $it->unit }}</span></td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($it->qty,3),'0'),'.') }}</td>
                            <td class="text-end">{{ idr($it->buy_price) }}</td>
                            <td class="text-end">{{ idr($it->sell_price) }}</td>
                            <td class="text-end">{{ idr($it->subtotal_revenue) }}</td>
                            @if($canEditRequestPricing)
                            <td class="text-nowrap text-end">
                                <button class="btn btn-sm btn-outline-secondary" style="padding:2px 6px" data-bs-toggle="modal" data-bs-target="#itemModal" onclick='openEditItem(@json($it))'><i class="fas fa-pencil-alt"></i></button>
                                <form method="POST" action="{{ route('request-order-items.destroy', $it) }}" class="d-inline" onsubmit="return confirm('Hapus item layanan ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" style="padding:2px 6px"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="{{ $canEditRequestPricing ? 6 : 5 }}" class="text-muted py-3 text-center">Belum ada item layanan. Menunggu Finance melengkapi layanan dan harga.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot><tr style="font-weight:700">
                        <td colspan="4" class="text-end">Total Revenue</td>
                        <td class="text-end" style="color:var(--primary)">{{ idr($itemRevenue) }}</td>
                        @if($canEditRequestPricing)<td></td>@endif
                    </tr></tfoot>
                </table>
                @if($requestOrder->jobDetails->isNotEmpty())
                <div class="alert alert-info mt-2 mb-0 py-2" style="font-size:11px">
                    <i class="fas fa-info-circle me-1"></i>Total pada tabel ini hanya berasal dari Item Layanan. Nilai jual dan HPP utama berasal dari <b>Rincian Biaya per Pekerjaan</b> yang diisi Finance di bawah.
                </div>
                @endif
                @if($requestOrder->invoice_status !== 'uninvoiced')
                <div class="alert alert-light border mt-2 mb-0 py-2" style="font-size:11px">Item dikunci karena Request DO sudah masuk invoice.</div>
                @elseif($requestOrder->request_status === 'approval' && $canEditRequestPricing)
                <div class="alert alert-warning mt-2 mb-0 py-2" style="font-size:11px"><i class="fas fa-rotate me-1"></i>Perubahan harga akan dicatat dan diajukan ulang ke Sales Manager.</div>
                @endif
            </div>
        </div>

        {{-- Rincian biaya per pekerjaan --}}
        @php
            $jobs = $requestOrder->jobDetails;
            $sumBiaya = $requestOrder->total_cost;
            $sumJual  = $requestOrder->total_revenue;
            $lastFinanceUpdate = $jobs->sortByDesc('updated_at')->first();
        @endphp
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0" style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Rincian Biaya per Pekerjaan</h6>
                    @if($canEditJobDetails)
                    <button class="btn btn-sm btn-primary" style="padding:3px 10px" data-bs-toggle="modal" data-bs-target="#addJobModal"><i class="fas fa-plus me-1"></i> Tambah</button>
                    @endif
                </div>
                @if($lastFinanceUpdate)
                <div class="alert alert-light border py-2 mb-2" style="font-size:11px">
                    <i class="fas fa-user-clock me-1 text-primary"></i>
                    Pembaruan Finance terakhir:
                    <b>{{ $lastFinanceUpdate->updater?->name ?? $lastFinanceUpdate->creator?->name ?? 'Penginput data lama tidak tercatat' }}</b>
                    · {{ $lastFinanceUpdate->updated_at?->format('d M Y H:i') }}
                </div>
                @endif
                <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:12px">
                    <thead><tr>
                        <th>Pekerjaan</th><th class="text-end">Biaya (HPP)</th><th class="text-end">Jual</th>
                        <th class="text-end">Laba</th><th>Bayar</th><th>Vendor</th>
                        @if($canEditJobDetails)<th></th>@endif
                    </tr></thead>
                    <tbody>
                        @forelse($jobs as $j)
                        <tr>
                            <td>
                                {{ $j->job_name }} @if($j->job_code)<span class="text-muted">({{ $j->job_code }})</span>@endif
                                <div class="text-muted" style="font-size:10px">
                                    Diinput {{ $j->creator?->name ?? 'penginput data lama tidak tercatat' }} · {{ $j->created_at?->format('d M Y H:i') }}
                                    @if($j->updated_by && $j->updated_by !== $j->created_by)
                                        <br>Diperbarui {{ $j->updater?->name ?? '-' }} · {{ $j->updated_at?->format('d M Y H:i') }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-end">{{ idr($j->riil_biaya) }}</td>
                            <td class="text-end">{{ idr($j->riil_jual) }}</td>
                            <td class="text-end" style="color:{{ $j->laba >= 0 ? '#10b981' : '#dc2626' }}">{{ idr($j->laba) }}</td>
                            <td><span class="badge bg-{{ $j->status_pembayaran === 'Lunas' ? 'success' : 'secondary' }}">{{ $j->status_pembayaran }}</span></td>
                            <td>{{ $j->vendor?->vendor_name ?? '-' }}</td>
                            @if($canEditJobDetails)
                            <td class="text-nowrap">
                                <button class="btn btn-sm btn-outline-secondary" style="padding:2px 6px"
                                    onclick='openEditJob(@json($j))'><i class="fas fa-pencil-alt"></i></button>
                                @if($u->isAdmin())
                                <form method="POST" action="{{ route('job-details.destroy', $j->id) }}" class="d-inline" onsubmit="return confirm('Hapus rincian ini?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" style="padding:2px 6px"><i class="fas fa-trash"></i></button>
                                </form>
                                @endif
                            </td>
                            @endif
                        </tr>
                        @empty
                        <tr><td colspan="{{ $canEditJobDetails ? 7 : 6 }}" class="text-muted">Belum ada rincian pekerjaan.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:700">
                            <td class="text-end">Total</td>
                            <td class="text-end" style="color:#dc2626">{{ idr($sumBiaya) }}</td>
                            <td class="text-end" style="color:var(--primary)">{{ idr($sumJual) }}</td>
                            <td class="text-end" style="color:#10b981">{{ idr($sumJual - $sumBiaya) }}</td>
                            <td colspan="{{ $canEditJobDetails ? 3 : 2 }}"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>

                <div class="alert alert-light border mt-2 mb-0 py-2" style="font-size:11px">
                    Status pembayaran vendor <b>Tempo</b> tidak menghambat penerbitan invoice customer. Invoice dapat dibuat sebelum pembayaran lunas.
                </div>

                {{-- Approval DO --}}
                @if($u->canAccess('approve_do'))
                <div class="mt-3 pt-2 border-top">
                    <div class="d-flex align-items-center flex-wrap gap-2">
                    @if($requestOrder->do_approved)
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> DO Disetujui (siap invoice)</span>
                        <form method="POST" action="{{ route('request-orders.approve-do', $requestOrder->id) }}" class="d-inline">
                            @csrf <input type="hidden" name="action" value="unapprove">
                            <button class="btn btn-sm btn-link text-danger" style="font-size:11px">Batalkan approval</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('request-orders.approve-do', $requestOrder->id) }}">
                            @csrf <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-success" {{ $jobs->isEmpty() && $requestOrder->items->isEmpty() ? 'disabled' : '' }}>
                                <i class="fas fa-check-double me-1"></i> Approve DO (Jual {{ idr($sumJual) }} / HPP {{ idr($sumBiaya) }})
                            </button>
                        </form>
                    @endif
                    @if($requestOrder->request_status === 'approval')
                        <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectDoPriceForm" aria-expanded="false" aria-controls="rejectDoPriceForm">
                            <i class="fas fa-times-circle me-1"></i> Reject DO
                        </button>
                    @endif
                    </div>
                    @if(!$requestOrder->do_approved && $jobs->isEmpty() && $requestOrder->items->isEmpty())
                        <small class="text-muted d-block mt-1">Accounting perlu mengisi layanan & harga dulu</small>
                    @endif

                    @if($requestOrder->request_status === 'approval')
                    <div class="collapse mt-2 {{ old('action') === 'reject' ? 'show' : '' }}" id="rejectDoPriceForm">
                        <form method="POST" action="{{ route('request-orders.approve-do', $requestOrder->id) }}" class="border rounded p-2 bg-light">
                            @csrf
                            <input type="hidden" name="action" value="reject">
                            <label class="form-label mb-1" style="font-size:12px;font-weight:700">Alasan harga tidak benar <span class="text-danger">*</span></label>
                            <textarea name="note" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" required placeholder="Jelaskan harga/HPP yang harus diperbaiki oleh Finance">{{ old('action') === 'reject' ? old('note') : '' }}</textarea>
                            <button class="btn btn-sm btn-danger" onclick="return confirm('Reject DO dan kembalikan ke Finance untuk perbaikan harga?')">
                                <i class="fas fa-undo me-1"></i> Reject & Kembalikan ke Finance
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        {{-- Timeline audit --}}
        <div class="card">
            <div class="card-body p-3">
                <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Riwayat Alur</h6>
                <ul class="list-unstyled mb-0" style="font-size:12px">
                    @forelse($requestOrder->statusLogs as $log)
                    <li class="d-flex gap-2 mb-2 pb-2 border-bottom">
                        <i class="fas fa-circle mt-1" style="font-size:7px;color:#3b82f6"></i>
                        <div>
                            <b>{{ \App\Models\RequestOrder::statusLogLabel($log->to_status) }}</b>
                            @if($log->from_status) <span class="text-muted">(dari {{ \App\Models\RequestOrder::statusLogLabel($log->from_status) }})</span>@endif
                            <br>
                            <span class="text-muted">{{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at->format('d M Y H:i') }}</span>
                            @if($log->note)<br><span style="color:#374151">{{ $log->note }}</span>@endif
                        </div>
                    </li>
                    @empty
                    <li class="text-muted">Belum ada riwayat.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    {{-- ─────────── Kolom kanan: panel aksi sesuai tahap ─────────── --}}
    <div class="col-lg-5">
        @if(session('success'))<div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>@endif
        @foreach($errors->all() as $e)<div class="alert alert-danger py-2" style="font-size:13px">{{ $e }}</div>@endforeach

        @php $u = auth()->user(); @endphp

        <div class="card mb-3 border-{{ $requestOrder->operational_status_color }}">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                    <div>
                        <h6 class="mb-1" style="font-weight:700"><i class="fas fa-route me-1"></i> Status Operasional DO</h6>
                        <span class="badge bg-{{ $requestOrder->operational_status_color }}">{{ $requestOrder->operational_status_label }}</span>
                    </div>
                    @if($requestOrder->operational_status_changed_at)
                    <small class="text-muted text-end">{{ $requestOrder->operational_status_changed_at->format('d M Y H:i') }}<br>{{ $requestOrder->operationalStatusChanger?->name }}</small>
                    @endif
                </div>

                @if($requestOrder->operational_note)
                <div class="alert alert-light border py-2 mb-2" style="font-size:12px">{{ $requestOrder->operational_note }}</div>
                @endif
                @if($requestOrder->rescheduled_for)
                <div class="mb-2" style="font-size:12px"><b>Jadwal baru:</b> {{ $requestOrder->rescheduled_for->format('d M Y') }}</div>
                @endif

                @if($u->canAccess('operational_status'))
                <form method="POST" action="{{ route('request-orders.operational-status', $requestOrder) }}" x-data="{ status: '{{ $requestOrder->operational_status ?? 'running' }}' }">
                    @csrf
                    <label class="form-label">Ganti Status</label>
                    <select name="operational_status" class="form-select form-select-sm mb-2" x-model="status" required>
                        @foreach(\App\Models\RequestOrder::OPERATIONAL_STATUSES as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="mb-2" x-show="status === 'rescheduled'">
                        <label class="form-label">Jadwal Baru <span class="text-danger">*</span></label>
                        <input type="date" name="rescheduled_for" class="form-control form-control-sm" value="{{ $requestOrder->rescheduled_for?->format('Y-m-d') }}" :required="status === 'rescheduled'">
                    </div>
                    <label class="form-label">Keterangan <span class="text-danger" x-show="status !== 'running'">*</span></label>
                    <textarea name="operational_note" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" :required="status !== 'running'" placeholder="Alasan pending, reschedule, atau cancel">{{ $requestOrder->operational_note }}</textarea>
                    <button class="btn btn-primary btn-sm w-100"><i class="fas fa-save me-1"></i> Simpan Status</button>
                </form>
                @endif
            </div>
        </div>

        {{-- TAHAP: VERIFIKASI (Sales Admin) --}}
        @if($requestOrder->request_status === 'verifikasi' && $u->canAccess('verify_request'))
        <div class="card mb-3 border-warning">
            <div class="card-body p-3">
                <h6 style="font-weight:700"><i class="fas fa-clipboard-check me-1 text-warning"></i> Verifikasi Data (Sales Admin)</h6>
                <p class="text-muted" style="font-size:12px">Cek customer, lokasi, jadwal, dan kelengkapan data sebelum diteruskan ke Finance.</p>
                <form method="POST" action="{{ route('request-orders.verify', $requestOrder->id) }}">
                    @csrf
                    <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan verifikasi (opsional)"></textarea>
                    <div class="d-flex gap-2">
                        <button name="action" value="approve" class="btn btn-success btn-sm flex-fill"><i class="fas fa-check me-1"></i> Setujui</button>
                        <button name="action" value="reject" class="btn btn-outline-danger btn-sm flex-fill" onclick="return confirm('Tolak request ini?')"><i class="fas fa-times me-1"></i> Tolak</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- KONTROL REQUEST DP aktif / nonaktif --}}
        @if(in_array($requestOrder->request_status, ['finance', 'approval', 'assigned']) && $u->canAccess('finance_dp_review'))
        <div class="card mb-3 {{ $requestOrder->dp_request_active ? 'border-info' : 'border-dark' }}">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center gap-2">
                    <div>
                        <h6 class="mb-1" style="font-weight:700"><i class="fas fa-toggle-{{ $requestOrder->dp_request_active ? 'on text-info' : 'off text-muted' }} me-1"></i> Request DP</h6>
                        <small class="text-muted">{{ $requestOrder->dp_request_active ? 'Aktif — Finance dapat mengisi status dan nominal DP.' : 'Nonaktif — Request dapat diteruskan tanpa pengisian DP.' }}</small>
                    </div>
                    <form method="POST" action="{{ route('request-orders.dp-active', $requestOrder) }}">@csrf
                        <input type="hidden" name="active" value="{{ $requestOrder->dp_request_active ? 0 : 1 }}">
                        <button class="btn btn-sm {{ $requestOrder->dp_request_active ? 'btn-outline-dark' : 'btn-info' }}" onclick="return confirm('{{ $requestOrder->dp_request_active ? 'Nonaktifkan' : 'Aktifkan' }} Request DP?')">
                            {{ $requestOrder->dp_request_active ? 'Nonaktifkan' : 'Aktifkan' }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
        @endif

        {{-- UPDATE DP setelah tahap Finance; saat approval perubahan diajukan ulang --}}
        @if(in_array($requestOrder->request_status, ['approval', 'assigned']) && $u->canAccess('finance_dp_review') && $requestOrder->dp_request_active)
        <div class="card mb-3 border-info" id="dp-management">
            <div class="card-body p-3" x-data="{ dpStatus: '{{ old('dp_status', $requestOrder->dp_status === 'pending' ? '' : $requestOrder->dp_status) }}' }">
                <h6 style="font-weight:700"><i class="fas fa-coins me-1 text-info"></i> {{ $requestOrder->dp_status === 'pending' ? 'Input DP' : 'Update DP' }}</h6>
                <p class="text-muted" style="font-size:12px">
                    @if($requestOrder->request_status === 'approval')
                        Isi atau perbarui data DP. Perubahan akan dicatat dan diajukan ulang ke Sales Manager.
                    @else
                        Isi atau perbarui data DP. Penyimpanan ini tidak mengubah tahap flow Request DO.
                    @endif
                </p>
                <form method="POST" action="{{ route('request-orders.dp.update', $requestOrder) }}">
                    @csrf
                    <label class="form-label">Status DP <span class="text-danger">*</span></label>
                    <select name="dp_status" class="form-select form-select-sm mb-2" x-model="dpStatus" required>
                        <option value="">- Pilih Status DP -</option>
                        <option value="taken">DP Terambil</option>
                        <option value="not_taken">DP Tidak Terambil</option>
                    </select>
                    <div class="mb-2">
                        <label class="form-label">Nominal DP / Potensi DP <span class="text-danger">*</span></label>
                        <input type="number" name="dp_amount" class="form-control form-control-sm" min="0" step="1" required value="{{ old('dp_amount', (float) $requestOrder->dp_amount) }}">
                        <div class="form-text" x-show="dpStatus === 'not_taken'">Isi nominal potensi DP yang tidak terambil; gunakan 0 jika memang tidak ada.</div>
                    </div>
                    <label class="form-label">Catatan Finance</label>
                    <textarea name="dp_note" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" placeholder="Keterangan DP">{{ old('dp_note', $requestOrder->dp_note) }}</textarea>
                    <button class="btn btn-info btn-sm w-100"><i class="fas fa-save me-1"></i> {{ $requestOrder->request_status === 'approval' ? 'Simpan & Ajukan Ulang' : 'Simpan Data DP' }}</button>
                </form>
            </div>
        </div>
        @endif

        {{-- TAHAP: FINANCE (harga & DP) --}}
        @if($requestOrder->request_status === 'finance' && $u->canAccess('finance_dp_review'))
        <div class="card mb-3 border-info" id="dp-management">
            <div class="card-body p-3" x-data="{ dpStatus: '{{ old('dp_status', $requestOrder->dp_status === 'pending' ? '' : $requestOrder->dp_status) }}' }">
                <h6 style="font-weight:700"><i class="fas fa-coins me-1 text-info"></i> Review Finance & DP</h6>
                <p class="text-muted" style="font-size:12px">Periksa item layanan, HPP, harga jual, lalu tentukan apakah DP terambil.</p>
                <form method="POST" action="{{ route('request-orders.finance-review', $requestOrder) }}">
                    @csrf
                    @if($requestOrder->dp_request_active)
                    <label class="form-label">Status DP <span class="text-danger">*</span></label>
                    <select name="dp_status" class="form-select form-select-sm mb-2" x-model="dpStatus" required>
                        <option value="">- Pilih Status DP -</option>
                        <option value="taken">DP Terambil</option>
                        <option value="not_taken">DP Tidak Terambil</option>
                    </select>
                    <div class="mb-2">
                        <label class="form-label">Nominal DP / Potensi DP <span class="text-danger">*</span></label>
                        <input type="number" name="dp_amount" class="form-control form-control-sm" min="0" step="1" required value="{{ old('dp_amount', (float) $requestOrder->dp_amount) }}">
                        <div class="form-text" x-show="dpStatus === 'not_taken'">Isi nominal potensi DP yang tidak terambil; gunakan 0 jika memang tidak ada.</div>
                    </div>
                    <label class="form-label">Catatan Finance</label>
                    <textarea name="dp_note" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" placeholder="Keterangan DP atau koreksi data">{{ old('dp_note', $requestOrder->dp_note) }}</textarea>
                    @else
                    <div class="alert alert-secondary py-2" style="font-size:12px">Request DP nonaktif. Finance dapat langsung meneruskan request ke Sales Manager.</div>
                    <textarea name="dp_note" class="form-control form-control-sm mb-2" rows="2" maxlength="1000" placeholder="Catatan Finance (opsional)">{{ old('dp_note', $requestOrder->dp_note) }}</textarea>
                    @endif
                    <div class="d-flex gap-2">
                        <button name="action" value="approve" class="btn btn-info btn-sm flex-fill"><i class="fas fa-check me-1"></i> Teruskan ke Sales Manager</button>
                        <button name="action" value="reject" class="btn btn-outline-danger btn-sm flex-fill"><i class="fas fa-undo me-1"></i> Kembali ke Sales Admin</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- TAHAP: DISPATCH (Transport Planner) --}}
        @if($requestOrder->request_status === 'dispatch' && $u->canAccess('dispatch'))
        @php
            $lastAssignment = $requestOrder->assignment->first();
            $requestFleetInfo = collect([$requestOrder->no_pol, $requestOrder->jenis_truck])
                ->filter(fn ($value) => filled($value))
                ->unique()
                ->implode(' / ');
            $dispatchType = old('assignment_type', $lastAssignment?->assignment_type
                ?? ($requestOrder->vendor_id ? 'external' : 'internal'));
            $dispatchVendorId = old('vendor_id', $lastAssignment?->vendor_id ?? $requestOrder->vendor_id);
            $dispatchFleetInfo = old('fleet_info', $lastAssignment?->fleet_info ?: $requestFleetInfo);
            $dispatchDriverName = old('driver_name', $lastAssignment?->driver_name ?: $requestOrder->supir);
            $dispatchDriverPhone = old('driver_phone', $lastAssignment?->driver_phone ?: $requestOrder->hp_supir);
            $dispatchEstimatedCost = old('estimated_cost', $lastAssignment?->estimated_cost ?? $requestOrder->total_cost);
            $dispatchNotes = old('notes', $lastAssignment?->notes ?: $requestOrder->keterangan);
        @endphp
        <div class="card mb-3 border-primary">
            <div class="card-body p-3">
                <h6 style="font-weight:700"><i class="fas fa-truck-moving me-1 text-primary"></i> Penugasan Armada (Transport Planner)</h6>
                <div class="alert alert-light border py-2 mb-2" style="font-size:11px">
                    <i class="fas fa-info-circle me-1 text-primary"></i>Data armada, driver, dan estimasi biaya diambil otomatis dari Request DO dan hasil Finance. Transport Planner tetap dapat mengubahnya.
                </div>
                <form method="POST" action="{{ route('request-orders.dispatch', $requestOrder->id) }}" x-data="{ type: '{{ $dispatchType }}' }">
                    @csrf
                    <label class="form-label" style="font-size:12px">Jenis Armada</label>
                    <select name="assignment_type" class="form-select form-select-sm mb-2" x-model="type">
                        <option value="internal" @selected($dispatchType === 'internal')>Armada Internal</option>
                        <option value="external" @selected($dispatchType === 'external')>Vendor Eksternal</option>
                    </select>

                    <div x-show="type === 'external'" class="mb-2">
                        <label class="form-label" style="font-size:12px">Vendor</label>
                        <select name="vendor_id" class="form-select form-select-sm">
                            <option value="">— Pilih Vendor —</option>
                            @foreach(\App\Models\Vendor::where('status','Active')->orderBy('vendor_name')->get() as $v)
                            <option value="{{ $v->id }}" @selected((string) $dispatchVendorId === (string) $v->id)>{{ $v->vendor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="form-label" style="font-size:12px">Armada / Plat / Nama</label>
                    <input type="text" name="fleet_info" class="form-control form-control-sm mb-2" value="{{ $dispatchFleetInfo }}" placeholder="Mis. B 1234 XY / Truk Engkel">
                    <div class="row g-2 mb-2">
                        <div class="col-7"><input type="text" name="driver_name" class="form-control form-control-sm" value="{{ $dispatchDriverName }}" placeholder="Nama driver"></div>
                        <div class="col-5"><input type="text" name="driver_phone" class="form-control form-control-sm" value="{{ $dispatchDriverPhone }}" placeholder="No. HP"></div>
                    </div>
                    <label class="form-label" style="font-size:12px">Estimasi Biaya</label>
                    <input type="number" name="estimated_cost" class="form-control form-control-sm mb-2" min="0" value="{{ $dispatchEstimatedCost }}">
                    <textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan penugasan (opsional)">{{ $dispatchNotes }}</textarea>
                    <button class="btn btn-primary btn-sm w-100"><i class="fas fa-paper-plane me-1"></i> Ajukan Penugasan</button>
                </form>
            </div>
        </div>
        @endif

        {{-- TAHAP: APPROVAL (Sales Manager/Admin) --}}
        @if($requestOrder->request_status === 'approval' && $u->canAccess('approve_assign'))
        @php $asg = $requestOrder->assignment->firstWhere('approval_status','pending'); @endphp
        <div class="card mb-3" style="border-color:#7c3aed">
            <div class="card-body p-3">
                <h6 style="font-weight:700"><i class="fas fa-user-check me-1" style="color:#7c3aed"></i> Approval Sales Manager</h6>
                @if($asg)
                <div style="font-size:12px;background:#f8faff;padding:8px;border-radius:6px" class="mb-2">
                    <div><b>Jenis:</b> {{ $asg->isInternal() ? 'Armada Internal' : 'Vendor Eksternal' }}</div>
                    @if($asg->isExternal())<div><b>Vendor:</b> {{ $asg->vendor?->vendor_name ?? '-' }}</div>@endif
                    <div><b>Armada:</b> {{ $asg->fleet_info ?? '-' }}</div>
                    <div><b>Driver:</b> {{ $asg->driver_name ?? '-' }} {{ $asg->driver_phone ? '('.$asg->driver_phone.')' : '' }}</div>
                    <div><b>Est. Biaya:</b> {{ idr($asg->estimated_cost) }}</div>
                    @if($asg->notes)<div><b>Catatan:</b> {{ $asg->notes }}</div>@endif
                </div>
                @else
                <div style="font-size:12px;background:#faf5ff;padding:9px;border-radius:6px" class="mb-2">
                    <div><b>Vendor:</b> {{ $requestOrder->vendor?->vendor_name ?? '-' }}</div>
                    <div><b>Revenue:</b> {{ idr($requestOrder->total_revenue) }}</div>
                    <div><b>HPP:</b> {{ idr($requestOrder->total_cost) }}</div>
                    <div><b>Status DP:</b> <span class="badge bg-{{ $requestOrder->dp_status_color }}">{{ $requestOrder->dp_status_label }}</span></div>
                    <div><b>Nominal DP:</b> {{ idr($requestOrder->dp_amount) }}</div>
                    @if($requestOrder->dp_note)<div><b>Catatan Finance:</b> {{ $requestOrder->dp_note }}</div>@endif
                </div>
                @endif
                <form method="POST" action="{{ route('request-orders.approve', $requestOrder->id) }}">
                    @csrf
                    <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan approval (opsional)"></textarea>
                    <div class="d-flex gap-2">
                        <button name="action" value="approve" class="btn btn-success btn-sm flex-fill" onclick="return confirm('Setujui Request DO? DO final akan otomatis terbit.')"><i class="fas fa-check me-1"></i> Setujui & Terbitkan DO</button>
                        <button name="action" value="reject" class="btn btn-outline-danger btn-sm flex-fill"><i class="fas fa-times me-1"></i> Tolak</button>
                    </div>
                </form>
            </div>
        </div>
        @endif

        {{-- TAHAP: ASSIGNED (DO terbit) --}}
        @if($requestOrder->request_status === 'assigned')
        <div class="card mb-3 border-success">
            <div class="card-body p-3">
                <h6 style="font-weight:700 "><i class="fas fa-check-circle me-1 text-success"></i> Penugasan Disetujui</h6>
                @forelse($requestOrder->deliveryOrder as $do)
                <a href="{{ route('delivery-orders.show', $do->id) }}" class="d-block mb-1" style="font-size:13px">
                    <i class="fas fa-truck me-1"></i> {{ $do->do_number }} — Buka Delivery Order
                </a>
                @empty
                <p class="text-muted" style="font-size:12px">DO final sedang diproses.</p>
                @endforelse
            </div>
        </div>
        @endif

        {{-- Status info untuk role yang tidak punya aksi di tahap ini --}}
        @if(in_array($requestOrder->request_status, ['rejected','cancelled']))
        <div class="alert alert-danger" style="font-size:13px">Request DO ini berstatus <b>{{ $requestOrder->flow_label }}</b>.</div>
        @endif
    </div>
</div>

{{-- Modal Item Layanan & Harga (Accounting) --}}
@if($canEditRequestPricing)
<div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <form method="POST" id="itemForm" action="{{ route('request-order-items.store', $requestOrder) }}">
            @csrf
            <input type="hidden" name="_method" id="itemMethod" value="POST">
            <div class="modal-header"><h6 class="modal-title" id="itemModalTitle">Tambah Item Layanan & Harga</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" style="font-size:13px">
                <div class="mb-2"><label class="form-label">Nama Layanan <span class="text-danger">*</span></label><input type="text" name="service_name" id="itemService" class="form-control form-control-sm" required></div>
                <div class="row g-2 mb-2">
                    <div class="col-4"><label class="form-label">Satuan</label><input type="text" name="unit" id="itemUnit" class="form-control form-control-sm" placeholder="trip/unit"></div>
                    <div class="col-4"><label class="form-label">Tonase</label><input type="number" step="0.001" min="0" name="tonnage" id="itemTonnage" class="form-control form-control-sm"></div>
                    <div class="col-4"><label class="form-label">Qty <span class="text-danger">*</span></label><input type="number" step="0.001" min="0.001" name="qty" id="itemQty" class="form-control form-control-sm" value="1" required></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label">Harga Beli / HPP <span class="text-danger">*</span></label><input type="number" min="0" name="buy_price" id="itemBuy" class="form-control form-control-sm" value="0" required></div>
                    <div class="col-6"><label class="form-label">Harga Jual <span class="text-danger">*</span></label><input type="number" min="0" name="sell_price" id="itemSell" class="form-control form-control-sm" value="0" required></div>
                </div>
                <div><label class="form-label">Keterangan</label><textarea name="description" id="itemDescription" class="form-control form-control-sm" rows="2"></textarea></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-primary">{{ $requestOrder->request_status === 'approval' ? 'Simpan & Ajukan Ulang' : 'Simpan' }}</button></div>
        </form>
    </div></div>
</div>
<script>
const itemStoreUrl = @json(route('request-order-items.store', $requestOrder));
function openAddItem() {
    const form = document.getElementById('itemForm');
    form.reset(); form.action = itemStoreUrl;
    document.getElementById('itemMethod').value = 'POST';
    document.getElementById('itemModalTitle').textContent = 'Tambah Item Layanan & Harga';
    document.getElementById('itemQty').value = 1;
    document.getElementById('itemBuy').value = 0;
    document.getElementById('itemSell').value = 0;
}
function openEditItem(item) {
    const form = document.getElementById('itemForm');
    form.action = `/request-order-items/${item.id}`;
    document.getElementById('itemMethod').value = 'PUT';
    document.getElementById('itemModalTitle').textContent = 'Edit Item Layanan & Harga';
    document.getElementById('itemService').value = item.service_name || '';
    document.getElementById('itemUnit').value = item.unit || '';
    document.getElementById('itemTonnage').value = item.tonnage || '';
    document.getElementById('itemQty').value = item.qty || 1;
    document.getElementById('itemBuy').value = item.buy_price || 0;
    document.getElementById('itemSell').value = item.sell_price || 0;
    document.getElementById('itemDescription').value = item.description || '';
}
</script>
@endif

{{-- Modal Tambah/Edit Rincian Pekerjaan --}}
@if($canEditJobDetails)
@php
    $pekerjaanList = \App\Models\Pekerjaan::where('is_active',true)->orderBy('name')->get();
    $vendorList = \App\Models\Vendor::where('status','Active')->orderBy('vendor_name')->get();
@endphp
<div class="modal fade" id="addJobModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" id="jobForm" action="{{ route('job-details.store', $requestOrder->id) }}">
        @csrf
        <input type="hidden" name="_method" id="jobMethod" value="POST">
        <div class="modal-header"><h6 class="modal-title" id="jobModalTitle">Tambah Rincian Pekerjaan</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:13px">
          <div class="mb-2">
            <label class="form-label">Pekerjaan <span class="text-danger">*</span></label>
            <select name="pekerjaan_id" id="jobPekerjaan" class="form-select form-select-sm">
              <option value="">— Pilih / isi manual —</option>
              @foreach($pekerjaanList as $p)
              <option value="{{ $p->id }}" data-code="{{ $p->code }}">{{ $p->label }}</option>
              @endforeach
            </select>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-8"><label class="form-label">Nama (manual)</label><input type="text" name="job_name" id="jobName" class="form-control form-control-sm" placeholder="Mis. Trucking"></div>
            <div class="col-4"><label class="form-label">Kode</label><input type="text" name="job_code" id="jobCode" class="form-control form-control-sm" placeholder="TR"></div>
          </div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Anggaran Biaya</label><input type="number" name="anggaran_biaya" id="jobAB" class="form-control form-control-sm" min="0" value="0"></div>
            <div class="col-6"><label class="form-label">Anggaran Jual</label><input type="number" name="anggaran_jual" id="jobAJ" class="form-control form-control-sm" min="0" value="0"></div>
            <div class="col-6"><label class="form-label">Riil Biaya (HPP) <span class="text-danger">*</span></label><input type="number" name="riil_biaya" id="jobRB" class="form-control form-control-sm" min="0" value="0" required></div>
            <div class="col-6"><label class="form-label">Riil Jual <span class="text-danger">*</span></label><input type="number" name="riil_jual" id="jobRJ" class="form-control form-control-sm" min="0" value="0" required></div>
            <div class="col-6"><label class="form-label">Dibayar</label><input type="number" name="dibayar" id="jobDibayar" class="form-control form-control-sm" min="0" value="0"></div>
            <div class="col-6"><label class="form-label">Status Bayar</label>
              <select name="status_pembayaran" id="jobStatus" class="form-select form-select-sm"><option>Tempo</option><option>Lunas</option></select></div>
          </div>
          <div class="mb-2"><label class="form-label">Vendor</label>
            <select name="vendor_id" id="jobVendor" class="form-select form-select-sm">
              <option value="">— Tidak ada —</option>
              @foreach($vendorList as $v)<option value="{{ $v->id }}">{{ $v->vendor_name }}</option>@endforeach
            </select></div>
          <div class="row g-2 mb-2">
            <div class="col-6"><label class="form-label">Tgl Transaksi</label><input type="date" name="tgl_transaksi" id="jobTgl" class="form-control form-control-sm"></div>
            <div class="col-6"><label class="form-label">Tgl Realisasi</label><input type="date" name="tgl_realisasi" id="jobTglR" class="form-control form-control-sm"></div>
          </div>
          <div class="mb-2"><label class="form-label">Catatan</label><textarea name="catatan" id="jobCatatan" class="form-control form-control-sm" rows="2"></textarea></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
          <button class="btn btn-sm btn-primary">{{ $requestOrder->request_status === 'approval' ? 'Simpan & Ajukan Ulang' : 'Simpan' }}</button></div>
      </form>
    </div>
  </div>
</div>

<script>
const jobStoreUrl = "{{ route('job-details.store', $requestOrder->id) }}";
document.getElementById('jobPekerjaan')?.addEventListener('change', function(){
    const opt = this.options[this.selectedIndex];
    if (opt.value) {
        document.getElementById('jobName').value = opt.text.split(' - ')[0];
        document.getElementById('jobCode').value = opt.dataset.code || '';
    }
});
function openEditJob(j){
    const f = document.getElementById('jobForm');
    f.action = `/job-details/${j.id}`;
    document.getElementById('jobMethod').value = 'PUT';
    document.getElementById('jobModalTitle').textContent = 'Edit Rincian Pekerjaan';
    document.getElementById('jobPekerjaan').value = j.pekerjaan_id || '';
    document.getElementById('jobName').value = j.job_name || '';
    document.getElementById('jobCode').value = j.job_code || '';
    document.getElementById('jobAB').value = j.anggaran_biaya || 0;
    document.getElementById('jobAJ').value = j.anggaran_jual || 0;
    document.getElementById('jobRB').value = j.riil_biaya || 0;
    document.getElementById('jobRJ').value = j.riil_jual || 0;
    document.getElementById('jobDibayar').value = j.dibayar || 0;
    document.getElementById('jobStatus').value = j.status_pembayaran || 'Tempo';
    document.getElementById('jobVendor').value = j.vendor_id || '';
    document.getElementById('jobTgl').value = j.tgl_transaksi ? j.tgl_transaksi.substring(0,10) : '';
    document.getElementById('jobTglR').value = j.tgl_realisasi ? j.tgl_realisasi.substring(0,10) : '';
    document.getElementById('jobCatatan').value = j.catatan || '';
    new bootstrap.Modal(document.getElementById('addJobModal')).show();
}
document.getElementById('addJobModal')?.addEventListener('hidden.bs.modal', function(){
    const f = document.getElementById('jobForm');
    f.reset(); f.action = jobStoreUrl;
    document.getElementById('jobMethod').value = 'POST';
    document.getElementById('jobModalTitle').textContent = 'Tambah Rincian Pekerjaan';
});
</script>
@endif
@endsection
