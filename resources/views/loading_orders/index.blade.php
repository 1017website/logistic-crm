@extends('layouts.app')
@section('title', 'Surat Perintah Muat')
@section('page-title', 'Surat Perintah Muat')
@section('page-subtitle', 'Buat, kelola, dan cetak surat perintah muat dalam format PDF')
@section('content')
<div class="row g-3"><div class="col-12">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <div style="font-size:14px;font-weight:700;color:#111827">Daftar Surat Perintah Muat</div>
            <div style="font-size:12px;color:#6b7280">Kelola surat dan unduh PDF dengan tanda tangan elektronik.</div>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-primary btn-sm" href="{{ route('loading-orders.create') }}"><i class="fas fa-plus me-1"></i> Buat Surat Perintah Muat</a>
        </div>
    </div>
    <form method="GET" action="{{ route('loading-orders.index') }}">
        <div class="card mb-3"><div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-10">
                    <label for="search" class="form-label">Pencarian</label>
                    <input id="search" class="form-control form-control-sm" name="search" value="{{ $search }}" placeholder="Cari nomor, penerima, rute, driver, atau kendaraan...">
                </div>
                <div class="col-md-2"><button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i> Cari</button></div>
            </div>
        </div></div>
    </form>
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="font-size:13px">
            <thead style="background:#f8f9fa"><tr>
                <th class="px-3 py-2">No. Surat</th><th class="py-2">Tanggal</th><th class="py-2">Penerima</th>
                <th class="py-2">Rute</th><th class="py-2">Driver / Kendaraan</th><th class="py-2">Penandatangan</th><th class="py-2 text-end pe-3">Aksi</th>
            </tr></thead>
            <tbody>@forelse($orders as $order)
            <tr>
                <td class="px-3 py-2"><span style="font-weight:700;color:#111827">{{ $order->number }}</span></td>
                <td>{{ $order->letter_date->format('d M Y') }}</td>
                <td><span style="font-weight:600">{{ $order->recipient }}</span></td><td style="font-size:12px">{{ $order->route }}</td>
                <td>{{ $order->driver_name }}<br><span class="text-muted" style="font-size:11px">{{ $order->vehicle_number }}</span></td>
                <td>{{ $order->signatory_name }}</td>
                <td class="text-end pe-3 text-nowrap">
                    <a class="btn btn-sm btn-outline-primary" style="padding:4px 9px" target="_blank" rel="noopener" href="{{ route('loading-orders.pdf', $order) }}" title="Print Preview"><i class="fas fa-print me-1"></i> Preview</a>
                    <a class="btn btn-sm btn-primary" style="padding:4px 9px" href="{{ route('loading-orders.pdf', [$order, 'download' => 1]) }}" title="Unduh PDF"><i class="fas fa-file-pdf me-1"></i> PDF</a>
                    <a class="btn btn-sm btn-outline-secondary" style="padding:4px 8px" href="{{ route('loading-orders.edit', $order) }}" title="Edit" aria-label="Edit {{ $order->number }}"><i class="fas fa-pen"></i></a>
                </td>
            </tr>
            @empty
            <tr><td colspan="7" class="text-center text-muted py-5">
                <i class="fas fa-file-alt d-block mb-2" style="font-size:28px;color:#d1d5db"></i>
                {{ $search ? 'Tidak ada surat yang cocok dengan pencarian.' : 'Belum ada surat perintah muat.' }}
            </td></tr>
            @endforelse</tbody>
        </table>
    </div></div></div>
    <div class="mt-3">{{ $orders->links() }}</div>
</div></div>
@endsection
