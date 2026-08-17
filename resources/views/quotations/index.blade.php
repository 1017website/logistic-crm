@extends('layouts.app')
@section('title', 'Penawaran')
@section('page-title', 'Penawaran')
@section('page-subtitle', 'Buat, kelola, dan ekspor surat penawaran harga dalam format PDF')

@section('content')
<div class="row g-3">
    <div class="col-12">
        @if(session('success'))
            <div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <div>
                <div style="font-size:14px;font-weight:700;color:#111827">Daftar Surat Penawaran</div>
                <div style="font-size:12px;color:#6b7280">Dokumen tersimpan sebagai data CRM; hasil akhirnya hanya PDF.</div>
            </div>
            <a href="{{ route('quotations.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus me-1"></i> Buat Penawaran
            </a>
        </div>

        <form method="GET" action="{{ route('quotations.index') }}">
            <div class="card mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select form-select-sm">
                                <option value="all">Semua Status</option>
                                @foreach(\App\Models\Quotation::STATUSES as $key => $label)
                                    <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label">Pencarian</label>
                            <input type="text" name="search" class="form-control form-control-sm"
                                value="{{ $search }}" placeholder="Cari nomor, customer, atau PIC...">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary btn-sm w-100"><i class="fas fa-search me-1"></i> Cari</button>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size:13px">
                        <thead style="background:#f8f9fa">
                            <tr>
                                <th class="px-3 py-2">No. Penawaran</th>
                                <th class="py-2">Tanggal</th>
                                <th class="py-2">Customer</th>
                                <th class="py-2">Rute</th>
                                <th class="py-2">Pembuat</th>
                                <th class="py-2">Status</th>
                                <th class="py-2 text-end pe-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($quotations as $quotation)
                                <tr>
                                    <td class="px-3 py-2">
                                        <span style="font-weight:700;color:#111827">{{ $quotation->quotation_number }}</span>
                                        <br><span class="text-muted" style="font-size:11px">{{ $quotation->items_count }} baris tarif</span>
                                    </td>
                                    <td>{{ $quotation->quotation_date?->format('d M Y') }}</td>
                                    <td>
                                        <span style="font-weight:600">{{ $quotation->company_name }}</span>
                                        @if($quotation->contact_name)
                                            <br><span class="text-muted" style="font-size:11px">PIC: {{ $quotation->contact_name }}</span>
                                        @endif
                                    </td>
                                    <td style="font-size:12px">
                                        @php $firstItem = $quotation->items->first(); @endphp
                                        @if($firstItem)
                                            {{ $firstItem->origin }} <i class="fas fa-arrow-right mx-1 text-muted"></i> {{ $firstItem->destination }}
                                            @if($quotation->items_count > 1)<span class="text-muted"> +{{ $quotation->items_count - 1 }}</span>@endif
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>{{ $quotation->user?->name ?? '-' }}</td>
                                    <td><span class="badge bg-{{ $quotation->status_color }}">{{ $quotation->status_label }}</span></td>
                                    <td class="text-end pe-3 text-nowrap">
                                        <a href="{{ route('quotations.show', $quotation) }}" class="btn btn-sm btn-outline-dark"
                                            style="padding:4px 8px" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('quotations.pdf', $quotation) }}" class="btn btn-sm btn-primary"
                                            style="padding:4px 9px" title="Unduh PDF">
                                            <i class="fas fa-file-pdf me-1"></i> PDF
                                        </a>
                                        <a href="{{ route('quotations.edit', $quotation) }}" class="btn btn-sm btn-outline-secondary"
                                            style="padding:4px 8px" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fas fa-file-signature d-block mb-2" style="font-size:28px;color:#d1d5db"></i>
                                        Belum ada surat penawaran.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="mt-3">{{ $quotations->links() }}</div>
    </div>
</div>
@endsection
