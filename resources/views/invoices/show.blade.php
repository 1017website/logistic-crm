@extends('layouts.app')
@section('title', 'Detail Invoice')
@section('page-title', $invoice->invoice_id)
@section('page-subtitle', $invoice->invoice_number)

@section('content')
@php
    $u = auth()->user();
    $hasPayment = in_array($invoice->status, ['termin', 'paid'], true);
    $canEditInvoice = !$hasPayment && ($u->isSuperAdmin() || ($u->isFinance() && $invoice->edit_request_status === 'approved'));
    $canEditPpn = !$hasPayment && ($canEditInvoice
        || ($invoice->status === 'draft' && ($u->isFinance() || $u->isAdmin())));
@endphp
<div class="row g-3">
    <div class="col-lg-8">
        @if(session('success'))<div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>@endif
        @foreach($errors->all() as $e)<div class="alert alert-danger py-2" style="font-size:13px">{{ $e }}</div>@endforeach

        <div class="card mb-3"><div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1" style="font-weight:800">{{ $invoice->invoice_number }}</h5>
                    <span class="badge bg-{{ $invoice->status_color }}">{{ $invoice->status_label }}</span>
                </div>
                <div class="d-flex gap-2">
                    <form method="GET" action="{{ route('invoices.print', $invoice->id) }}" target="_blank" class="d-flex gap-1">
                        <select name="type" class="form-select form-select-sm no-select2" aria-label="Pilih tipe cetak">
                            <option value="all">Semua layanan</option>
                            @if($invoice->items->contains('item_type', 'TR'))<option value="TR">Trucking saja</option>@endif
                            @if($invoice->items->contains('item_type', 'NTR'))<option value="NTR">Non-Trucking saja</option>@endif
                        </select>
                        <button class="btn btn-sm btn-outline-secondary text-nowrap"><i class="fas fa-print me-1"></i> Cetak</button>
                    </form>
                    <a href="{{ route('invoices.pdf', $invoice) }}" class="btn btn-sm btn-outline-danger text-nowrap"><i class="fas fa-file-pdf me-1"></i> PDF</a>
                    <a href="{{ route('invoices.excel', $invoice) }}" class="btn btn-sm btn-outline-success text-nowrap"><i class="fas fa-file-excel me-1"></i> Excel</a>
                    <a href="{{ route('invoices.index', ['tab'=>$hasPayment ? 'paid' : $invoice->status]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                </div>
            </div>
            <div class="row g-2" style="font-size:13px">
                <div class="col-md-6"><span class="text-muted">Customer</span><br><b>{{ $invoice->customer?->company_name ?? '-' }}</b></div>
                <div class="col-md-3"><span class="text-muted">Tipe Invoice</span><br><b>{{ $invoice->jenis_label }}</b></div>
                <div class="col-md-3"><span class="text-muted">Pajak</span><br><b>{{ (float)$invoice->ppn_persen > 0 ? 'PPN '.rtrim(rtrim(number_format($invoice->ppn_persen,2),'0'),'.').'%' : 'Non-PPN' }}</b></div>
                <div class="col-md-3"><span class="text-muted">Periode Invoice</span><br><b>{{ $invoice->periode_invoice?->translatedFormat('F Y') ?? '-' }}</b></div>
                <div class="col-md-3"><span class="text-muted">Tgl Buat</span><br>{{ $invoice->tgl_buat?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3"><span class="text-muted">Tgl Submit</span><br>{{ $invoice->submitted_at?->format('d M Y H:i') ?? 'Belum diterbitkan' }}</div>
                <div class="col-md-3"><span class="text-muted">Tgl Tempo</span><br>{{ $invoice->tgl_tempo?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3"><span class="text-muted">Operator</span><br>{{ $invoice->operator?->name ?? '-' }}</div>
                @if($invoice->tgl_pencairan)<div class="col-md-3"><span class="text-muted">Tgl Cair</span><br>{{ $invoice->tgl_pencairan->format('d M Y') }}</div>@endif
            </div>
        </div></div>

        <div class="card"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">DO dalam Invoice</h6>
            <table class="table table-sm mb-0" style="font-size:12px">
                <thead><tr><th>No DO</th><th>Nama</th><th>Uraian</th><th>Jenis Truck</th><th class="text-end">Qty</th><th class="text-end">Harga</th><th class="text-end">Jumlah</th>@if($canEditInvoice)<th></th>@endif</tr></thead>
                <tbody>
                    @foreach($invoice->items as $it)
                    <tr>
                        <td>{{ $it->deliveryOrder?->do_number ?? $it->requestOrder?->do_number ?? '-' }}</td>
                        <td>@if($canEditInvoice)<input form="item-form-{{ $it->id }}" name="item_name" class="form-control form-control-sm" value="{{ $it->item_name }}" required>@else <span class="badge {{ $it->item_type === 'TR' ? 'bg-primary' : 'bg-secondary' }}">{{ $it->item_type }}</span> {{ $it->item_name }} @endif</td>
                        <td>@if($canEditInvoice)<input form="item-form-{{ $it->id }}" name="description" class="form-control form-control-sm" value="{{ $it->description }}">@else{{ $it->description }}@endif</td>
                        <td>@if($canEditInvoice)<input form="item-form-{{ $it->id }}" name="truck_type" class="form-control form-control-sm" value="{{ $it->truck_type ?: $it->requestOrder?->jenis_truck }}">@else{{ $it->truck_type ?: $it->requestOrder?->jenis_truck ?: '-' }}@endif</td>
                        <td class="text-end">@if($canEditInvoice)<input form="item-form-{{ $it->id }}" type="number" step="0.001" min="0.001" name="quantity" class="form-control form-control-sm text-end" value="{{ (float)($it->quantity ?: 1) }}" required>@else{{ (float)($it->quantity ?: 1) }}@endif</td>
                        <td class="text-end">@if($canEditInvoice)<input form="item-form-{{ $it->id }}" type="number" min="0" name="unit_price" class="form-control form-control-sm text-end" value="{{ (float)($it->unit_price ?: $it->jual) }}" required>@else{{ idr($it->unit_price ?: $it->jual) }}@endif</td>
                        <td class="text-end">{{ idr($it->jual) }}</td>
                        @if($canEditInvoice)<td><form id="item-form-{{ $it->id }}" method="POST" action="{{ route('invoices.items.update', [$invoice, $it]) }}">@csrf @method('PUT')<button class="btn btn-sm btn-outline-primary"><i class="fas fa-save"></i></button></form></td>@endif
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight:700"><td colspan="{{ $canEditInvoice ? 6 : 6 }}" class="text-end">Subtotal</td><td class="text-end">{{ idr($invoice->total_jual) }}</td>@if($canEditInvoice)<td></td>@endif</tr>
                    @if($invoice->ppn_nominal > 0)
                    <tr><td colspan="6" class="text-end text-muted">PPN {{ rtrim(rtrim(number_format($invoice->ppn_persen,2),'0'),'.') }}%</td><td class="text-end">{{ idr($invoice->ppn_nominal) }}</td>@if($canEditInvoice)<td></td>@endif</tr>
                    @endif
                    <tr style="font-weight:800"><td colspan="6" class="text-end">Grand Total</td><td class="text-end" style="color:var(--primary)">{{ idr($invoice->grand_total ?: $invoice->total_jual) }}</td>@if($canEditInvoice)<td></td>@endif</tr>
                    @if($invoice->payments->isNotEmpty())
                    <tr><td colspan="6" class="text-end text-success">Total Terbayar</td><td class="text-end text-success">{{ idr($invoice->total_paid) }}</td>@if($canEditInvoice)<td></td>@endif</tr>
                    <tr style="font-weight:800"><td colspan="6" class="text-end text-danger">Sisa Tagihan</td><td class="text-end text-danger">{{ idr($invoice->outstanding) }}</td>@if($canEditInvoice)<td></td>@endif</tr>
                    @endif
                </tfoot>
            </table>
        </div></div>

        @if($invoice->payments->isNotEmpty())
        <div class="card mt-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Riwayat Pembayaran</h6>
            <div class="table-responsive"><table class="table table-sm mb-0" style="font-size:12px">
                <thead><tr><th>Tanggal</th><th>Jenis</th><th class="text-end">Nominal</th><th>Catatan</th><th>Dicatat oleh</th></tr></thead>
                <tbody>@foreach($invoice->payments->sortByDesc('payment_date') as $payment)
                    <tr><td>{{ $payment->payment_date?->format('d M Y') }}</td><td>{{ $payment->type_label }}</td><td class="text-end">{{ idr($payment->amount) }}</td><td>{{ $payment->note ?: '-' }}</td><td>{{ $payment->recorder?->name ?? '-' }}</td></tr>
                @endforeach</tbody>
            </table></div>
        </div></div>
        @endif
    </div>

    <div class="col-lg-4">
        @if($u->isFinance() && !$canEditInvoice && !$hasPayment)
        <div class="card mb-3 border-warning"><div class="card-body p-3">
            <h6 style="font-weight:700">Permintaan Edit Invoice</h6>
            @if($invoice->edit_request_status === 'pending')
                <div class="alert alert-warning py-2 mb-0" style="font-size:12px">Menunggu persetujuan Super Admin.<br><b>Alasan:</b> {{ $invoice->edit_request_reason }}</div>
            @else
                @if($invoice->edit_request_status === 'rejected')<div class="alert alert-danger py-2" style="font-size:12px">Permintaan sebelumnya ditolak. {{ $invoice->edit_review_note }}</div>@endif
                <form method="POST" action="{{ route('invoices.request-edit', $invoice) }}">@csrf
                    <textarea name="reason" class="form-control form-control-sm mb-2" rows="3" required placeholder="Jelaskan data yang perlu diedit"></textarea>
                    <button class="btn btn-warning btn-sm w-100"><i class="fas fa-lock-open me-1"></i> Ajukan Edit ke Super Admin</button>
                </form>
            @endif
        </div></div>
        @endif

        @if($u->isSuperAdmin() && $invoice->edit_request_status === 'pending')
        <div class="card mb-3 border-warning"><div class="card-body p-3">
            <h6 style="font-weight:700">Approval Edit (Super Admin)</h6>
            <p style="font-size:12px"><b>{{ $invoice->editRequester?->name }}</b>: {{ $invoice->edit_request_reason }}</p>
            <form method="POST" action="{{ route('invoices.review-edit', $invoice) }}">@csrf
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan approval"></textarea>
                <div class="d-flex gap-2"><button name="action" value="approve" class="btn btn-success btn-sm flex-fill">Setujui</button><button name="action" value="reject" class="btn btn-outline-danger btn-sm flex-fill">Tolak</button></div>
            </form>
        </div></div>
        @endif

        @if($u->isFinance() && $invoice->edit_request_status === 'approved')
        <div class="alert alert-success py-2" style="font-size:12px">Edit disetujui oleh {{ $invoice->editReviewer?->name ?? 'Super Admin' }}. Selesaikan perubahan lalu kunci kembali.
            <form method="POST" action="{{ route('invoices.finish-edit', $invoice) }}" class="mt-2">@csrf<button class="btn btn-sm btn-success w-100">Selesai Edit & Kunci</button></form>
        </div>
        @endif

        @if($canEditInvoice)
        <div class="card mb-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px">Edit Nomor Invoice</h6>
            <form method="POST" action="{{ route('invoices.number', $invoice->id) }}">@csrf @method('PUT')
                <input type="text" name="invoice_number" class="form-control form-control-sm mb-2" value="{{ $invoice->invoice_number }}">
                <button class="btn btn-sm btn-outline-primary w-100">Simpan Nomor</button>
            </form>
        </div></div>
        @endif

        @if($canEditPpn)
        @php
            $selectedTaxTypes = $taxInvoices->filter(fn($taxInvoice) => (float)$taxInvoice->ppn_persen > 0)->keys();
            $selectedTaxRate = (float)($taxInvoices->first(fn($taxInvoice) => (float)$taxInvoice->ppn_persen > 0)?->ppn_persen ?? 11);
        @endphp
        <div class="card mb-3 border-info"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px">PPN</h6>
            <p class="text-muted mb-2" style="font-size:11px">Centang jenis invoice yang dikenakan PPN. Jenis yang tidak dicentang menjadi Non-PPN.</p>
            <form method="POST" action="{{ route('invoices.ppn', $invoice->id) }}" id="invoicePpnForm">@csrf @method('PUT')
                <label class="form-label mb-1" style="font-size:12px"><b>Pilih jenis invoice</b></label>
                <div class="d-flex gap-3 border rounded p-2 mb-2">
                    @foreach(['TR' => 'Trucking (TR)', 'NTR' => 'Non-Trucking (Non-TR)'] as $type => $label)
                        @if($taxInvoices->has($type))
                        <div class="form-check">
                            <input class="form-check-input invoice-ppn-type" type="checkbox" name="ppn_types[]" id="invoicePpnType{{ $type }}" value="{{ $type }}" @checked($selectedTaxTypes->contains($type))>
                            <label class="form-check-label" for="invoicePpnType{{ $type }}">{{ $label }}</label>
                        </div>
                        @endif
                    @endforeach
                </div>
                <div id="invoicePpnRateWrap" class="border rounded p-2 mb-2" style="{{ $selectedTaxTypes->isEmpty() ? 'display:none' : '' }}">
                    <label class="form-label mb-1" style="font-size:12px">Pilih tarif PPN</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input invoice-ppn-rate" type="radio" name="ppn_persen" id="invoicePpn11" value="11" @checked($selectedTaxRate === 11.0)>
                            <label class="form-check-label" for="invoicePpn11">11%</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input invoice-ppn-rate" type="radio" name="ppn_persen" id="invoicePpn11Kecil" value="1.1" @checked($selectedTaxRate === 1.1)>
                            <label class="form-check-label" for="invoicePpn11Kecil">1,1%</label>
                        </div>
                    </div>
                </div>
                <button class="btn btn-sm btn-info w-100">Simpan Pengaturan PPN</button>
            </form>
        </div></div>
        @endif

        <div class="card"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px">Status</h6>
            @if($invoice->status==='draft')
            <form method="POST" action="{{ route('invoices.submit',$invoice->id) }}">@csrf
                <label class="form-label" style="font-size:12px">Masukkan ke Periode Invoice</label>
                <input type="month" name="periode_invoice" class="form-control form-control-sm mb-1" value="{{ ($invoice->periode_invoice ?: $invoice->tgl_buat ?: now())->format('Y-m') }}" required>
                <small class="text-muted d-block mb-2">Hanya menentukan bulan laporan. TOP mengikuti tanggal invoice diterbitkan, kecuali jatuh tempo diatur manual.</small>
                <button class="btn btn-success btn-sm w-100"><i class="fas fa-paper-plane me-1"></i> Terbitkan Invoice</button>
            </form>
            @elseif(in_array($invoice->status, ['invoice', 'termin'], true))
            <div class="alert alert-info py-2" style="font-size:11px">Invoice sudah terbit dan tetap dapat menunggu pembayaran sampai pengiriman selesai.</div>
            <form method="POST" action="{{ route('invoices.pay',$invoice->id) }}" class="mb-2">@csrf
                <div class="row g-1 mb-2" style="font-size:11px"><div class="col-4">Tagihan<br><b>{{ idr($invoice->grand_total ?: $invoice->total_jual) }}</b></div><div class="col-4">Terbayar<br><b class="text-success">{{ idr($invoice->total_paid) }}</b></div><div class="col-4">Sisa<br><b class="text-danger">{{ idr($invoice->outstanding) }}</b></div></div>
                <label class="form-label" style="font-size:12px">Jenis Pembayaran</label>
                <select name="payment_type" id="detailPaymentType" class="form-select form-select-sm mb-2" required><option value="termin">Pembayaran Titip / Termin</option><option value="pelunasan">Pelunasan seluruh sisa</option></select>
                <div id="detailPaymentAmount">
                    <label class="form-label" style="font-size:12px">Nominal Termin</label>
                    <input type="number" name="amount" min="1" max="{{ max(1, $invoice->outstanding - 1) }}" class="form-control form-control-sm mb-1" required>
                    <small class="text-muted d-block mb-2">Harus lebih kecil dari sisa tagihan.</small>
                </div>
                <label class="form-label" style="font-size:12px">Tanggal Pembayaran</label>
                <input type="date" name="tgl_pencairan" class="form-control form-control-sm mb-2" value="{{ now()->toDateString() }}" required>
                <label class="form-label" style="font-size:12px">Catatan</label>
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Opsional"></textarea>
                <button class="btn btn-success btn-sm w-100"><i class="fas fa-money-bill-wave me-1"></i> Simpan Pembayaran</button>
            </form>
            @if($invoice->status === 'invoice')
            <form method="POST" action="{{ route('invoices.unsubmit',$invoice->id) }}">@csrf<button class="btn btn-link btn-sm text-danger w-100" style="font-size:11px">Kembalikan ke draft</button></form>
            @endif
            @else
            <div class="alert alert-success mb-0" style="font-size:13px"><i class="fas fa-check-circle me-1"></i> Lunas {{ $invoice->tgl_pencairan?->format('d M Y') }}</div>
            @endif
        </div></div>
    </div>
</div>
<script>
const invoicePpnTypes = [...document.querySelectorAll('.invoice-ppn-type')];
const toggleInvoicePpnRate = () => {
    const taxable = invoicePpnTypes.some(input => input.checked);
    const wrap = document.getElementById('invoicePpnRateWrap');
    if (wrap) wrap.style.display = taxable ? '' : 'none';
    document.querySelectorAll('.invoice-ppn-rate').forEach(input => input.required = taxable);
};
invoicePpnTypes.forEach(input => input.addEventListener('change', toggleInvoicePpnRate));
toggleInvoicePpnRate();

const detailPaymentType = document.getElementById('detailPaymentType');
const toggleDetailPaymentAmount = () => {
    const wrap = document.getElementById('detailPaymentAmount');
    const input = wrap?.querySelector('input[name="amount"]');
    const isTermin = detailPaymentType?.value === 'termin';
    if (wrap) wrap.style.display = isTermin ? '' : 'none';
    if (input) input.required = isTermin;
};
detailPaymentType?.addEventListener('change', toggleDetailPaymentAmount);
toggleDetailPaymentAmount();
</script>
@endsection
