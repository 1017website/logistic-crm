@extends('layouts.app')
@section('title', 'Invoice')
@section('page-title', 'Invoice')
@section('page-subtitle', 'Status penagihan terpusat · satu invoice dapat memuat banyak DO · TR dan Non-TR dipisahkan')

@section('content')
@php $u = auth()->user(); @endphp
<div class="row g-3"><div class="col-12">

    @if(session('success'))<div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>@endif
    @foreach($errors->all() as $e)<div class="alert alert-danger py-2" style="font-size:13px">{{ $e }}</div>@endforeach

    {{-- Tabs + Tambah --}}
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <ul class="nav nav-pills" style="gap:6px">
            @foreach(['draft'=>'Draft','invoice'=>'Invoice','paid'=>'Paid'] as $key=>$label)
            <li class="nav-item">
                <a class="nav-link {{ $tab === $key ? 'active' : '' }}" style="padding:5px 16px;font-size:13px"
                   href="{{ route('invoices.index', array_merge(request()->query(), ['tab'=>$key])) }}">{{ $label }}</a>
            </li>
            @endforeach
        </ul>
        <div class="d-flex gap-2">
            <a href="{{ route('invoices.export', array_merge(request()->query(), ['status'=>$status])) }}" class="btn btn-outline-success btn-sm"><i class="fas fa-file-excel me-1"></i> Export Excel</a>
            <a href="{{ route('invoices.export-pdf', array_merge(request()->query(), ['status'=>$status])) }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-1"></i> Export PDF</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"><i class="fas fa-plus me-1"></i> Tambah</button>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('invoices.index') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="card mb-3"><div class="card-body p-3"><div class="row g-2 align-items-end">
            <div class="col-md-3">
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">Semua Customer</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->company_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <select name="jenis" class="form-select form-select-sm">
                    <option value="all" @selected($jenis === 'all')>Semua Tipe (TR & NTR)</option>
                    @foreach(\App\Models\Invoice::TYPES as $key => $label)<option value="{{ $key }}" @selected($jenis === $key)>{{ $label }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-4"><input type="text" name="search" class="form-control form-control-sm" placeholder="Cari no invoice / customer..." value="{{ $search }}"></div>
            <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i></button></div>
        </div></div></div>
    </form>

    {{-- Tabel --}}
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:13px">
            <thead style="background:#f8f9fa"><tr>
                <th class="px-3 py-2">ID / No Invoice</th><th class="py-2">Buat / Tempo</th><th class="py-2">Customer</th><th class="py-2">Tipe / Pajak</th>
                <th class="py-2 text-end">HPP</th><th class="py-2 text-end">Jual</th><th class="py-2 text-end">Laba</th>
                <th class="py-2">Status</th>@if($tab==='invoice')<th class="py-2">Umur</th>@endif<th class="py-2"></th>
            </tr></thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td class="px-3 py-2"><span style="font-weight:700;color:var(--primary)">{{ $inv->invoice_id }}</span><br>
                        <span style="font-size:11px;color:#6b7280">{{ $inv->invoice_number }}</span></td>
                    <td class="py-2" style="font-size:12px">{{ $inv->tgl_buat?->format('d M Y') }}<br>
                        <span class="text-muted">Tempo: {{ $inv->tgl_tempo?->format('d M Y') ?? '-' }}</span></td>
                    <td class="py-2">{{ $inv->customer?->company_name ?? '-' }}</td>
                    <td class="py-2"><span class="badge bg-dark">{{ $inv->jenis_label }}</span><br>
                        <small class="text-muted">{{ $inv->do_count }} DO · {{ $inv->items->count() }} komponen</small><br>
                        <small class="text-muted">{{ (float)$inv->ppn_persen > 0 ? 'PPN '.rtrim(rtrim(number_format($inv->ppn_persen,2),'0'),'.').'%' : 'Non-PPN' }}</small></td>
                    <td class="py-2 text-end">{{ idr($inv->total_hpp) }}</td>
                    <td class="py-2 text-end">{{ idr($inv->total_jual) }}</td>
                    <td class="py-2 text-end" style="color:#10b981">{{ idr($inv->laba) }}</td>
                    <td class="py-2"><span class="badge bg-{{ $inv->status_color }}">{{ $inv->status_label }}</span></td>
                    @if($tab==='invoice')
                    <td class="py-2" style="font-size:12px">
                        @php $um = $inv->umur_hari; @endphp
                        @if($um !== null)<span style="color:{{ $um < 0 ? '#dc2626' : '#6b7280' }}">{{ $um }} hr</span>@else - @endif
                    </td>
                    @endif
                    <td class="py-2 text-nowrap">
                        <a href="{{ route('invoices.show', $inv->id) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px" title="Detail"><i class="fas fa-list"></i></a>
                        <a href="{{ route('invoices.print', $inv->id) }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="padding:3px 7px" title="Cetak"><i class="fas fa-print"></i></a>
                        <a href="{{ route('invoices.pdf', $inv->id) }}" class="btn btn-sm btn-outline-danger" style="padding:3px 7px" title="Unduh PDF"><i class="fas fa-file-pdf"></i></a>
                        <a href="{{ route('invoices.excel', $inv->id) }}" class="btn btn-sm btn-outline-success" style="padding:3px 7px" title="Unduh Excel rincian"><i class="fas fa-file-excel"></i></a>
                        @if($inv->status==='draft')
                        <form method="POST" action="{{ route('invoices.submit',$inv->id) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success" style="padding:3px 7px" title="Terbitkan"><i class="fas fa-paper-plane"></i></button></form>
                        @elseif($inv->status==='invoice')
                        <button class="btn btn-sm btn-outline-success" style="padding:3px 7px" title="Pencairan" data-bs-toggle="modal" data-bs-target="#payModal{{ $inv->id }}"><i class="fas fa-money-bill-wave"></i></button>
                        @endif
                        @if($u->canAccess('finance'))
                        @include('components.delete-request-button', ['module'=>'invoices','id'=>$inv->id,'label'=>$inv->invoice_number ?? $inv->invoice_id,'pending'=>in_array($inv->id,$pendingDeletionIds ?? [])])
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $tab === 'invoice' ? 10 : 9 }}" class="text-center py-4 text-muted">Tidak ada invoice pada tab ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
    <div class="mt-3">{{ $invoices->links() }}</div>
</div></div>

{{-- Modal pencairan per invoice --}}
@foreach($invoices as $inv)
@if($inv->status==='invoice')
<div class="modal fade" id="payModal{{ $inv->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('invoices.pay',$inv->id) }}">@csrf
        <div class="modal-header"><h6 class="modal-title">Pencairan {{ $inv->invoice_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:13px">
            <p>Nilai: <b>{{ idr($inv->grand_total ?: $inv->total_jual) }}</b></p>
            <label class="form-label">Tanggal Pencairan</label>
            <input type="date" name="tgl_pencairan" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-success">Tandai Lunas</button></div>
    </form>
</div></div></div>
@endif
@endforeach

{{-- Modal Buat Invoice (multi-DO) --}}
<div class="modal fade" id="addInvoiceModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content">
    <form method="POST" action="{{ route('invoices.store') }}" id="invoiceForm">@csrf
        <div class="modal-header"><h6 class="modal-title">Buat Invoice</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:13px">
            <div class="row g-2 mb-2">
                <div class="col-md-5"><label class="form-label">Customer</label>
                    <select name="customer_id" id="invCustomer" class="form-select form-select-sm no-select2" required>
                        <option value="">— Pilih Customer —</option>
                        @foreach($customers as $c)<option value="{{ $c->id }}">{{ $c->company_name }} @if($c->invoice_code)({{ $c->invoice_code }})@elseif($c->customer_code)({{ $c->customer_code }})@endif</option>@endforeach
                    </select>
                </div>
                <div class="col-md-3"><label class="form-label">Tgl Buat</label><input type="date" name="tgl_buat" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                <div class="col-md-4"><label class="form-label">Tgl Tempo</label><input type="date" name="tgl_tempo" class="form-control form-control-sm" value="{{ now()->addDays(30)->toDateString() }}"></div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">TR & Non-TR</label>
                    <input type="hidden" name="billing_mode" value="separate">
                    <div class="form-control form-control-sm bg-light">Otomatis dipisah: Invoice TR dan Invoice Non-TR</div>
                    <small class="text-muted">Masing-masing invoice tetap dapat berisi banyak DO customer yang sama.</small>
                </div>
                <div class="col-md-3"><label class="form-label">Pajak</label><select name="ppn_mode" id="ppnMode" class="form-select form-select-sm" required><option value="non_ppn">Non-PPN</option><option value="ppn">PPN</option></select></div>
                <div class="col-md-2" id="ppnPercentWrap" style="display:none"><label class="form-label">PPN</label><div class="input-group input-group-sm"><input type="number" step="0.01" min="0.01" max="100" name="ppn_persen" id="ppnPercent" class="form-control" value="11"><span class="input-group-text">%</span></div></div>
                <div class="col-md-3"><label class="form-label">Catatan</label><input type="text" name="notes" class="form-control form-control-sm" placeholder="Opsional"></div>
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                    <label class="form-label mb-0">Pilih beberapa DO siap tagih <small class="text-muted">(customer yang sama, setelah file POD diunggah)</small></label>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="selectAllDosBtn" disabled>Pilih Semua DO</button>
                </div>
                <div id="doListWrap" class="border rounded p-2" style="max-height:320px;overflow:auto">
                    <div class="text-muted">Pilih customer dulu.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-3" style="font-size:13px">
                <div><b id="selectedDoCount">0 DO dipilih · 0 komponen</b></div>
                <div>Total HPP: <b id="sumHpp">Rp 0</b></div>
                <div>Total Jual: <b id="sumJual" style="color:var(--primary)">Rp 0</b></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button class="btn btn-sm btn-primary" id="invSubmitBtn" disabled>Simpan Draft</button></div>
    </form>
</div></div></div>

<script>
const availUrl = "{{ route('invoices.available-dos') }}";
const fmtRp = n => 'Rp ' + Number(n||0).toLocaleString('id-ID');
const esc = value => String(value ?? '').replace(/[&<>"']/g, char => ({
    '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#039;'
}[char]));

async function loadAvailableDos(customerId){
    const wrap = document.getElementById('doListWrap');
    if (!wrap) return;
    wrap.innerHTML = '<div class="text-muted">Memuat...</div>';
    if (!customerId) { wrap.innerHTML = '<div class="text-muted">Pilih customer dulu.</div>'; recalcInv(); return; }
    try {
        const res = await fetch(`${availUrl}?customer_id=${customerId}`, {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const dos = await res.json();
        if (!dos.length) {
            wrap.innerHTML = '<div class="text-muted small">Tidak ada komponen DO siap tagih.<br>DO baru muncul setelah harga disetujui dan <b>file POD sudah diunggah</b>. Status Sampai Tujuan atau Menunggu Upload POD belum dapat ditagih. Komponen yang sudah masuk invoice tidak dapat dipilih ulang.</div>';
            const selectAll = document.getElementById('selectAllDosBtn'); if (selectAll) selectAll.disabled = true;
            recalcInv(); return;
        }
        wrap.innerHTML = dos.map(d => {
            const components = d.types.map(t => `
                <label class="d-flex align-items-center gap-2 py-1 ps-3 border-top ${t.available ? '' : 'text-muted'}" style="${t.available ? 'cursor:pointer' : 'opacity:.65'}">
                    <input type="checkbox" name="selections[]" value="${d.id}:${t.type}" class="doChk"
                           data-hpp="${t.hpp}" data-jual="${t.jual}" ${t.available ? '' : 'disabled'}>
                    <span class="badge ${t.type === 'TR' ? 'bg-primary' : 'bg-secondary'}">${t.type}</span>
                    <span class="flex-fill">${esc(t.description)}</span>
                    <span class="text-muted">HPP ${fmtRp(t.hpp)}</span>
                    <span style="color:var(--primary)">Jual ${fmtRp(t.jual)}</span>
                    ${t.available ? '' : '<small>Sudah diinvoice</small>'}
                </label>`).join('');

            return `<div class="mb-2">
                <div class="d-flex justify-content-between gap-2 pb-1">
                    <span><b>${esc(d.do_number)}</b> <span class="text-muted">${esc(d.do_date)} · ${esc(d.origin)} → ${esc(d.destination)}</span></span>
                    <small class="text-muted">POD ${esc(d.pod_at)}</small>
                </div>
                ${components}
            </div>`;
        }).join('');
        wrap.querySelectorAll('.doChk').forEach(c => c.addEventListener('change', recalcInv));
        const selectAll = document.getElementById('selectAllDosBtn'); if (selectAll) selectAll.disabled = false;
        recalcInv();
    } catch(e) {
        wrap.innerHTML = '<div class="text-danger small">Gagal memuat DO: ' + (e.message||e) + '</div>';
    }
}

// #invCustomer kini native (no-select2) → event change pasti jalan.
const _invCust = document.getElementById('invCustomer');
if (_invCust) {
    _invCust.addEventListener('change', function(){ loadAvailableDos(this.value); });
}
const _ppnMode = document.getElementById('ppnMode');
function togglePpnInput(){
    const taxable = _ppnMode?.value === 'ppn';
    const wrap = document.getElementById('ppnPercentWrap');
    const input = document.getElementById('ppnPercent');
    if (wrap) wrap.style.display = taxable ? '' : 'none';
    if (input) input.required = taxable;
}
_ppnMode?.addEventListener('change', togglePpnInput);
togglePpnInput();
// Saat modal dibuka, muat ulang sesuai customer yang sedang terpilih.
if (window.jQuery) {
    jQuery(document).on('shown.bs.modal', '#addInvoiceModal', function(){
        loadAvailableDos(document.getElementById('invCustomer')?.value);
    });
}

function recalcInv(){
    let hpp=0, jual=0, n=0;
    const selectedDos = new Set();
    document.querySelectorAll('.doChk:checked').forEach(c=>{
        hpp+=+c.dataset.hpp; jual+=+c.dataset.jual; n++;
        selectedDos.add(String(c.value).split(':')[0]);
    });
    const count = document.getElementById('selectedDoCount');
    if (count) count.textContent = `${selectedDos.size} DO dipilih · ${n} komponen`;
    const eh = document.getElementById('sumHpp'); if (eh) eh.textContent = fmtRp(hpp);
    const ej = document.getElementById('sumJual'); if (ej) ej.textContent = fmtRp(jual);
    const btn = document.getElementById('invSubmitBtn'); if (btn) btn.disabled = n===0;
    const allChecks = [...document.querySelectorAll('.doChk:not(:disabled)')];
    const selectAll = document.getElementById('selectAllDosBtn');
    if (selectAll) selectAll.textContent = allChecks.length && allChecks.every(c => c.checked) ? 'Batalkan Semua' : 'Pilih Semua DO';
}

document.getElementById('selectAllDosBtn')?.addEventListener('click', function(){
    const checks = [...document.querySelectorAll('.doChk:not(:disabled)')];
    const shouldCheck = checks.some(c => !c.checked);
    checks.forEach(c => { c.checked = shouldCheck; });
    recalcInv();
});
</script>
@endsection
