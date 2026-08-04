@extends('layouts.app')
@section('title', 'Edit Penawaran')
@section('page-title', 'Edit Penawaran')
@section('page-subtitle', $quotation->quotation_number)

@section('content')
    @include('quotations.form', [
        'formAction' => route('quotations.update', $quotation),
        'formMethod' => 'PUT',
        'submitLabel' => 'Simpan Perubahan',
    ])
@endsection
