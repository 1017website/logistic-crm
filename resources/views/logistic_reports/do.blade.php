@extends('layouts.app')
@section('title', 'Laporan DO')
@section('page-title', 'Laporan DO')
@section('page-subtitle', 'Rekap Delivery Order')

@section('content')
<div class="row g-3"><div class="col-12">

    <a href="{{ route('logistic-reports.index') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i> Kembali</a>

    {{-- Filter --}}
    <form method="GET" action="{{ route('logistic-reports.do') }}">
        <div class="card mb-3"><div class="card-body p-3"><div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Client</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">Semua Client</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->company_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Jenis</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="all" @selected($jenis=='all')>Semua</option>
                    <option value="uninvoiced" @selected($jenis=='uninvoiced')>Belum Ditagih</option>
                    <option value="partial" @selected($jenis=='partial')>Ditagih Sebagian</option>
                    <option value="invoiced" @selected($jenis=='invoiced')>Sudah Ditagih</option>
                    <option value="paid" @selected($jenis=='paid')>Sudah Dibayar</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Bulan</label>
                <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="DO / client" value="{{ $search }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search"></i></button>
                <a href="{{ route('logistic-reports.do.export', request()->query()) }}" class="btn btn-success btn-sm flex-fill"><i class="fas fa-download"></i></a>
            </div>
        </div></div></div>
    </form>

    {{-- Ringkasan --}}
    @php
        $sumHpp = $dos->getCollection()->sum(fn($d)=>(float)$d->total_cost);
        $sumJual = $dos->getCollection()->sum(fn($d)=>(float)$d->total_revenue);
    @endphp
    <div class="d-flex gap-3 mb-3 flex-wrap" style="font-size:13px">
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Jumlah DO (halaman ini)</div><div style="font-weight:800">{{ $dos->total() }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">HPP</div><div style="font-weight:800">{{ idr($sumHpp) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Jual</div><div style="font-weight:800;color:var(--primary)">{{ idr($sumJual) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Laba</div><div style="font-weight:800;color:#16a34a">{{ idr($sumJual-$sumHpp) }}</div></div></div>
    </div>

    {{-- Tabel --}}
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:12px">
            <thead style="background:#f8f9fa"><tr>
                <th class="px-3 py-2">DO</th><th class="py-2">Tanggal</th><th class="py-2">Client</th>
                <th class="py-2">Container</th><th class="py-2">Kota/Tujuan</th><th class="py-2">Truck/Nopol</th>
                <th class="py-2 text-end">HPP</th><th class="py-2 text-end">Jual</th><th class="py-2">Tagih</th>
            </tr></thead>
            <tbody>
                @forelse($dos as $do)
                <tr>
                    <td class="px-3 py-2" style="font-weight:700;color:var(--primary)">{{ $do->do_number }}</td>
                    <td class="py-2">{{ $do->order_date?->format('d M Y') }}</td>
                    <td class="py-2">{{ $do->customer?->company_name ?? '-' }}</td>
                    <td class="py-2">{{ $do->no_container ?? '-' }}</td>
                    <td class="py-2">{{ $do->kota ?? '-' }} / {{ $do->tujuan ?? '-' }}</td>
                    <td class="py-2">{{ $do->no_pol ?? '-' }} <span class="text-muted">{{ $do->jenis_truck }}</span></td>
                    <td class="py-2 text-end">{{ idr($do->total_cost) }}</td>
                    <td class="py-2 text-end">{{ idr($do->total_revenue) }}</td>
                    <td class="py-2">
                        @php $cmap=['uninvoiced'=>['secondary','Belum'],'partial'=>['info','Sebagian'],'invoiced'=>['warning','Ditagih'],'paid'=>['success','Lunas']]; $cm=$cmap[$do->invoice_status]??['secondary','-']; @endphp
                        <span class="badge bg-{{ $cm[0] }}">{{ $cm[1] }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center py-4 text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
    <div class="mt-3">{{ $dos->links() }}</div>
</div></div>
@endsection
