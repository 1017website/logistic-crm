@extends('layouts.app')
@section('title', 'Detail Delivery Order')
@section('page-title', $deliveryOrder->do_number)
@section('page-subtitle', 'Alur lapangan: surat jalan, pickup, delivery, POD, tutup & finance')

@section('content')
@php $u = auth()->user(); $do = $deliveryOrder; @endphp
<div class="row g-3">
    {{-- ─────────── Kiri: info, item, dokumen, timeline ─────────── --}}
    <div class="col-lg-7">
        <div class="card mb-3"><div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-1" style="font-weight:800">{{ $do->do_number }}</h5>
                    <span class="badge bg-{{ $do->flow_color }}">{{ $do->flow_label }}</span>
                    <span class="badge {{ $do->invoice_status === 'paid' ? 'bg-success' : ($do->invoice_status === 'uninvoiced' ? 'bg-light text-dark border' : 'bg-warning text-dark') }}">
                        {{ $do->invoice_status_label }}
                    </span>
                    @if($do->requestOrder)
                    <a href="{{ route('request-orders.show', $do->requestOrder->id) }}" class="badge bg-light text-dark border text-decoration-none">
                        <i class="fas fa-link me-1"></i>{{ $do->requestOrder->do_number }}
                    </a>
                    @endif
                </div>
                <a href="{{ route('delivery-orders.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
            </div>

            <div class="row g-2" style="font-size:13px">
                <div class="col-md-6"><span class="text-muted">Customer</span><br><b>{{ $do->customer?->company_name ?? '-' }}</b></div>
                <div class="col-md-6"><span class="text-muted">Jenis Armada</span><br><b>{{ $do->assignment_type === 'internal' ? 'Armada Internal' : 'Vendor Eksternal' }}</b></div>
                <div class="col-md-6"><span class="text-muted">Armada / Vendor</span><br>{{ $do->fleet_info ?? ($do->vendor?->vendor_name ?? '-') }}</div>
                <div class="col-md-6"><span class="text-muted">Driver</span><br>{{ $do->driver_name ?? '-' }} {{ $do->driver_phone ? '('.$do->driver_phone.')' : '' }}</div>
                <div class="col-md-6"><span class="text-muted">Origin</span><br>{{ $do->origin ?? '-' }}</div>
                <div class="col-md-6"><span class="text-muted">Destination</span><br>{{ $do->destination ?? '-' }}</div>
                <div class="col-md-4"><span class="text-muted">Tgl DO</span><br>{{ $do->do_date?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-4"><span class="text-muted">Pickup</span><br>{{ $do->pickup_date?->format('d M Y') ?? '-' }}</div>
                <div class="col-md-4"><span class="text-muted">Delivery</span><br>{{ $do->delivery_date?->format('d M Y') ?? '-' }}</div>
            </div>
        </div></div>

        {{-- Item layanan (dari request order) --}}
        <div class="card mb-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Item Layanan</h6>
            <table class="table table-sm mb-0" style="font-size:12px">
                <thead><tr><th>Layanan</th><th class="text-end">Qty</th><th class="text-end">Jual</th><th class="text-end">Subtotal</th></tr></thead>
                <tbody>
                    @forelse($do->items as $it)
                    <tr>
                        <td>{{ $it->service_name }} <span class="text-muted">{{ $it->unit }}</span></td>
                        <td class="text-end">{{ rtrim(rtrim(number_format($it->qty,3),'0'),'.') }}</td>
                        <td class="text-end">{{ idr($it->sell_price) }}</td>
                        <td class="text-end">{{ idr($it->subtotal_revenue) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="text-muted">Tidak ada item.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr style="font-weight:700"><td colspan="3" class="text-end">Total Revenue</td><td class="text-end" style="color:var(--primary)">{{ idr($do->total_revenue) }}</td></tr>
                    <tr><td colspan="3" class="text-end text-muted">Biaya Aktual + Lain</td><td class="text-end" style="color:#dc2626">{{ idr($do->total_cost) }}</td></tr>
                    <tr style="font-weight:700"><td colspan="3" class="text-end">Gross Profit</td><td class="text-end" style="color:#10b981">{{ idr($do->gross_profit) }}</td></tr>
                </tfoot>
            </table>
        </div></div>

        {{-- Dokumen --}}
        <div class="card mb-3"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Dokumen</h6>
            <div class="d-flex gap-2 flex-wrap" style="font-size:13px">
                @if($do->surat_jalan_file)
                <a href="{{ asset('storage/'.$do->surat_jalan_file) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-alt me-1"></i> Surat Jalan</a>
                @else <span class="text-muted">Surat jalan belum diunggah.</span> @endif
                @if($do->assignment_type === 'internal')
                <a href="{{ route('delivery-orders.surat-jalan.print', $do->id) }}" target="_blank" class="btn btn-sm btn-outline-dark"><i class="fas fa-barcode me-1"></i> Cetak SJ Internal</a>
                @endif
                @if($do->pod_file)
                <a href="{{ asset('storage/'.$do->pod_file) }}" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-image me-1"></i> POD</a>
                @endif
            </div>
        </div></div>

        {{-- Timeline --}}
        <div class="card"><div class="card-body p-3">
            <h6 style="font-weight:700;font-size:13px;text-transform:uppercase;color:#6b7280">Riwayat Alur</h6>
            <ul class="list-unstyled mb-0" style="font-size:12px">
                @forelse($do->statusLogs as $log)
                <li class="d-flex gap-2 mb-2 pb-2 border-bottom">
                    <i class="fas fa-circle mt-1" style="font-size:7px;color:#3b82f6"></i>
                    <div>
                        <b>{{ \App\Models\DeliveryOrder::FLOW[$log->to_status] ?? $log->to_status }}</b>
                        @if($log->from_status)<span class="text-muted">(dari {{ \App\Models\DeliveryOrder::FLOW[$log->from_status] ?? $log->from_status }})</span>@endif
                        <br><span class="text-muted">{{ $log->user?->name ?? 'Sistem' }} · {{ $log->created_at->format('d M Y H:i') }}</span>
                        @if($log->note)<br><span style="color:#374151">{{ $log->note }}</span>@endif
                    </div>
                </li>
                @empty
                <li class="text-muted">Belum ada riwayat.</li>
                @endforelse
            </ul>
        </div></div>
    </div>

    {{-- ─────────── Kanan: panel aksi sesuai tahap ─────────── --}}
    <div class="col-lg-5">
        @if(session('success'))<div class="alert alert-success py-2" style="font-size:13px">{{ session('success') }}</div>@endif
        @foreach($errors->all() as $e)<div class="alert alert-danger py-2" style="font-size:13px">{{ $e }}</div>@endforeach

        {{-- SURAT JALAN (Sales Admin) --}}
        @if($do->status === 'surat_jalan' && $u->canAccess('pod_field'))
        <div class="card mb-3 border-secondary"><div class="card-body p-3">
            <h6 style="font-weight:700"><i class="fas fa-file-signature me-1"></i> Terbitkan Surat Jalan</h6>
            <form method="POST" action="{{ route('delivery-orders.surat-jalan', $do->id) }}" enctype="multipart/form-data">
                @csrf
                <label class="form-label" style="font-size:12px">Upload Surat Jalan (PDF/gambar, maks 5MB)</label>
                <input type="file" name="surat_jalan_file" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png" required>
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-upload me-1"></i> Unggah & Lanjut Pickup</button>
            </form>
        </div></div>
        @endif

        {{-- PICKUP (Sales Admin) --}}
        @if($do->status === 'pickup' && $u->canAccess('pod_field'))
        <div class="card mb-3 border-primary"><div class="card-body p-3">
            <h6 style="font-weight:700"><i class="fas fa-box me-1 text-primary"></i> Konfirmasi Pickup</h6>
            <form method="POST" action="{{ route('delivery-orders.pickup', $do->id) }}">
                @csrf
                <label class="form-label" style="font-size:12px">Tanggal Pickup</label>
                <input type="date" name="pickup_date" class="form-control form-control-sm mb-2" value="{{ now()->toDateString() }}">
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-truck me-1"></i> Barang Dipickup</button>
            </form>
        </div></div>
        @endif

        {{-- DELIVERY (Sales Admin) --}}
        @if($do->status === 'in_delivery' && $u->canAccess('pod_field'))
        <div class="card mb-3 border-warning"><div class="card-body p-3">
            <h6 style="font-weight:700"><i class="fas fa-route me-1 text-warning"></i> Konfirmasi Sampai Tujuan</h6>
            <form method="POST" action="{{ route('delivery-orders.delivered', $do->id) }}">
                @csrf
                <label class="form-label" style="font-size:12px">Tanggal Sampai</label>
                <input type="date" name="delivery_date" class="form-control form-control-sm mb-2" value="{{ now()->toDateString() }}">
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-flag-checkered me-1"></i> Sampai Tujuan</button>
            </form>
        </div></div>
        @endif

        {{-- POD upload (Sales Admin) --}}
        @if($do->status === 'pod' && $u->canAccess('pod_field'))
        <div class="card mb-3" style="border-color:#7c3aed"><div class="card-body p-3">
            <h6 style="font-weight:700"><i class="fas fa-camera me-1" style="color:#7c3aed"></i> Upload POD (Bukti Terima)</h6>
            <form method="POST" action="{{ route('delivery-orders.pod', $do->id) }}" enctype="multipart/form-data">
                @csrf
                <label class="form-label" style="font-size:12px">Foto/PDF POD (maks 5MB)</label>
                <input type="file" name="pod_file" class="form-control form-control-sm mb-2" accept=".pdf,.jpg,.jpeg,.png" required>
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
                <button class="btn btn-primary btn-sm w-100"><i class="fas fa-upload me-1"></i> Unggah POD</button>
            </form>
        </div></div>
        @endif

        {{-- VERIFIKASI POD + INPUT BIAYA + TUTUP (Sales Admin) --}}
        @if($do->status === 'verifikasi_pod' && $u->canAccess('pod_field'))
        <div class="card mb-3 border-success"><div class="card-body p-3">
            <h6 style="font-weight:700"><i class="fas fa-clipboard-check me-1 text-success"></i> Verifikasi POD & Tutup DO</h6>
            @if($do->pod_file)
            <a href="{{ asset('storage/'.$do->pod_file) }}" target="_blank" class="d-block mb-2" style="font-size:12px"><i class="fas fa-eye me-1"></i> Lihat POD</a>
            @endif
            <form method="POST" action="{{ route('delivery-orders.close', $do->id) }}">
                @csrf
                <label class="form-label" style="font-size:12px">Biaya Aktual (vendor/operasional)</label>
                <input type="number" name="actual_cost" class="form-control form-control-sm mb-2" min="0" value="{{ (int) $do->actual_cost }}" required>
                <label class="form-label" style="font-size:12px">Biaya Lain</label>
                <input type="number" name="other_cost" class="form-control form-control-sm mb-2" min="0" value="{{ (int) $do->other_cost }}">
                <textarea name="note" class="form-control form-control-sm mb-2" rows="2" placeholder="Catatan (opsional)"></textarea>
                <button class="btn btn-success btn-sm w-100" onclick="return confirm('Verifikasi POD, simpan biaya, dan tutup DO?')"><i class="fas fa-lock me-1"></i> Verifikasi & Tutup DO</button>
            </form>
        </div></div>
        @endif

        {{-- FINANCE terpusat di menu Invoice --}}
        @if($do->pod_at && $u->canAccess('finance'))
        <div class="card mb-3" style="border-color:#d97706"><div class="card-body p-3">
            <h6 style="font-weight:700"><i class="fas fa-file-invoice-dollar me-1" style="color:#d97706"></i> Penagihan</h6>
            <p class="mb-2" style="font-size:12px">
                Status: <b>{{ $do->invoice_status_label }}</b>.
                Invoice tersedia sejak POD diterima dan pembayaran dikelola dari satu tempat.
            </p>
            @if($do->invoiceItems->isNotEmpty())
                <div class="mb-2" style="font-size:12px">
                    @foreach($do->invoiceItems->unique('invoice_id') as $invoiceItem)
                        @if($invoiceItem->invoice)
                        <a href="{{ route('invoices.show', $invoiceItem->invoice->id) }}" class="badge bg-light text-dark border text-decoration-none me-1">
                            {{ $invoiceItem->invoice->invoice_number }}
                        </a>
                        @endif
                    @endforeach
                </div>
            @endif
            <a href="{{ route('invoices.index', ['customer_id' => $do->customer_id]) }}" class="btn btn-warning btn-sm w-100">
                <i class="fas fa-receipt me-1"></i> Buka Menu Invoice
            </a>
        </div></div>
        @endif

        {{-- SELESAI --}}
        @if($do->status === 'paid')
        <div class="alert alert-success" style="font-size:13px"><i class="fas fa-check-circle me-1"></i> Alur selesai. DO lunas.</div>
        @endif

        {{-- Info read-only kalau user tak punya aksi di tahap ini --}}
        @if(!in_array($do->status, ['paid']) && (
            ($do->status === 'surat_jalan' && !$u->canAccess('pod_field')) ||
            ($do->status === 'pickup' && !$u->canAccess('pod_field')) ||
            ($do->status === 'in_delivery' && !$u->canAccess('pod_field')) ||
            ($do->status === 'pod' && !$u->canAccess('pod_field')) ||
            ($do->status === 'verifikasi_pod' && !$u->canAccess('pod_field'))
        ))
        <div class="alert alert-info" style="font-size:13px">DO sedang di tahap <b>{{ $do->flow_label }}</b>. Menunggu tindakan dari role terkait.</div>
        @endif
    </div>
</div>
@endsection
