@extends('layouts.app')
@section('title', 'Laporan Outstanding')
@section('page-title', 'Laporan Outstanding')
@section('page-subtitle', 'Piutang belum terbayar & umur tagihan')

@section('content')
@php
    $bucketStyle = [
        'current' => ['#6b7280', '#f3f4f6'],
        '1_30'    => ['#d97706', '#fef3c7'],
        '31_60'   => ['#ea580c', '#ffedd5'],
        '61_90'   => ['#dc2626', '#fee2e2'],
        '90_plus' => ['#991b1b', '#fecaca'],
    ];
@endphp
<div class="row g-3"><div class="col-12">

    <a href="{{ route('logistic-reports.index') }}" class="btn btn-sm btn-outline-secondary mb-3"><i class="fas fa-arrow-left me-1"></i> Kembali</a>

    {{-- Filter --}}
    <form method="GET" action="{{ route('logistic-reports.outstanding') }}">
        <div class="card mb-3"><div class="card-body p-3"><div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label" style="font-size:12px">Client</label>
                <select name="customer_id" class="form-select form-select-sm">
                    <option value="">Semua Client</option>
                    @foreach($customers as $c)<option value="{{ $c->id }}" @selected($customerId == $c->id)>{{ $c->company_name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Umur Piutang</label>
                <select name="aging" class="form-select form-select-sm">
                    <option value="all" @selected($aging=='all')>Semua</option>
                    <option value="overdue" @selected($aging=='overdue')>Lewat Jatuh Tempo</option>
                    @foreach($buckets as $key => $label)
                        <option value="{{ $key }}" @selected($aging==$key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">S/D Tgl Invoice</label>
                <input type="date" name="sd_tanggal" class="form-control form-control-sm" value="{{ $sdTanggal }}">
            </div>
            <div class="col-md-2">
                <label class="form-label" style="font-size:12px">Cari</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="No invoice / client" value="{{ $search }}">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary btn-sm flex-fill"><i class="fas fa-search me-1"></i> Filter</button>
                <a href="{{ route('logistic-reports.outstanding.export', request()->query()) }}" class="btn btn-success btn-sm flex-fill"><i class="fas fa-download me-1"></i> Excel</a>
            </div>
        </div></div></div>
    </form>

    {{-- Ringkasan --}}
    <div class="d-flex gap-3 mb-3 flex-wrap" style="font-size:13px">
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Jml Invoice</div><div style="font-weight:800">{{ $totalCount }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Jml Client</div><div style="font-weight:800">{{ $totalClient }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Total Tagihan</div><div style="font-weight:800;color:var(--primary)">{{ idr($totalTagihan) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Sudah Terbayar</div><div style="font-weight:800;color:#16a34a">{{ idr($totalPaid) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Outstanding</div><div style="font-weight:800;color:#dc2626">{{ idr($totalOutstanding) }}</div></div></div>
        <div class="card flex-fill"><div class="card-body py-2 text-center"><div class="text-muted" style="font-size:11px">Lewat Jatuh Tempo</div><div style="font-weight:800;color:#991b1b">{{ idr($totalOverdue) }}</div></div></div>
    </div>

    {{-- Belum ditagih: sengaja di luar aging agar tidak tercampur piutang resmi --}}
    <div class="card mb-3" style="border-left:4px solid #6366f1">
        <div class="card-body p-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <div style="font-weight:700;font-size:13px"><i class="fas fa-file-invoice me-2" style="color:#6366f1"></i>Belum Ditagih (Draft Invoice)</div>
                <div class="text-muted" style="font-size:11px">
                    Pekerjaan selesai yang tagihannya belum diterbitkan. <b>Tidak dihitung</b> sebagai piutang di kartu mana pun di atas.
                </div>
            </div>
            <div class="d-flex gap-4 text-end">
                <div>
                    <div class="text-muted" style="font-size:11px">Jml Draft</div>
                    <div style="font-weight:800">{{ $draftSummary['count'] }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:11px">Jml Client</div>
                    <div style="font-weight:800">{{ $draftSummary['clients'] }}</div>
                </div>
                <div>
                    <div class="text-muted" style="font-size:11px">Nilai Belum Ditagih</div>
                    <div style="font-weight:800;color:#4f46e5">{{ idr($draftSummary['amount']) }}</div>
                </div>
                <div class="align-self-center">
                    <a href="{{ route('invoices.index', ['tab' => 'draft']) }}" class="btn btn-sm btn-outline-primary">Buka Draft</a>
                </div>
            </div>
        </div>
    </div>

    {{-- Bucket umur piutang (klik untuk drill-down) --}}
    @php $bucketTotal = collect($bucketSummary)->sum('amount'); @endphp
    <div class="card mb-3"><div class="card-body p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <div style="font-weight:700;font-size:13px">Umur Piutang (Aging)</div>
            <div class="text-muted" style="font-size:11px">Klik kartu untuk memfilter</div>
        </div>
        <div class="row g-2">
            @foreach($buckets as $key => $label)
                @php
                    [$fg, $bg] = $bucketStyle[$key] ?? ['#6b7280', '#f3f4f6'];
                    $b = $bucketSummary[$key];
                    $pct = $bucketTotal > 0 ? round($b['amount'] / $bucketTotal * 100) : 0;
                    $active = $aging === $key;
                @endphp
                <div class="col-md col-6">
                    <a href="{{ route('logistic-reports.outstanding', array_merge(request()->query(), ['aging' => $active ? 'all' : $key, 'page' => null])) }}" class="text-decoration-none">
                        <div style="border-radius:10px;padding:10px 12px;background:{{ $bg }};border:2px solid {{ $active ? $fg : 'transparent' }}">
                            <div style="font-size:11px;font-weight:600;color:{{ $fg }}">{{ $label }}</div>
                            <div style="font-weight:800;color:{{ $fg }};font-size:14px">{{ idr($b['amount']) }}</div>
                            <div style="font-size:10px;color:{{ $fg }};opacity:.75">{{ $b['count'] }} invoice &middot; {{ $pct }}%</div>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div></div>

    {{-- Rekap per client --}}
    <div class="card mb-3"><div class="card-body p-0">
        <div class="px-3 py-2" style="font-weight:700;font-size:13px;border-bottom:1px solid #e5e7eb">Rekap Outstanding per Client</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:12px">
                <thead style="background:#f8f9fa"><tr>
                    <th class="px-3 py-2">Client</th><th class="py-2 text-center">Jml Inv</th>
                    <th class="py-2 text-end">Total Tagihan</th><th class="py-2 text-end">Terbayar</th>
                    <th class="py-2 text-end">Outstanding</th><th class="py-2 text-end">Lewat Tempo</th>
                    <th class="py-2 text-center">Tunggakan Terlama</th>
                </tr></thead>
                <tbody>
                    @forelse($perCustomer as $pc)
                    <tr>
                        <td class="px-3 py-2" style="font-weight:600">{{ $pc['customer'] }}</td>
                        <td class="py-2 text-center">{{ $pc['count'] }}</td>
                        <td class="py-2 text-end">{{ idr($pc['grand_total']) }}</td>
                        <td class="py-2 text-end text-success">{{ idr($pc['paid']) }}</td>
                        <td class="py-2 text-end text-danger" style="font-weight:700">{{ idr($pc['outstanding']) }}</td>
                        <td class="py-2 text-end" style="color:#991b1b">{{ idr($pc['overdue']) }}</td>
                        <td class="py-2 text-center">
                            @if($pc['oldest'] !== null && $pc['oldest'] > 0)
                                <span style="color:#dc2626;font-weight:600">{{ $pc['oldest'] }} hari</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center py-4 text-muted">Tidak ada piutang.</td></tr>
                    @endforelse
                </tbody>
                @if($perCustomer->isNotEmpty())
                <tfoot style="background:#f8f9fa;font-weight:800">
                    <tr>
                        <td class="px-3 py-2">TOTAL</td>
                        <td class="py-2 text-center">{{ $totalCount }}</td>
                        <td class="py-2 text-end">{{ idr($totalTagihan) }}</td>
                        <td class="py-2 text-end text-success">{{ idr($totalPaid) }}</td>
                        <td class="py-2 text-end text-danger">{{ idr($totalOutstanding) }}</td>
                        <td class="py-2 text-end" style="color:#991b1b">{{ idr($totalOverdue) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div></div>

    {{-- Detail invoice --}}
    <div class="card"><div class="card-body p-0">
        <div class="px-3 py-2" style="font-weight:700;font-size:13px;border-bottom:1px solid #e5e7eb">Detail Invoice Belum Terbayar</div>
        <div class="table-responsive">
            <table class="table table-hover mb-0" style="font-size:12px">
                <thead style="background:#f8f9fa"><tr>
                    <th class="px-3 py-2">No Urut Inv</th><th class="py-2">No Invoice</th><th class="py-2">Customer</th>
                    <th class="py-2">Tgl Invoice</th><th class="py-2">Due Date</th><th class="py-2 text-center">Lewat Tempo</th>
                    <th class="py-2">Kategori Umur</th><th class="py-2 text-end">Grand Total</th>
                    <th class="py-2 text-end">Terbayar</th><th class="py-2 text-end">Outstanding</th><th class="py-2">Status</th>
                </tr></thead>
                <tbody>
                    @forelse($rows as $r)
                    @php
                        $inv = $r['invoice'];
                        [$fg, $bg] = $bucketStyle[$r['bucket']] ?? ['#6b7280', '#f3f4f6'];
                        $od = $r['days_overdue'];
                    @endphp
                    <tr>
                        <td class="px-3 py-2">
                            <a href="{{ route('invoices.show', $inv) }}" style="font-weight:700;color:var(--primary);text-decoration:none">{{ $inv->invoice_id }}</a>
                        </td>
                        <td class="py-2" style="font-size:11px">{{ $inv->invoice_number }}</td>
                        <td class="py-2">{{ $inv->customer?->company_name ?? '-' }}</td>
                        <td class="py-2">{{ $inv->tgl_buat?->format('d M Y') ?? '-' }}</td>
                        <td class="py-2">{{ $inv->tgl_tempo?->format('d M Y') ?? '-' }}</td>
                        <td class="py-2 text-center">
                            @if($od === null)
                                <span class="text-muted">-</span>
                            @elseif($od > 0)
                                <span style="color:#dc2626;font-weight:700">+{{ $od }}</span>
                            @else
                                <span class="text-muted">{{ $od }}</span>
                            @endif
                        </td>
                        <td class="py-2"><span style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:{{ $bg }};color:{{ $fg }}">{{ $buckets[$r['bucket']] }}</span></td>
                        <td class="py-2 text-end">{{ idr($r['grand_total']) }}</td>
                        <td class="py-2 text-end text-success">{{ idr($r['paid']) }}</td>
                        <td class="py-2 text-end text-danger" style="font-weight:700">{{ idr($r['outstanding']) }}</td>
                        <td class="py-2"><span class="badge bg-{{ $inv->status_color }}">{{ $inv->status_label }}</span></td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center py-4 text-muted">Tidak ada piutang belum terbayar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div></div>
    <div class="mt-3">{{ $rows->links() }}</div>
    <p class="text-muted mt-2" style="font-size:11px">
        Outstanding = grand total invoice (termasuk PPN) dikurangi pembayaran yang sudah masuk. Hanya invoice berstatus
        <b>Invoice</b> &amp; <b>Termin</b> yang masih menyisakan saldo; draft dan invoice lunas tidak dihitung.
        Kolom "Lewat Tempo" positif = jumlah hari melewati jatuh tempo. Invoice tanpa due date dihitung sebagai belum jatuh tempo.
        Kartu aging selalu menampilkan seluruh bucket sesuai filter client &amp; tanggal.
        Kartu "Belum Ditagih" berisi draft invoice — nilainya belum menjadi piutang dan tidak masuk export.
    </p>
</div></div>
@endsection
