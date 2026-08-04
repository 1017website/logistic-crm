@extends('layouts.app')
@section('title', 'Buat Penawaran')
@section('page-title', 'Buat Penawaran')
@section('page-subtitle', 'Lengkapi data surat dan tarif, lalu ekspor sebagai PDF')

@section('content')
    @include('quotations.form', [
        'formAction' => route('quotations.store'),
        'formMethod' => 'POST',
        'submitLabel' => 'Simpan Penawaran',
    ])
@endsection
