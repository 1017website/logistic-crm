@extends('layouts.app')
@section('title', 'Surat Perintah Muat')
@section('page-title', $order->exists ? 'Edit Surat Perintah Muat' : 'Buat Surat Perintah Muat')
@section('page-subtitle', 'Lengkapi data surat, lalu ekspor sebagai PDF')
@section('content')
@if(!$order->exists)
<div class="d-flex align-items-center flex-wrap gap-2 mb-3" style="font-size:13px">
    <span>Template surat:</span>
    <a href="{{ route('loading-orders.create') }}" class="btn btn-sm {{ $order->language === 'en' ? 'btn-outline-secondary' : 'btn-primary' }}">Indonesia</a>
    <a href="{{ route('loading-orders.create', ['language' => 'en']) }}" class="btn btn-sm {{ $order->language === 'en' ? 'btn-primary' : 'btn-outline-secondary' }}">English</a>
    <span class="text-muted" style="font-size:11px">Pilih template sebelum mengisi surat.</span>
</div>
@endif
@if($errors->any())
<div class="alert alert-danger py-2" style="font-size:13px"><strong>Periksa kembali isian surat.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<form method="post" action="{{ $order->exists ? route('loading-orders.update', $order) : route('loading-orders.store') }}">
@csrf
<input type="hidden" name="language" value="{{ $order->language ?? 'id' }}">
@if($order->exists) @method('PUT') @endif
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <a href="{{ route('loading-orders.index') }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i> Kembali</a>
    <div class="d-flex gap-2 flex-wrap">
        @if($order->exists)
        <a href="{{ route('loading-orders.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="fas fa-print me-1"></i> Preview</a>
        <a href="{{ route('loading-orders.pdf', [$order, 'download' => 1]) }}" class="btn btn-outline-danger btn-sm"><i class="fas fa-file-pdf me-1"></i> Export PDF</a>
        @endif
        <button class="btn btn-primary btn-sm" type="submit"><i class="fas fa-save me-1"></i> Simpan Surat Perintah Muat</button>
    </div>
</div>
<div class="card mb-3">
    <div class="card-header bg-white py-3">
        <div style="font-size:14px;font-weight:700">Data Surat</div>
        <div style="font-size:11px;color:#6b7280">Penerima, tanggal, dan identitas surat perintah muat. Bahasa PDF: {{ $order->language === 'en' ? 'English' : 'Indonesia' }}.</div>
    </div>
    <div class="card-body"><div class="row g-3">
        <div class="col-md-4"><label class="form-label" for="number">Nomor Surat</label><input id="number" class="form-control" value="{{ $order->number ?: 'Otomatis setelah disimpan' }}" readonly></div>
        <div class="col-md-4"><label for="letter_date" class="form-label">Tanggal Surat <span class="text-danger">*</span></label><input id="letter_date" type="date" name="letter_date" class="form-control" value="{{ old('letter_date', $order->letter_date?->format('Y-m-d')) }}" required></div>
        <div class="col-md-4"><label for="city" class="form-label">Kota <span class="text-danger">*</span></label><input id="city" name="city" class="form-control" maxlength="255" value="{{ old('city', $order->city) }}" required></div>
        @foreach(['subject'=>'Hal / Perihal', 'recipient'=>'Kepada Yth. / Perusahaan Penerima'] as $field=>$label)
        <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }} <span class="text-danger">*</span></label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field, $order->$field) }}" required></div>
        @endforeach
        <div class="col-12"><label for="recipient_address" class="form-label">Alamat Penerima</label><textarea id="recipient_address" name="recipient_address" class="form-control" maxlength="1000" rows="2">{{ old('recipient_address', $order->recipient_address) }}</textarea></div>
        <div class="col-12"><label for="opening" class="form-label">Paragraf Pembuka <span class="text-danger">*</span></label><textarea id="opening" name="opening" class="form-control" maxlength="6000" rows="2" required>{{ old('opening', $order->opening) }}</textarea></div>
    </div></div>
</div>
<div class="card mb-3">
    <div class="card-header bg-white py-3"><div style="font-size:14px;font-weight:700">Rincian Muatan dan Armada</div><div style="font-size:11px;color:#6b7280">Rute, referensi muatan, driver, dan kendaraan.</div></div>
    <div class="card-body"><div class="row g-3">
    @foreach(['route'=>'Rute', 'po_number'=>'No. PO', 'mod_number'=>'No. MOD', 'driver_name'=>'Nama Driver', 'vehicle_number'=>'Nomor Polisi', 'driver_phone'=>'No. HP Driver', 'vehicle_type'=>'Jenis Armada'] as $field=>$label)
        @php($required = !in_array($field, ['po_number', 'mod_number', 'driver_phone']))
        <div class="col-md-4"><label for="{{ $field }}" class="form-label">{{ $label }} @if($required)<span class="text-danger">*</span>@endif</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field, $order->$field) }}" @required($required)></div>
    @endforeach
    </div></div>
</div>
<div class="card mb-3">
    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div><div style="font-size:14px;font-weight:700">Syarat dan Ketentuan</div><div style="font-size:11px;color:#6b7280">Urutan di bawah sama dengan urutan daftar bernomor pada PDF.</div></div>
        <button type="button" class="btn btn-outline-primary btn-sm" id="addTerm"><i class="fas fa-plus me-1"></i> Tambah Syarat</button>
    </div>
    <div class="card-body" id="termsList">
        @foreach(old('term_items', preg_split('/\R/u', old('terms', $order->terms))) as $term)
        <div class="d-flex align-items-start gap-2 mb-2 term-row">
            <span class="badge bg-dark mt-2 term-number">{{ $loop->iteration }}</span>
            <input name="term_items[]" class="form-control" value="{{ $term }}" maxlength="6000" required aria-label="Syarat {{ $loop->iteration }}">
            <button type="button" class="btn btn-outline-danger btn-sm remove-term" aria-label="Hapus syarat"><i class="fas fa-times"></i></button>
        </div>
        @endforeach
    </div>
</div>
<div class="card mb-3">
    <div class="card-header bg-white py-3"><div style="font-size:14px;font-weight:700">Penutup dan Tanda Tangan</div><div style="font-size:11px;color:#6b7280">Nama dan jabatan otomatis mengikuti akun pembuat surat.</div></div>
    <div class="card-body"><div class="row g-3">
        <div class="col-12"><label for="closing" class="form-label">Kalimat Penutup <span class="text-danger">*</span></label><textarea id="closing" name="closing" class="form-control" rows="2" maxlength="6000" required>{{ old('closing', $order->closing) }}</textarea></div>
        @foreach(['signatory_name'=>'Nama Penandatangan', 'signatory_title'=>'Jabatan'] as $field=>$label)
        <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}</label><input id="{{ $field }}" class="form-control" value="{{ $order->$field }}" readonly></div>
        @endforeach
        <div class="col-12" style="font-size:11px;color:#6b7280">PDF dilengkapi QR verifikasi. Setelah surat diubah, simpan dan unduh ulang PDF untuk menggunakan versi terbaru.</div>
    </div></div>
</div>
<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('loading-orders.index') }}" class="btn btn-outline-secondary">Batal</a>
    <button class="btn btn-primary" type="submit"><i class="fas fa-save me-1"></i> Simpan Surat Perintah Muat</button>
</div>
</form>
@endsection
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const list = document.getElementById('termsList');
    function renumber() {
        list.querySelectorAll('.term-row').forEach((row, i) => {
            row.querySelector('.term-number').textContent = i + 1;
            row.querySelector('input').setAttribute('aria-label', 'Syarat ' + (i + 1));
            row.querySelector('.remove-term').disabled = list.children.length === 1;
        });
    }
    document.getElementById('addTerm').addEventListener('click', function () {
        const row = list.querySelector('.term-row').cloneNode(true);
        row.querySelector('input').value = '';
        list.appendChild(row);
        renumber();
        row.querySelector('input').focus();
    });
    list.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-term');
        if (button && list.children.length > 1) {
            button.closest('.term-row').remove();
            renumber();
        }
    });
    renumber();
});
</script>
@endpush
