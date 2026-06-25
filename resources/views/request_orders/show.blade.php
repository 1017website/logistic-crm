@extends('layouts.app')
@section('title', 'Detail Request DO')
@section('page-title', $requestOrder->do_number)
@section('page-subtitle', 'Detail Request DO & alur verifikasi/penugasan')

@section('content')
<div class="row g-3">
    {{-- ─────────── Kolom kiri: info & item ─────────── --}}
    <div class="col-lg-7">
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1" style="font-weight:800">{{ $requestOrder->do_number }}</h5>
                        <span class="badge bg-{{ $requestOrder->flow_color }}">{{ $requestOrder->flow_label }}</span>
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
                </div>
            </div>
        </div>

        {{-- Item layanan --}}
        <div class="card mb-3">
            <div class="card-body p-3">
                <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Item Layanan</h6>
                <table class="table table-sm mb-0" style="font-size:12px">
                    <thead><tr>
                        <th>Layanan</th><th class="text-end">Qty</th><th class="text-end">Beli</th>
                        <th class="text-end">Jual</th><th class="text-end">Subtotal</th>
                    </tr></thead>
                    <tbody>
                        @foreach($requestOrder->items as $it)
                        <tr>
                            <td>{{ $it->service_name }} <span class="text-muted">{{ $it->unit }}</span></td>
                            <td class="text-end">{{ rtrim(rtrim(number_format($it->qty,3),'0'),'.') }}</td>
                            <td class="text-end">{{ idr($it->buy_price) }}</td>
                            <td class="text-end">{{ idr($it->sell_price) }}</td>
                            <td class="text-end">{{ idr($it->subtotal_revenue) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr style="font-weight:700">
                        <td colspan="4" class="text-end">Total Revenue</td>
                        <td class="text-end" style="color:var(--primary)">{{ idr($requestOrder->total_revenue) }}</td>
                    </tr></tfoot>
                </table>
            </div>
        </div>

        {{-- Rincian biaya per pekerjaan --}}
        @php
            $jobs = $requestOrder->jobDetails()->with('vendor','pekerjaan')->get();
            $sumBiaya = $jobs->sum(fn($j)=>(float)$j->riil_biaya);
            $sumJual  = $jobs->sum(fn($j)=>(float)$j->riil_jual);
            $u = auth()->user();
        @endphp
        <div class="card mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="mb-0" style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Rincian Biaya per Pekerjaan</h6>
                    @if($u->canAccess('job_details'))
                    <button class="btn btn-sm btn-primary" style="padding:3px 10px" data-bs-toggle="modal" data-bs-target="#addJobModal"><i class="fas fa-plus me-1"></i> Tambah</button>
                    @endif
                </div>
                <div class="table-responsive">
                <table class="table table-sm mb-0" style="font-size:12px">
                    <thead><tr>
                        <th>Pekerjaan</th><th class="text-end">Biaya (HPP)</th><th class="text-end">Jual</th>
                        <th class="text-end">Laba</th><th>Bayar</th><th>Vendor</th>
                        @if($u->canAccess('job_details'))<th></th>@endif
                    </tr></thead>
                    <tbody>
                        @forelse($jobs as $j)
                        <tr>
                            <td>{{ $j->job_name }} @if($j->job_code)<span class="text-muted">({{ $j->job_code }})</span>@endif</td>
                            <td class="text-end">{{ idr($j->riil_biaya) }}</td>
                            <td class="text-end">{{ idr($j->riil_jual) }}</td>
                            <td class="text-end" style="color:{{ $j->laba >= 0 ? '#10b981' : '#dc2626' }}">{{ idr($j->laba) }}</td>
                            <td><span class="badge bg-{{ $j->status_pembayaran === 'Lunas' ? 'success' : 'secondary' }}">{{ $j->status_pembayaran }}</span></td>
                            <td>{{ $j->vendor?->vendor_name ?? '-' }}</td>
                            @if($u->canAccess('job_details'))
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
                        <tr><td colspan="{{ $u->canAccess('job_details') ? 7 : 6 }}" class="text-muted">Belum ada rincian pekerjaan.</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot>
                        <tr style="font-weight:700">
                            <td class="text-end">Total</td>
                            <td class="text-end" style="color:#dc2626">{{ idr($sumBiaya) }}</td>
                            <td class="text-end" style="color:var(--primary)">{{ idr($sumJual) }}</td>
                            <td class="text-end" style="color:#10b981">{{ idr($sumJual - $sumBiaya) }}</td>
                            <td colspan="{{ $u->canAccess('job_details') ? 3 : 2 }}"></td>
                        </tr>
                    </tfoot>
                </table>
                </div>

                {{-- Approval DO --}}
                @if($u->canAccess('approve_do'))
                <div class="mt-3 pt-2 border-top">
                    @if($requestOrder->do_approved)
                        <span class="badge bg-success"><i class="fas fa-check me-1"></i> DO Disetujui (siap invoice)</span>
                        <form method="POST" action="{{ route('request-orders.approve-do', $requestOrder->id) }}" class="d-inline">
                            @csrf <input type="hidden" name="action" value="unapprove">
                            <button class="btn btn-sm btn-link text-danger" style="font-size:11px">Batalkan approval</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('request-orders.approve-do', $requestOrder->id) }}">
                            @csrf <input type="hidden" name="action" value="approve">
                            <button class="btn btn-sm btn-success" {{ $jobs->isEmpty() ? 'disabled' : '' }}>
                                <i class="fas fa-check-double me-1"></i> Approve DO (Jual {{ idr($sumJual) }} / HPP {{ idr($sumBiaya) }})
                            </button>
                            @if($jobs->isEmpty())<small class="text-muted ms-2">Isi rincian dulu</small>@endif
                        </form>
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
                            <b>{{ \App\Models\RequestOrder::FLOW[$log->to_status] ?? $log->to_status }}</b>
                            @if($log->from_status) <span class="text-muted">(dari {{ \App\Models\RequestOrder::FLOW[$log->from_status] ?? $log->from_status }})</span>@endif
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

        {{-- TAHAP: VERIFIKASI (Sales Admin) --}}
        @if($requestOrder->request_status === 'verifikasi' && $u->canAccess('verify_request'))
        <div class="card mb-3 border-warning">
            <div class="card-body p-3">
                <h6 style="font-weight:700"><i class="fas fa-clipboard-check me-1 text-warning"></i> Verifikasi Data (Sales Admin)</h6>
                <p class="text-muted" style="font-size:12px">Cek harga, customer, lokasi, jadwal, dan kelengkapan data sebelum diteruskan ke Transport Planner.</p>
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

        {{-- TAHAP: DISPATCH (Transport Planner) --}}
        @if($requestOrder->request_status === 'dispatch' && $u->canAccess('dispatch'))
        <div class="card mb-3 border-primary">
            <div class="card-body p-3">
                <h6 style="font-weight:700"><i class="fas fa-truck-moving me-1 text-primary"></i> Penugasan Armada (Transport Planner)</h6>
                <form method="POST" action="{{ route('request-orders.dispatch', $requestOrder->id) }}" x-data="{ type: 'internal' }">
                    @csrf
                    <label class="form-label" style="font-size:12px">Jenis Armada</label>
                    <select name="assignment_type" class="form-select form-select-sm mb-2" x-model="type">
                        <option value="internal">Armada Internal</option>
                        <option value="external">Vendor Eksternal</option>
                    </select>

                    <div x-show="type === 'external'" class="mb-2">
                        <label class="form-label" style="font-size:12px">Vendor</label>
                        <select name="vendor_id" class="form-select form-select-sm">
                            <option value="">— Pilih Vendor —</option>
                            @foreach(\App\Models\Vendor::where('status','Active')->orderBy('vendor_name')->get() as $v)
                            <option value="{{ $v->id }}">{{ $v->vendor_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <label class="form-label" style="font-size:12px">Armada / Plat / Nama</label>
                    <input type="text" name="fleet_info" class="form-control form-control-sm mb-2" placeholder="Mis. B 1234 XY / Truk Engkel">
                    <div class="row g-2 mb-2">
                        <div class="col-7"><input type="text" name="driver_name" class="form-control form-control-sm" placeholder="Nama driver"></div>
                        <div class="col-5"><input type="text" name="driver_phone" class="form-control form-control-sm" placeholder="No. HP"></div>
                    </div>
                    <label class="form-label" style="font-size:12px">Estimasi Biaya</label>
                    <input type="number" name="estimated_cost" class="form-control form-control-sm mb-2" min="0" value="0">
                    <textarea name="notes" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan penugasan (opsional)"></textarea>
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
                <h6 style="font-weight:700"><i class="fas fa-user-check me-1" style="color:#7c3aed"></i> Approval Penugasan</h6>
                @if($asg)
                <div style="font-size:12px;background:#f8faff;padding:8px;border-radius:6px" class="mb-2">
                    <div><b>Jenis:</b> {{ $asg->isInternal() ? 'Armada Internal' : 'Vendor Eksternal' }}</div>
                    @if($asg->isExternal())<div><b>Vendor:</b> {{ $asg->vendor?->vendor_name ?? '-' }}</div>@endif
                    <div><b>Armada:</b> {{ $asg->fleet_info ?? '-' }}</div>
                    <div><b>Driver:</b> {{ $asg->driver_name ?? '-' }} {{ $asg->driver_phone ? '('.$asg->driver_phone.')' : '' }}</div>
                    <div><b>Est. Biaya:</b> {{ idr($asg->estimated_cost) }}</div>
                    @if($asg->notes)<div><b>Catatan:</b> {{ $asg->notes }}</div>@endif
                </div>
                <form method="POST" action="{{ route('request-orders.approve', $requestOrder->id) }}">
                    @csrf
                    <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan approval (opsional)"></textarea>
                    <div class="d-flex gap-2">
                        <button name="action" value="approve" class="btn btn-success btn-sm flex-fill" onclick="return confirm('Setujui penugasan? DO final akan otomatis terbit.')"><i class="fas fa-check me-1"></i> Setujui & Terbitkan DO</button>
                        <button name="action" value="reject" class="btn btn-outline-danger btn-sm flex-fill"><i class="fas fa-times me-1"></i> Tolak</button>
                    </div>
                </form>
                @else
                <p class="text-muted" style="font-size:12px">Tidak ada penugasan pending.</p>
                @endif
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
                    <i class="fas fa-file-invoice me-1"></i> {{ $do->do_number }} — {{ $do->flow_label }}
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

{{-- Modal Tambah/Edit Rincian Pekerjaan --}}
@php $u = auth()->user(); @endphp
@if($u->canAccess('job_details'))
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
            <label class="form-label">Pekerjaan</label>
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
            <div class="col-6"><label class="form-label">Riil Biaya (HPP)</label><input type="number" name="riil_biaya" id="jobRB" class="form-control form-control-sm" min="0" value="0"></div>
            <div class="col-6"><label class="form-label">Riil Jual</label><input type="number" name="riil_jual" id="jobRJ" class="form-control form-control-sm" min="0" value="0"></div>
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
          <button class="btn btn-sm btn-primary">Simpan</button></div>
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
