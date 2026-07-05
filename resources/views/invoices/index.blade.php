@extends('layouts.app')
@section('title', 'Invoice')
@section('page-title', 'Invoice')
@section('page-subtitle', 'Penagihan multi-DO per customer, nomor urut per customer')

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
                   href="{{ route('invoices.index', ['tab'=>$key]) }}">{{ $label }}</a>
            </li>
            @endforeach
        </ul>
        <div class="d-flex gap-2">
            <a href="{{ route('invoices.export', ['status'=>$status]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-download me-1"></i> Excel</a>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addInvoiceModal"><i class="fas fa-plus me-1"></i> Tambah</button>
        </div>
    </div>

    {{-- Filter --}}
    <form method="GET" action="{{ route('invoices.index') }}">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <div class="card mb-3"><div class="card-body p-3"><div class="row g-2 align-items-end">
            <div class="col-md-4">
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">Semua Customer</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->company_name }}</option>@endforeach
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
                <th class="px-3 py-2">ID / No Invoice</th><th class="py-2">Buat / Tempo</th><th class="py-2">Customer</th>
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
                <tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada invoice pada tab ini.</td></tr>
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
                <div class="col-md-3"><label class="form-label">Tgl Tempo</label><input type="date" name="tgl_tempo" class="form-control form-control-sm" value="{{ now()->addDays(30)->toDateString() }}"></div>
            </div>
            <div class="mb-2">
                <label class="form-label">DO siap tagih <small class="text-muted">(approved & belum diinvoice)</small></label>
                <div id="doListWrap" class="border rounded p-2" style="max-height:260px;overflow:auto">
                    <div class="text-muted">Pilih customer dulu.</div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-3" style="font-size:13px">
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
const fmtRp = n => 'Rp ' + (n||0).toLocaleString('id-ID');

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
            wrap.innerHTML = '<div class="text-muted small">Tidak ada DO siap tagih untuk customer ini.<br>DO baru muncul setelah <b>Request DO</b> diisi <b>Rincian Biaya per Pekerjaan</b> lalu di-<b>Approve DO</b>, dan belum pernah diinvoice.</div>';
            recalcInv(); return;
        }
        wrap.innerHTML = dos.map(d => `
            <label class="d-flex align-items-center gap-2 py-1 border-bottom" style="cursor:pointer">
                <input type="checkbox" name="do_ids[]" value="${d.id}" class="doChk" data-hpp="${d.hpp}" data-jual="${d.jual}">
                <span class="flex-fill"><b>${d.do_number}</b> <span class="text-muted">${d.order_date||''} ${d.tujuan||d.destination||''}</span></span>
                <span class="text-muted">HPP ${fmtRp(d.hpp)}</span><span style="color:var(--primary)">Jual ${fmtRp(d.jual)}</span>
            </label>`).join('');
        wrap.querySelectorAll('.doChk').forEach(c => c.addEventListener('change', recalcInv));
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
// Saat modal dibuka, muat ulang sesuai customer yang sedang terpilih.
if (window.jQuery) {
    jQuery(document).on('shown.bs.modal', '#addInvoiceModal', function(){
        loadAvailableDos(document.getElementById('invCustomer')?.value);
    });
}

function recalcInv(){
    let hpp=0, jual=0, n=0;
    document.querySelectorAll('.doChk:checked').forEach(c=>{ hpp+=+c.dataset.hpp; jual+=+c.dataset.jual; n++; });
    const eh = document.getElementById('sumHpp'); if (eh) eh.textContent = fmtRp(hpp);
    const ej = document.getElementById('sumJual'); if (ej) ej.textContent = fmtRp(jual);
    const btn = document.getElementById('invSubmitBtn'); if (btn) btn.disabled = n===0;
}
</script>
@endsection
