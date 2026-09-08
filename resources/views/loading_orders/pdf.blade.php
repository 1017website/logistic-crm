<!DOCTYPE html>
<html lang="id"><head><meta charset="UTF-8"><title>{{ $order->number }}</title>
<style>
@page { size:A4; margin:12mm 12mm 15mm; }
body { font-family:"DejaVu Sans",sans-serif; font-size:8.5pt; line-height:1.3; color:#111; margin:0; }
.body { margin:0 13mm; } .date { text-align:right; margin:7mm 0 4mm; }
.meta { margin-bottom:8mm; } .recipient { margin-bottom:8mm; }
p { margin:0 0 3mm; } .details { border-collapse:collapse; width:95%; margin:0 0 7mm 7mm; }
.details td { vertical-align:top; padding:0; overflow-wrap:break-word; word-wrap:break-word; }
.details .label { width:32mm; } .details .colon { width:4mm; }
.terms { margin:2mm 0 4mm; padding-left:6mm; } .terms li { padding-left:1mm; margin-bottom:1mm; }
.signature { page-break-inside:avoid; margin-top:6mm; width:100mm; }
.text { overflow-wrap:break-word; word-wrap:break-word; }
</style></head><body>
<x-print-letterhead :company="$company" />
<div class="body text">
    <div class="date">{{ $order->city }}, {{ $order->letter_date->locale('id')->translatedFormat('d F Y') }}</div>
    <div class="meta">Hal &nbsp; : <strong><u>{{ $order->subject }}</u></strong><br><span style="font-size:8pt">Nomor : {{ $order->number }}</span></div>
    <div class="recipient">Kepada Yth.<br><br>{{ $order->recipient }}@if($order->recipient_address)<br>{!! nl2br(e($order->recipient_address)) !!}@endif</div>
    <p>Dengan Hormat,</p>
    <p>{!! nl2br(e($order->opening)) !!}</p>
    <table class="details">@foreach(['route'=>'Rute', 'po_number'=>'No. PO', 'mod_number'=>'No. MOD', 'driver_name'=>'Nama Driver', 'vehicle_number'=>'Nopol', 'driver_phone'=>'No. HP', 'vehicle_type'=>'Jenis Armada'] as $field=>$label)
    <tr><td class="label">{{ $label }}</td><td class="colon">:</td><td>{{ $order->$field ?: '-' }}</td></tr>
    @endforeach</table>
    <strong>Syarat dan ketentuan</strong>
    <ol class="terms">
    @foreach(preg_split('/\R/u', $order->terms) as $term)
        @if(trim($term) !== '')<li>{{ $term }}</li>@endif
    @endforeach
    </ol>
    <p>{!! nl2br(e($order->closing)) !!}</p>
    <div class="signature">Hormat kami<br>{{ $company['name'] }}<div style="margin-top:3mm">
        <x-verified-signature :signature-qr="$signatureQr" :verification-url="$verificationUrl" :signer-name="$order->signatory_name" :signer-title="$order->signatory_title" :company-name="$company['name']" />
    </div></div>
</div></body></html>
