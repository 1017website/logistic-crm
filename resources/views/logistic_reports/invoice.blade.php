@extends('layouts.app')
@section('title', 'Laporan Invoice')
@section('page-title', 'Laporan Invoice')
@section('page-subtitle', 'Tagihan, jatuh tempo & umur piutang')

@section('content')
<div class="row g-3"><div class="col-12">

    <a href="{{ route('logistic-reports.index') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i> Kembali</a>

    {{-- Filter --}}
    <form method="GET" action="{{ route('logistic-reports.invoice') }}">
        <div class="card mb-3"><div class="card-body p-3"><div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Client</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">Semua Client</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->company_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Jenis Invoice</label>
                <select name="jenis" class="form-select form-select-sm">
                    <option value="all" @selected($jenis=='all')>Semua</option>
                    <option value="unpaid" @selected($jenis=='unpaid')>Belum Terbayar</option>
                    <option value="paid" @selected($jenis=='paid')>Sudah Cair</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Periode Invoice</label>
                <input type="month" name="periode" class="form-control form-control-sm" value="{{ $periode }}">
            </div>
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">S/D Tgl Kirim Invoice</label>
                <input type="date" name="sd_tanggal" class="form-control form-control-sm" value="{{ $sdTanggal }}">
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i> Filter</button>
                <a href="{{ route('logistic-reports.invoice.export', request()->query()) }}" class="btn btn-success btn-sm flex-fill"><i class="fas fa-download me-1"></i> Excel</a>
            </div>
        </div></div></div>
    </form>

    {{-- Ringkasan --}}
    <div class="d-flex gap-3 mb-3 flex-wrap" style="font-size:13px">
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Jml Invoice</div><div style="font-weight:800">{{ $invoiceCount }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">HPP</div><div style="font-weight:800">{{ idr($sumHpp) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Harga Jual</div><div style="font-weight:800;color:var(--primary)">{{ idr($sumJual) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Laba</div><div style="font-weight:800;color:#16a34a">{{ idr($sumLaba) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Terbayar</div><div style="font-weight:800;color:#16a34a">{{ idr($sumPaid) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Outstanding Belum Terbayar</div><div style="font-weight:800;color:#dc2626">{{ idr($sumOutstanding) }}</div></div></div>
    </div>

    {{-- Tabel --}}
    <div class="card"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:12px">
            <thead style="background:#f8f9fa"><tr>
                <th class="px-3 py-2">No Urut Inv</th><th class="py-2">Periode</th><th class="py-2">Submit</th><th class="py-2">No Invoice</th>
                <th class="py-2">Customer</th><th class="py-2 text-end">HPP</th><th class="py-2 text-end">Harga Jual</th>
                <th class="py-2 text-end">Laba</th><th class="py-2 text-end">Terbayar</th><th class="py-2 text-end">Outstanding</th><th class="py-2">Due Date</th><th class="py-2 text-center">Hari</th><th class="py-2">Status</th>
            </tr></thead>
            <tbody>
                @forelse($invoices as $inv)
                <tr>
                    <td class="px-3 py-2" style="font-weight:700;color:var(--primary)">{{ $inv->invoice_id }}</td>
                    <td class="py-2"><b>{{ $inv->periode_invoice?->translatedFormat('M Y') ?? '-' }}</b></td>
                    <td class="py-2">{{ $inv->submitted_at?->format('d M Y') ?? $inv->tgl_buat?->format('d M Y') }}</td>
                    <td class="py-2" style="font-size:11px">{{ $inv->invoice_number }}</td>
                    <td class="py-2">{{ $inv->customer?->company_name ?? '-' }}</td>
                    <td class="py-2 text-end">{{ idr($inv->total_hpp) }}</td>
                    <td class="py-2 text-end">{{ idr($inv->total_jual) }}</td>
                    <td class="py-2 text-end" style="color:#16a34a">{{ idr($inv->laba) }}</td>
                    <td class="py-2 text-end text-success">{{ idr($inv->total_paid) }}</td>
                    <td class="py-2 text-end text-danger">{{ idr($inv->outstanding) }}</td>
                    <td class="py-2">{{ $inv->tgl_tempo?->format('d M Y') ?? '-' }}</td>
                    <td class="py-2 text-center">
                        @php $um = $inv->umur_hari; @endphp
                        @if($um !== null)
                            <span style="color:{{ $um < 0 ? '#dc2626' : ($um <= 7 ? '#d97706' : '#6b7280') }};font-weight:600">{{ $um }}</span>
                        @else - @endif
                    </td>
                    <td class="py-2"><span class="badge bg-{{ $inv->status_color }}">{{ $inv->status_label }}</span></td>
                </tr>
                @empty
                <tr><td colspan="13" class="text-center py-4 text-muted">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
    <div class="mt-3">{{ $invoices->links() }}</div>
    <p class="text-muted mt-2" style="font-size:11px">Outstanding = grand total invoice (termasuk PPN) dikurangi pembayaran masuk. Kolom "Hari" = sisa hari ke jatuh tempo (negatif = lewat tempo).</p>
</div></div>
@endsection
