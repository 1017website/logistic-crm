@extends('layouts.app')
@section('title', 'Delivery Orders')
@section('page-title', 'Delivery Orders')
@section('page-subtitle', 'DO final: surat jalan, pickup, delivery, POD, dan finance')

@section('content')
<div class="row g-3">
    <div class="col-12">

        {{-- Header + KPI --}}
        <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
            <a href="{{ route('delivery-orders.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-download me-1"></i> Export Excel
            </a>
            <div class="d-flex gap-3 flex-wrap">
                {{-- KPI di sini memakai biaya aktual DO final; Laporan DO memisahkan
                     HPP Rencana & HPP Aktual, jadi labelnya dibuat eksplisit. --}}
                @foreach([[$volumeDo, 'Volume DO', '#111'], [$revenue, 'Revenue', '#111'], [$totalCost, 'HPP Aktual', '#111'], [$grossProfit, 'Gross Profit (Aktual)', '#10b981']] as $s)
                <div class="text-center {{ !$loop->first ? 'ps-3' : '' }}" style="{{ !$loop->first ? 'border-left:1px solid var(--border-color)' : '' }}">
                    <div style="font-size:{{ $loop->index >= 1 ? '1rem' : '1.2rem' }};font-weight:800;color:{{ $s[2] }}">
                        {{ $loop->index >= 1 ? idr($s[0]) : $s[0] }}
                    </div>
                    <div style="font-size:.68rem;color:var(--text-muted)">{{ $s[1] }}</div>
                </div>
                @endforeach
            </div>
        </div>

        {{-- Filter --}}
        <form method="GET" action="{{ route('delivery-orders.index') }}">
            <div class="card mb-3"><div class="card-body p-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-2"><input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}"></div>
                    <div class="col-md-2"><input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}"></div>
                    <div class="col-md-3">
                        <select name="status" class="form-select form-select-sm">
                            <option value="all">Semua Tahap</option>
                            @foreach($flowOptions as $key => $label)
                            <option value="{{ $key }}" @selected($status == $key)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><input type="text" name="search" class="form-control form-control-sm" placeholder="Cari DO, customer, armada..." value="{{ $search }}"></div>
                    <div class="col-md-2"><button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-search"></i></button></div>
                </div>
            </div></div>
        </form>

        {{-- Tabel --}}
        <div class="card"><div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size:13px">
                    <thead style="background:#f8f9fa"><tr>
                        <th class="px-3 py-2">No. DO</th>
                        <th class="py-2">Request DO</th>
                        <th class="py-2">Customer</th>
                        <th class="py-2">Armada / Vendor</th>
                        <th class="py-2">Rute</th>
                        <th class="py-2">Tahap</th>
                        <th class="py-2">Revenue</th>
                        <th class="py-2">Tgl DO</th>
                        <th class="py-2"></th>
                    </tr></thead>
                    <tbody>
                        @forelse($dos as $do)
                        <tr>
                            <td class="px-3 py-2" style="font-weight:700;color:var(--primary)">{{ $do->do_number }}</td>
                            <td class="py-2" style="font-size:12px;color:#6b7280">{{ $do->requestOrder?->do_number ?? '-' }}</td>
                            <td class="py-2">{{ $do->customer?->company_name ?? '-' }}</td>
                            <td class="py-2" style="font-size:12px">
                                {{ $do->fleet_info ?? ($do->vendor?->vendor_name ?? '-') }}
                                <br><span class="badge bg-light text-dark border" style="font-size:9px">{{ $do->assignment_type === 'internal' ? 'Internal' : 'Eksternal' }}</span>
                            </td>
                            <td class="py-2" style="font-size:11px;color:#6b7280">{{ $do->origin ?? '?' }} &rarr; {{ $do->destination ?? '?' }}</td>
                            <td class="py-2"><span class="badge bg-{{ $do->flow_color }}">{{ $do->flow_label }}</span></td>
                            <td class="py-2" style="font-weight:600;color:var(--primary);white-space:nowrap">{{ idr($do->total_revenue) }}</td>
                            <td class="py-2" style="font-size:12px;color:#6b7280">{{ $do->do_date?->format('d M Y') }}</td>
                            <td class="py-2">
                                <a href="{{ route('delivery-orders.show', $do->id) }}" class="btn btn-sm btn-outline-primary" style="padding:3px 7px" title="Detail & Aksi">
                                    <i class="fas fa-stream"></i>
                                </a>
                                @include('components.delete-request-button', [
                                    'module'  => 'delivery-orders',
                                    'id'      => $do->id,
                                    'label'   => $do->do_number,
                                    'pending' => in_array($do->id, $pendingDeletionDoIds ?? []),
                                ])
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center py-4 text-muted">Belum ada Delivery Order pada periode ini.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>

        <div class="mt-3">{{ $dos->links() }}</div>
    </div>
</div>
@endsection
