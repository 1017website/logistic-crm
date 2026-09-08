@php($english = $order->language === 'en')
<!DOCTYPE html>
<html lang="{{ $english ? 'en' : 'id' }}"><head><meta charset="UTF-8"><title>{{ $order->number }}</title>
<style>
@page { size:A4; margin:12mm 12mm 15mm; }
body { font-family:"DejaVu Sans",sans-serif; font-size:8.5pt; line-height:1.3; color:#111; margin:0; }
.body { margin:0 13mm; } .date { text-align:right; margin:4mm 0 4mm; }
.meta { margin-bottom:5mm; } .recipient { margin-bottom:5mm; }
p { margin:0 0 3mm; } .details { border-collapse:collapse; width:95%; margin:0 0 5mm 7mm; }
.details td { vertical-align:top; padding:0; overflow-wrap:break-word; word-wrap:break-word; }
.details .label { width:32mm; } .details .colon { width:4mm; }
.terms { margin:2mm 0 4mm; padding-left:6mm; } .terms li { padding-left:1mm; margin-bottom:1mm; }
.signature { page-break-inside:avoid; margin-top:4mm; width:100mm; }
.text { overflow-wrap:break-word; word-wrap:break-word; }
</style></head><body>
<x-print-letterhead :company="$company" :compact-logo="true" />
<div class="body text">
    <div class="date">{{ $order->city }}, {{ $order->letter_date->locale($english ? 'en' : 'id')->translatedFormat('d F Y') }}</div>
    <div class="meta">{{ $english ? 'Subject' : 'Hal' }} &nbsp; : <strong><u>{{ $order->subject }}</u></strong><br><span style="font-size:8pt">{{ $english ? 'Number' : 'Nomor' }} : {{ $order->number }}</span></div>
    <div class="recipient">{{ $english ? 'To:' : 'Kepada Yth.' }}<br><br>{{ $order->recipient }}@if($order->recipient_address)<br>{!! nl2br(e($order->recipient_address)) !!}@endif</div>
    <p>{{ $english ? 'Dear Sir/Madam,' : 'Dengan Hormat,' }}</p>
    <p>{!! nl2br(e($order->opening)) !!}</p>
    <table class="details">@foreach(($english ? ['route'=>'Route', 'po_number'=>'PO No.', 'mod_number'=>'MOD No.', 'driver_name'=>'Driver Name', 'vehicle_number'=>'License Plate', 'driver_phone'=>'Phone No.', 'vehicle_type'=>'Vehicle Type'] : ['route'=>'Rute', 'po_number'=>'No. PO', 'mod_number'=>'No. MOD', 'driver_name'=>'Nama Driver', 'vehicle_number'=>'Nopol', 'driver_phone'=>'No. HP', 'vehicle_type'=>'Jenis Armada']) as $field=>$label)
    <tr><td class="label">{{ $label }}</td><td class="colon">:</td><td>{{ $order->$field ?: '-' }}</td></tr>
    @endforeach</table>
    <strong>{{ $english ? 'Terms and Conditions' : 'Syarat dan ketentuan' }}</strong>
    <ol class="terms">
    @foreach(preg_split('/\R/u', $order->terms) as $term)
        @if(trim($term) !== '')<li>{{ $term }}</li>@endif
    @endforeach
    </ol>
    <p>{!! nl2br(e($order->closing)) !!}</p>
    <div class="signature">{{ $english ? 'Yours faithfully,' : 'Hormat kami' }}<br>{{ $company['name'] }}<div style="margin-top:3mm">
        <x-verified-signature :signature-qr="$signatureQr" :verification-url="$verificationUrl" :signer-name="$order->signatory_name" :signer-title="$order->signatory_title" :company-name="$company['name']" :language="$order->language ?? 'id'" />
    </div></div>
</div></body></html>
