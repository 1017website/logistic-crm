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
            @foreach(['draft'=>'Draft','invoice'=>'Invoice','paid'=>'Paid / Termin'] as $key=>$label)
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

    {{-- Bundle invoice per customer --}}
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:13px">
            <thead style="background:#f8f9fa"><tr>
                <th class="px-3 py-2">Customer</th>
                <th class="py-2 text-center">Jumlah Invoice</th>
                <th class="py-2 text-end">Total Tagihan</th>
                <th class="py-2 text-end">Sudah Terbayar</th>
                <th class="py-2 text-end">Belum Terbayar</th>
                <th class="py-2 text-end">Total HPP</th>
                <th class="py-2 text-center">Rincian</th>
            </tr></thead>
            <tbody>
                @forelse($invoiceBundles as $bundle)
                @php $collapseId = 'invoiceBundle'.$loop->index; @endphp
                <tr style="background:#fff">
                    <td class="px-3 py-3">
                        <div style="font-weight:800;color:#111827">{{ $bundle['customer']?->company_name ?? 'Customer tidak tersedia' }}</div>
                        <small class="text-muted">{{ $bundle['customer']?->customer_code ?? '-' }}</small>
                    </td>
                    <td class="py-3 text-center"><span class="badge bg-dark">{{ $bundle['invoice_count'] }} invoice</span></td>
                    <td class="py-3 text-end" style="font-weight:700">{{ idr($bundle['total_invoice']) }}</td>
                    <td class="py-3 text-end" style="font-weight:700;color:#059669">{{ idr($bundle['total_paid']) }}</td>
                    <td class="py-3 text-end" style="font-weight:700;color:{{ $bundle['outstanding'] > 0 ? '#dc2626' : '#059669' }}">{{ idr($bundle['outstanding']) }}</td>
                    <td class="py-3 text-end">{{ idr($bundle['total_hpp']) }}</td>
                    <td class="py-3 text-center">
                        <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                            <i class="fas fa-chevron-down me-1"></i> Lihat Invoice
                        </button>
                    </td>
                </tr>
                <tr class="collapse" id="{{ $collapseId }}"><td colspan="7" class="p-0" style="background:#f8fafc">
                    <div class="p-3">
                        <div class="table-responsive"><table class="table table-sm table-bordered bg-white mb-0" style="font-size:12px">
                            <thead><tr>
                                <th>ID / No Invoice</th><th>Buat / Tempo</th><th>Tipe / Pajak</th>
                                <th class="text-end">HPP</th><th class="text-end">Tagihan</th><th class="text-end">Laba</th>
                                <th>Status Pembayaran</th><th>Umur</th><th>Aksi</th>
                            </tr></thead>
                            <tbody>@foreach($bundle['invoices'] as $inv)
                            <tr>
                                <td><a href="{{ route('invoices.show', $inv) }}" style="font-weight:700">{{ $inv->invoice_id }}</a><br><small class="text-muted">{{ $inv->invoice_number }}</small></td>
                                <td>{{ $inv->tgl_buat?->format('d M Y') ?? '-' }}<br><small class="text-muted">Tempo: {{ $inv->tgl_tempo?->format('d M Y') ?? '-' }}</small></td>
                                <td><span class="badge bg-dark">{{ $inv->jenis_label }}</span><br><small>{{ (float)$inv->ppn_persen > 0 ? 'PPN '.rtrim(rtrim(number_format($inv->ppn_persen,2),'0'),'.').'%' : 'Non-PPN' }} · {{ $inv->do_count }} DO</small></td>
                                <td class="text-end">{{ idr($inv->total_hpp) }}</td>
                                <td class="text-end" style="font-weight:700">{{ idr($inv->grand_total ?: $inv->total_jual) }}</td>
                                <td class="text-end text-success">{{ idr($inv->laba) }}</td>
                                <td><span class="badge bg-{{ $inv->status_color }}">{{ $inv->status_label }}</span><br><small class="text-success">Terbayar {{ idr($inv->total_paid) }}</small><br><small class="text-danger">Sisa {{ idr($inv->outstanding) }}</small></td>
                                <td>@php $um = $inv->umur_hari; @endphp @if($um !== null)<span style="color:{{ $um < 0 ? '#dc2626' : '#6b7280' }}">{{ $um }} hr</span>@else - @endif</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('invoices.show', $inv) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px" title="Detail"><i class="fas fa-list"></i></a>
                                    <a href="{{ route('invoices.print', $inv) }}" target="_blank" class="btn btn-sm btn-outline-secondary" style="padding:3px 7px" title="Cetak"><i class="fas fa-print"></i></a>
                                    <a href="{{ route('invoices.pdf', $inv) }}" class="btn btn-sm btn-outline-danger" style="padding:3px 7px" title="Unduh PDF"><i class="fas fa-file-pdf"></i></a>
                                    <a href="{{ route('invoices.excel', $inv) }}" class="btn btn-sm btn-outline-success" style="padding:3px 7px" title="Unduh Excel"><i class="fas fa-file-excel"></i></a>
                                    @if($inv->status==='draft')
                                    <form method="POST" action="{{ route('invoices.submit',$inv) }}" class="d-inline">@csrf<button class="btn btn-sm btn-outline-success" style="padding:3px 7px" title="Terbitkan"><i class="fas fa-paper-plane"></i></button></form>
                                    @elseif(in_array($inv->status, ['invoice', 'termin'], true))
                                    <button class="btn btn-sm btn-outline-success" style="padding:3px 7px" title="Catat pembayaran" data-bs-toggle="modal" data-bs-target="#payModal{{ $inv->id }}"><i class="fas fa-money-bill-wave"></i></button>
                                    @endif
                                    @if($u->canAccess('finance'))
                                    @include('components.delete-request-button', ['module'=>'invoices','id'=>$inv->id,'label'=>$inv->invoice_number ?? $inv->invoice_id,'pending'=>in_array($inv->id,$pendingDeletionIds ?? [])])
                                    @endif
                                </td>
                            </tr>
                            @endforeach</tbody>
                        </table></div>
                    </div>
                </td></tr>
                @empty
                <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada invoice pada tab ini.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
    <div class="mt-3">{{ $invoiceBundles->links() }}</div>
</div></div>

{{-- Modal pembayaran per invoice --}}
@foreach($listedInvoices as $inv)
@if(in_array($inv->status, ['invoice', 'termin'], true))
<div class="modal fade" id="payModal{{ $inv->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('invoices.pay',$inv->id) }}">@csrf
        <div class="modal-header"><h6 class="modal-title">Pembayaran {{ $inv->invoice_number }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" style="font-size:13px">
            <div class="row g-2 mb-3">
                <div class="col-4">Tagihan<br><b>{{ idr($inv->grand_total ?: $inv->total_jual) }}</b></div>
                <div class="col-4">Terbayar<br><b class="text-success">{{ idr($inv->total_paid) }}</b></div>
                <div class="col-4">Sisa<br><b class="text-danger">{{ idr($inv->outstanding) }}</b></div>
            </div>
            <label class="form-label">Jenis Pembayaran</label>
            <select name="payment_type" class="form-select form-select-sm mb-2 payment-type" data-amount-target="payAmount{{ $inv->id }}" required>
                <option value="termin">Pembayaran Titip / Termin</option>
                <option value="pelunasan">Pelunasan seluruh sisa</option>
            </select>
            <div id="payAmount{{ $inv->id }}" class="payment-amount-wrap">
                <label class="form-label">Nominal Termin</label>
                <input type="number" name="amount" min="1" max="{{ max(1, $inv->outstanding - 1) }}" class="form-control form-control-sm mb-2" placeholder="Masukkan nominal pembayaran" required>
                <small class="text-muted d-block mb-2">Harus lebih kecil dari sisa tagihan. Untuk membayar seluruh sisa, pilih Pelunasan.</small>
            </div>
            <label class="form-label">Tanggal Pembayaran</label>
            <input type="date" name="tgl_pencairan" class="form-control form-control-sm mb-2" value="{{ now()->toDateString() }}" required>
            <label class="form-label">Catatan</label>
            <textarea name="note" class="form-control form-control-sm" rows="2" placeholder="Opsional"></textarea>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Batal</button><button class="btn btn-sm btn-success">Simpan Pembayaran</button></div>
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
                        <option value="">{{ $invoiceCustomers->isEmpty() ? '— Belum ada customer dengan DO Closed siap tagih —' : '— Pilih Customer —' }}</option>
                        @foreach($invoiceCustomers as $c)<option value="{{ $c->id }}" data-top-days="{{ $c->effective_top_days }}">{{ $c->company_name }} @if($c->invoice_code)({{ $c->invoice_code }})@elseif($c->customer_code)({{ $c->customer_code }})@endif</option>@endforeach
                    </select>
                    <small class="text-muted">Hanya customer dengan DO Closed yang masih memiliki komponen belum diinvoice.</small>
                </div>
                <div class="col-md-3"><label class="form-label">Tgl Buat</label><input type="date" name="tgl_buat" id="invTglBuat" class="form-control form-control-sm" value="{{ now()->toDateString() }}" required></div>
                <div class="col-md-4"><label class="form-label">Tgl Tempo</label>
                    <input type="date" name="tgl_tempo" id="invTglTempo" class="form-control form-control-sm" value="{{ now()->addDays(\App\Models\Customer::defaultTopDays())->toDateString() }}">
                    <small class="text-muted" id="invTopHint">Terisi otomatis dari TOP customer. Bisa diubah manual.</small>
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-4">
                    <label class="form-label">TR & Non-TR</label>
                    <input type="hidden" name="billing_mode" value="separate">
                    <div class="form-control form-control-sm bg-light">Otomatis dipisah: Invoice TR dan Invoice Non-TR</div>
                    <small class="text-muted">Masing-masing invoice tetap dapat berisi banyak DO customer yang sama.</small>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Jenis yang dikenakan PPN</label>
                    <div class="d-flex gap-3 pt-1">
                        <div class="form-check"><input class="form-check-input ppn-type" type="checkbox" name="ppn_types[]" id="ppnTypeTR" value="TR"><label class="form-check-label" for="ppnTypeTR">TR</label></div>
                        <div class="form-check"><input class="form-check-input ppn-type" type="checkbox" name="ppn_types[]" id="ppnTypeNTR" value="NTR"><label class="form-check-label" for="ppnTypeNTR">Non-TR</label></div>
                    </div>
                </div>
                <div class="col-md-2" id="ppnPercentWrap" style="display:none"><label class="form-label">Tarif PPN</label>
                    <div class="d-flex gap-2 pt-1">
                        <div class="form-check"><input class="form-check-input ppn-percent" type="radio" name="ppn_persen" id="ppn11" value="11" checked><label class="form-check-label" for="ppn11">11%</label></div>
                        <div class="form-check"><input class="form-check-input ppn-percent" type="radio" name="ppn_persen" id="ppn11Kecil" value="1.1"><label class="form-check-label" for="ppn11Kecil">1,1%</label></div>
                    </div>
                </div>
                <div class="col-md-3"><label class="form-label">Catatan</label><input type="text" name="notes" class="form-control form-control-sm" placeholder="Opsional"></div>
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between align-items-center gap-2 mb-1">
                    <label class="form-label mb-0">Pilih beberapa DO siap tagih <small class="text-muted">(customer yang sama, setelah DO ditutup)</small></label>
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
document.querySelectorAll('.payment-type').forEach(select => {
    const toggleAmount = () => {
        const wrap = document.getElementById(select.dataset.amountTarget);
        const input = wrap?.querySelector('input[name="amount"]');
        const isTermin = select.value === 'termin';
        if (wrap) wrap.style.display = isTermin ? '' : 'none';
        if (input) input.required = isTermin;
    };
    select.addEventListener('change', toggleAmount);
    toggleAmount();
});

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
            wrap.innerHTML = '<div class="text-muted small">Tidak ada komponen DO siap tagih.<br>Draft invoice normalnya dibuat otomatis setelah harga disetujui dan <b>DO ditutup</b>. Komponen yang sudah masuk invoice tidak dapat dipilih ulang.</div>';
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
    _invCust.addEventListener('change', function(){
        loadAvailableDos(this.value);
        applyCustomerTop();
    });
}

// Tgl Tempo mengikuti TOP customer terpilih; tetap dapat ditimpa manual.
function applyCustomerTop() {
    const select = document.getElementById('invCustomer');
    const tglBuat = document.getElementById('invTglBuat');
    const tglTempo = document.getElementById('invTglTempo');
    const hint = document.getElementById('invTopHint');
    if (!select || !tglBuat || !tglTempo) return;

    const option = select.options[select.selectedIndex];
    const topDays = parseInt(option?.dataset?.topDays ?? '', 10);
    if (!tglBuat.value || isNaN(topDays)) return;

    const due = new Date(tglBuat.value + 'T00:00:00');
    due.setDate(due.getDate() + topDays);
    tglTempo.value = due.toISOString().slice(0, 10);
    if (hint) hint.textContent = 'TOP ' + topDays + ' hari dari tanggal invoice. Bisa diubah manual.';
}
document.getElementById('invTglBuat')?.addEventListener('change', applyCustomerTop);
const _ppnTypes = [...document.querySelectorAll('.ppn-type')];
function togglePpnInput(){
    const taxable = _ppnTypes.some(input => input.checked);
    const wrap = document.getElementById('ppnPercentWrap');
    if (wrap) wrap.style.display = taxable ? '' : 'none';
    document.querySelectorAll('.ppn-percent').forEach(input => input.required = taxable);
}
_ppnTypes.forEach(input => input.addEventListener('change', togglePpnInput));
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
