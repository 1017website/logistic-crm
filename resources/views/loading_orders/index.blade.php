@extends('layouts.app')
@section('title', 'Surat Perintah Muat')
@section('page-title', 'Surat Perintah Muat')
@section('page-subtitle', 'Buat, sesuaikan, dan cetak surat perintah muat')
@section('content')
<div class="d-flex flex-wrap justify-content-between gap-3 mb-3">
    <form method="get" class="d-flex gap-2">
        <input class="form-control" name="search" value="{{ $search }}" placeholder="Cari nomor, penerima, rute, driver..." aria-label="Cari surat">
        <button class="btn btn-outline-primary">Cari</button>
    </form>
    <a class="btn btn-primary" href="{{ route('loading-orders.create') }}"><i class="fas fa-plus me-2"></i>Buat Surat Perintah Muat</a>
</div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Nomor / Tanggal</th><th>Penerima</th><th>Rute</th><th>Driver / Kendaraan</th><th>Aksi</th></tr></thead>
    <tbody>@forelse($orders as $order)
    <tr>
        <td>{{ $order->number }}<div class="small text-muted">{{ $order->letter_date->format('d/m/Y') }}</div></td>
        <td>{{ $order->recipient }}</td><td>{{ $order->route }}</td>
        <td>{{ $order->driver_name }}<div class="small text-muted">{{ $order->vehicle_number }}</div></td>
        <td class="text-nowrap"><a class="btn btn-sm btn-outline-primary" href="{{ route('loading-orders.edit', $order) }}">Edit</a>
        <a class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" href="{{ route('loading-orders.pdf', $order) }}">Pratinjau</a>
        <a class="btn btn-sm btn-primary" href="{{ route('loading-orders.pdf', [$order, 'download' => 1]) }}">PDF</a></td>
    </tr>
    @empty<tr><td colspan="5" class="text-center text-muted py-5">{{ $search ? 'Tidak ada surat yang cocok dengan pencarian.' : 'Belum ada Surat Perintah Muat. Klik Buat Surat Perintah Muat untuk memulai.' }}</td></tr>@endforelse</tbody>
</table></div></div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
