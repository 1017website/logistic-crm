@extends('layouts.app')
@section('title', 'Detail Invoice')
@section('page-title', $invoice->invoice_id)
@section('page-subtitle', $invoice->invoice_number)

@section('content')
@php $u = auth()->user(); @endphp
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
                    <a href="{{ route('invoices.index', ['tab'=>$invoice->status]) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                </div>
            </div>
            <div class="row g-2" style="font-size:13px">
                <div class="col-md-6"><span class="text-muted">Customer</span><br><b>{{ $invoice->customer?->company_name ?? '-' }}</b></div>
                <div class="col-md-3"><span class="text-muted">Tipe Invoice</span><br><b>{{ $invoice->jenis_label }}</b></div>
                <div class="col-md-3"><span class="text-muted">Pajak</span><br><b>{{ (float)$invoice->ppn_persen > 0 ? 'PPN '.rtrim(rtrim(number_format($invoice->ppn_persen,2),'0'),'.').'%' : 'Non-PPN' }}</b></div>
                <div class="col-md-3"><span class="text-muted">Tgl Buat</span><br>{{ $invoice->tgl_buat?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3"><span class="text-muted">Tgl Tempo</span><br>{{ $invoice->tgl_tempo?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-3"><span class="text-muted">Operator</span><br>{{ $invoice->operator?->name ?? '-' }}</div>
                @if($invoice->tgl_pencairan)<div class="col-md-3"><span class="text-muted">Tgl Cair</span><br>{{ $invoice->tgl_pencairan->format('d M Y') }}</div>@endif
            </div>
        </div></div>

        <div class="card"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">DO dalam Invoice</h6>
            <table class="table table-sm mb-0" style="font-size:12px">
                <thead><tr><th>No DO</th><th>Tipe / Keterangan</th><th>Tujuan</th><th class="text-end">HPP</th><th class="text-end">Jual</th></tr></thead>
                <tbody>
                    @foreach($invoice->items as $it)
                    <tr>
                        <td>{{ $it->deliveryOrder?->do_number ?? $it->requestOrder?->do_number ?? '-' }}</td>
                        <td><span class="badge {{ $it->item_type === 'TR' ? 'bg-primary' : 'bg-secondary' }}">{{ $it->item_type }}</span> {{ $it->description }}</td>
                        <td>{{ $it->requestOrder?->tujuan ?? $it->requestOrder?->destination ?? '-' }}</td>
                        <td class="text-end">{{ idr($it->hpp) }}</td>
                        <td class="text-end">{{ idr($it->jual) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr style="font-weight:700"><td colspan="3" class="text-end">Subtotal</td><td class="text-end">{{ idr($invoice->total_hpp) }}</td><td class="text-end">{{ idr($invoice->total_jual) }}</td></tr>
                    @if($invoice->ppn_nominal > 0)
                    <tr><td colspan="4" class="text-end text-muted">PPN {{ rtrim(rtrim(number_format($invoice->ppn_persen,2),'0'),'.') }}%</td><td class="text-end">{{ idr($invoice->ppn_nominal) }}</td></tr>
                    @endif
                    <tr style="font-weight:800"><td colspan="4" class="text-end">Grand Total</td><td class="text-end" style="color:var(--primary)">{{ idr($invoice->grand_total ?: $invoice->total_jual) }}</td></tr>
                </tfoot>
            </table>
        </div></div>
    </div>

    <div class="col-lg-4">
        @if($u->canAccess('invoices'))
        <div class="card mb-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px">Edit Nomor Invoice</h6>
            <form method="POST" action="{{ route('invoices.number', $invoice->id) }}">@csrf @method('PUT')
                <input type="text" name="invoice_number" class="form-control form-control-sm mb-2" value="{{ $invoice->invoice_number }}">
                <button class="btn btn-sm btn-outline-primary w-100">Simpan Nomor</button>
            </form>
        </div></div>
        <div class="card mb-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px">PPN</h6>
            <form method="POST" action="{{ route('invoices.ppn', $invoice->id) }}">@csrf @method('PUT')
                <div class="input-group input-group-sm mb-2">
                    <input type="number" step="0.01" name="ppn_persen" class="form-control" value="{{ $invoice->ppn_persen }}" min="0" max="100">
                    <span class="input-group-text">%</span>
                </div>
                <button class="btn btn-sm btn-outline-primary w-100">Terapkan PPN</button>
            </form>
        </div></div>
        @endif

        <div class="card"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px">Status</h6>
            @if($invoice->status==='draft')
            <form method="POST" action="{{ route('invoices.submit',$invoice->id) }}">@csrf<button class="btn btn-success btn-sm w-100"><i class="fas fa-paper-plane me-1"></i> Terbitkan Invoice</button></form>
            @elseif($invoice->status==='invoice')
            <div class="alert alert-info py-2" style="font-size:11px">Invoice sudah terbit dan tetap dapat menunggu pembayaran sampai pengiriman selesai.</div>
            <form method="POST" action="{{ route('invoices.pay',$invoice->id) }}" class="mb-2">@csrf
                <label class="form-label" style="font-size:12px">Tgl Pencairan</label>
                <input type="date" name="tgl_pencairan" class="form-control form-control-sm mb-2" value="{{ now()->toDateString() }}" required>
                <button class="btn btn-success btn-sm w-100"><i class="fas fa-money-bill-wave me-1"></i> Tandai Lunas</button>
            </form>
            <form method="POST" action="{{ route('invoices.unsubmit',$invoice->id) }}">@csrf<button class="btn btn-link btn-sm text-danger w-100" style="font-size:11px">Kembalikan ke draft</button></form>
            @else
            <div class="alert alert-success mb-0" style="font-size:13px"><i class="fas fa-check-circle me-1"></i> Lunas {{ $invoice->tgl_pencairan?->format('d M Y') }}</div>
            @endif
        </div></div>
    </div>
</div>
@endsection
