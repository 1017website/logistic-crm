@extends('layouts.app')
@section('title', 'Surat Perintah Muat')
@section('page-title', $order->exists ? 'Edit Surat Perintah Muat' : 'Buat Surat Perintah Muat')
@section('page-subtitle', 'Sesuaikan isi surat, lalu simpan untuk membuat PDF dengan tanda tangan elektronik')
@section('content')
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('loading-orders.index') }}" class="btn btn-outline-secondary">Kembali ke daftar</a>
    @if($order->exists)
    <a href="{{ route('loading-orders.pdf', $order) }}" target="_blank" rel="noopener" class="btn btn-outline-primary">Pratinjau / Cetak</a>
    <a href="{{ route('loading-orders.pdf', [$order, 'download' => 1]) }}" class="btn btn-primary">Unduh PDF</a>
    @endif
</div>
@if($errors->any())<div class="alert alert-danger"><strong>Periksa kembali isian surat.</strong><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="post" action="{{ $order->exists ? route('loading-orders.update', $order) : route('loading-orders.store') }}">
@csrf
@if($order->exists) @method('PUT') @endif
<div class="card p-4 mb-3">
    <h5 class="mb-3">Informasi surat</h5>
    <p class="text-muted">Nomor surat: <strong>{{ $order->number ?: 'Otomatis setelah disimpan' }}</strong></p>
    <div class="row g-3">
        <div class="col-md-6"><label for="letter_date" class="form-label">Tanggal surat *</label><input id="letter_date" type="date" name="letter_date" class="form-control" value="{{ old('letter_date', $order->letter_date?->format('Y-m-d')) }}" required></div>
        @foreach(['city'=>'Kota penerbitan', 'subject'=>'Hal / Perihal', 'recipient'=>'Kepada Yth. / Perusahaan penerima'] as $field=>$label)
        <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }} *</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field, $order->$field) }}" required></div>
        @endforeach
        <div class="col-12"><label for="recipient_address" class="form-label">Alamat penerima (opsional)</label><textarea id="recipient_address" name="recipient_address" class="form-control" maxlength="1000" rows="2">{{ old('recipient_address', $order->recipient_address) }}</textarea></div>
    </div>
</div>
<div class="card p-4 mb-3">
    <h5 class="mb-3">Rincian muatan dan armada</h5><div class="row g-3">
    @foreach(['route'=>'Rute', 'po_number'=>'No. PO', 'mod_number'=>'No. MOD', 'driver_name'=>'Nama driver', 'vehicle_number'=>'Nomor polisi', 'driver_phone'=>'No. HP driver', 'vehicle_type'=>'Jenis armada'] as $field=>$label)
        @php($required = !in_array($field, ['po_number', 'mod_number', 'driver_phone']))
        <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }}{{ $required ? ' *' : ' (opsional)' }}</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field, $order->$field) }}" @required($required)></div>
    @endforeach
    </div>
</div>
<div class="card p-4 mb-3">
    <h5 class="mb-3">Isi surat</h5>
    @foreach(['opening'=>'Paragraf pembuka', 'terms'=>'Syarat dan ketentuan', 'closing'=>'Paragraf penutup'] as $field=>$label)
        <div class="mb-3"><label for="{{ $field }}" class="form-label">{{ $label }} *</label>
        @if($field === 'terms')<div class="form-text mb-2">Tulis satu ketentuan per baris. Nomor akan ditambahkan otomatis di PDF.</div>@endif
        <textarea id="{{ $field }}" name="{{ $field }}" class="form-control" rows="{{ $field === 'terms' ? 7 : 4 }}" maxlength="6000" required>{{ old($field, $order->$field) }}</textarea></div>
    @endforeach
</div>
<div class="card p-4 mb-3"><h5 class="mb-3">Tanda tangan elektronik</h5>
    <div class="row g-3">@foreach(['signatory_name'=>'Nama penandatangan', 'signatory_title'=>'Jabatan penandatangan'] as $field=>$label)
    <div class="col-md-6"><label for="{{ $field }}" class="form-label">{{ $label }} *</label><input id="{{ $field }}" name="{{ $field }}" class="form-control" maxlength="255" value="{{ old($field, $order->$field) }}" required></div>
    @endforeach</div>
    <p class="text-muted small mt-3 mb-0">PDF dilengkapi QR verifikasi sesuai nama di atas. Setelah isi surat diubah, unduh ulang PDF. QR versi sebelumnya tidak lagi berlaku. Pratinjau menampilkan data yang sudah disimpan.</p>
</div>
<button class="btn btn-primary" type="submit">Simpan Surat Perintah Muat</button>
</form>
@endsection
