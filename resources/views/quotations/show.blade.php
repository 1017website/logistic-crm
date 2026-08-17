@extends('layouts.app')
@section('title', 'Detail Penawaran')
@section('page-title', $quotation->quotation_number)
@section('page-subtitle', $quotation->subject)

@section('content')
<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-1" style="font-weight:800">{{ $quotation->quotation_number }}</h5>
                    <span class="badge bg-{{ $quotation->status_color }}">{{ $quotation->status_label }}</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('quotations.pdf', $quotation) }}" class="btn btn-sm btn-primary"><i class="fas fa-file-pdf me-1"></i> Unduh PDF</a>
                    <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-pen me-1"></i> Edit</a>
                    <a href="{{ route('quotations.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
                </div>
            </div>
            <div class="row g-3" style="font-size:13px">
                <div class="col-md-4"><span class="text-muted">Tanggal</span><br><b>{{ $quotation->quotation_date?->format('d M Y') }}</b></div>
                <div class="col-md-4"><span class="text-muted">Customer</span><br><b>{{ $quotation->company_name }}</b></div>
                <div class="col-md-4"><span class="text-muted">Pembuat</span><br><b>{{ $quotation->user?->name ?? '-' }}</b></div>
                <div class="col-md-4"><span class="text-muted">Perihal</span><br>{{ $quotation->subject }}</div>
                <div class="col-md-4"><span class="text-muted">Lampiran</span><br>{{ $quotation->attachment }}</div>
                <div class="col-md-4"><span class="text-muted">Kota Dokumen</span><br>{{ $quotation->city }}</div>
                <div class="col-md-4"><span class="text-muted">Penerima</span><br>{{ $quotation->recipient_name }} {{ $quotation->recipient_title }}</div>
                <div class="col-md-4"><span class="text-muted">Kontak</span><br>{{ $quotation->contact_name ?: '-' }}@if($quotation->contact_phone)<br>{{ $quotation->contact_phone }}@endif</div>
                <div class="col-md-4"><span class="text-muted">Penanda Tangan</span><br>{{ $quotation->signatory_name }}<br><span class="text-muted">{{ $quotation->signatory_title }}</span></div>
                @if($quotation->recipient_address)<div class="col-12"><span class="text-muted">Alamat Penerima</span><br>{!! nl2br(e($quotation->recipient_address)) !!}</div>@endif
            </div>
        </div></div>

        <div class="card mb-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Detail Tarif</h6>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0" style="font-size:12px">
                    <thead><tr><th>No</th><th>Origin</th><th>Destination</th><th>Komoditas</th><th>Tonase</th><th>Unit</th><th class="text-end">Tarif</th></tr></thead>
                    <tbody>
                        @foreach($quotation->items as $index => $item)
                            <tr><td>{{ $index + 1 }}</td><td>{{ $item->origin }}</td><td>{{ $item->destination }}</td><td>{{ $item->commodity }}</td><td>{{ $item->tonnage }}</td><td>{{ $item->unit }}</td><td class="text-end fw-semibold">Rp {{ number_format((float)$item->rate, 0, ',', '.') }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div></div>

        <div class="card"><div class="card-body p-3" style="font-size:13px">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Isi Penawaran</h6>
            <p>{!! nl2br(e($opening)) !!}</p>
            <div class="text-muted mb-1">Syarat dan ketentuan</div>
            <ol class="mb-3">@foreach($quotation->terms ?? [] as $term)<li class="mb-1">{{ $term }}</li>@endforeach</ol>
            <div class="text-muted mb-1">Penutup</div><p class="mb-0">{!! nl2br(e($closing)) !!}</p>
        </div></div>
    </div>
</div>
@endsection
