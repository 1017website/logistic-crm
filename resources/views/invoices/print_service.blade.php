<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $serviceType === 'TR' ? 'Trucking' : 'Non Trucking' }} {{ $invoice->invoice_number }}</title>
<style>
    @page { size:A4 portrait; margin:39mm 14mm 19mm; }
    * { box-sizing:border-box; }
    body { margin:0; font-family:Arial, Helvetica, sans-serif; font-size:9pt; line-height:1.35; color:#071637; background:#eee; }
    .sheet { width:210mm; min-height:297mm; margin:16px auto; padding:10mm 14mm 19mm; background:#fff; }
    .masthead { text-align:center; height:29mm; }
    .masthead img { width:84mm; max-height:19mm; object-fit:contain; }
    .company-name { font-size:17pt; font-weight:bold; line-height:1.05; }
    .tagline { font-size:8pt; font-weight:bold; line-height:1.1; }
    .document-name { font-size:10pt; font-weight:bold; line-height:1.2; }
    table { width:100%; border-collapse:collapse; }
    .meta { margin:2mm 0 1mm; font-size:9pt; font-weight:bold; }
    .meta td { vertical-align:top; padding:0; }
    .meta .recipient { width:58%; }
    .recipient-table td:first-child { width:17mm; }
    .invoice-meta td:first-child { width:24mm; }
    .invoice-meta td:nth-child(2) { width:3mm; }
    .invoice-meta td:last-child { text-align:right; }
    .invoice-number { padding-top:12mm !important; white-space:nowrap; }
    .items { table-layout:auto; font-size:8pt; }
    .items th, .items td { border:.6pt solid #171d29; padding:2pt 3pt; vertical-align:top; overflow-wrap:break-word; }
    .items th { text-align:left; background:#fff; height:8mm; vertical-align:middle; }
    .items .number { width:9mm; text-align:center; }
    .items .date { width:22mm; text-align:center; white-space:nowrap; }
    .items .amount { width:30mm; }
    .money { position:relative; text-align:right; white-space:nowrap; padding-left:17pt !important; }
    .money .currency { position:absolute; left:3pt; }
    .items .tr-amount { vertical-align:bottom; }
    .items .amount-line td { border:0; padding:0; font-size:inherit; vertical-align:bottom; }
    .items .amount-line .currency-label { width:17pt; text-align:left; }
    .items .amount-line .amount-value { text-align:right; }
    .do-number { font-size:7pt; }
    .ntr-do-group { page-break-inside:avoid; break-inside:avoid; }
    .summary { margin-top:3mm; page-break-inside:avoid; break-inside:avoid; font-size:8pt; }
    .tr-summary { page-break-before:always; break-before:page; }
    .totals td { border:.6pt solid #171d29; padding:3pt; }
    .totals .words { width:70%; }
    .totals .label { width:13%; }
    .payment { margin-top:2mm; font-size:8pt; }
    .payment td { padding:1pt 0; vertical-align:top; }
    .payment .label { width:34mm; }
    .payment .signature { width:42%; text-align:right; }
    .company-sign { padding-top:14mm !important; }
    .digital-signature { width:92mm; margin:5mm 0 0 auto; page-break-inside:avoid; }
    .footer { text-align:center; font-size:7pt; line-height:1.4; margin-top:4mm; }
    .toolbar { max-width:210mm; margin:12px auto; display:flex; gap:8px; flex-wrap:wrap; }
    .toolbar a, .toolbar button { font:13px Arial,sans-serif; color:#071637; background:white; border:1px solid #888; padding:8px 12px; text-decoration:none; cursor:pointer; }
    .toolbar a.active { background:#111827; border-color:#111827; color:#fff; font-weight:bold; }
    .toolbar a:focus-visible, .toolbar button:focus-visible { outline:2px solid #111827; outline-offset:3px; }
    @media screen {
        body { display:flex; flex-direction:column; }
        .toolbar { order:0; }
        .masthead { order:1; width:210mm; height:39mm; margin:16px auto 0; padding-top:10mm; background:#fff; }
        .sheet { order:2; margin:0 auto; padding-top:0; min-height:239mm; }
        .footer { order:3; width:210mm; margin:0 auto 16px; padding-bottom:5mm; background:#fff; }
    }
    @media screen and (max-width:800px) { .sheet { margin:0; } .toolbar { padding:0 8px; } }
    @media print {
        body { background:#fff; }
        .toolbar { display:none; }
        .sheet { width:auto; min-height:auto; margin:0; padding:0; background:transparent; }
        .masthead { position:fixed; top:-32mm; left:0; right:0; }
        .footer { position:fixed; bottom:-14mm; left:0; right:0; margin:0; }
    }
    @if($isPdf)
    body { background:#fff; }
    .sheet { width:auto; min-height:auto; margin:0; padding:0; background:transparent; }
    .toolbar { display:none; }
    .masthead { position:fixed; top:-32mm; left:0; right:0; }
    .footer { position:fixed; bottom:-14mm; left:0; right:0; margin:0; }
    @endif
</style>
</head>
<body>
@php
    $logo = $company['logo'] ?? null;
    $logoUrl = $logo && str_starts_with($logo, 'data:') ? $logo : ($logo ? \Illuminate\Support\Facades\Storage::url($logo) : null);
    $invoiceDate = $invoice->tgl_buat?->copy()->locale('id')->translatedFormat('l, d F Y') ?? '-';
    $topDays = $invoice->tgl_buat && $invoice->tgl_tempo ? (int) $invoice->tgl_buat->diffInDays($invoice->tgl_tempo) : $invoice->customer?->effective_top_days;
@endphp
@include('invoices.partials.print_toolbar')
    <div class="masthead">
        @if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo {{ $company['name'] }}">@endif
        <div class="company-name">{{ $company['name'] }}</div>
        <div class="tagline">THE TRANSPORTER</div>
        <div class="document-name">{{ $documentMode === 'proforma' ? 'PRO FORMA INVOICE' : 'INVOICE' }} {{ $serviceType === 'TR' ? 'TRUCKING' : 'NON TRUCKING' }}</div>
    </div>
    <div class="footer">
        @if($company['npwp'])<div>{{ $company['npwp'] }}</div>@endif
        <div>{{ str_replace(["\r\n", "\n"], ' ', $company['address']) }}@if($company['phone']) | {{ $company['phone'] }}@endif</div>
        <div>{{ $company['website'] }}@if($company['website'] && $company['email']) | @endif{{ $company['email'] }}</div>
    </div>
<div class="sheet">
    <table class="meta">
        <tr>
            <td class="recipient">
                <table class="recipient-table"><tr><td>Kepada :</td><td>{{ $invoice->customer?->company_name ?? '-' }}<br>{!! nl2br(e($invoice->customer?->address ?? '')) !!}</td></tr></table>
            </td>
            <td><table class="invoice-meta">
                <tr><td>Tanggal</td><td>:</td><td>{{ $invoiceDate }}</td></tr>
                <tr><td class="invoice-number">No Invoice</td><td class="invoice-number">:</td><td class="invoice-number">{{ $invoice->invoice_number }}</td></tr>
            </table></td>
        </tr>
    </table>
    <table class="items">
        <colgroup><col style="width:9mm"><col style="width:22mm"><col style="width:121mm"><col style="width:30mm"></colgroup>
        <thead><tr><th class="number" style="width:9mm">No</th><th class="date" style="width:22mm">Tgl Kirim</th><th style="width:121mm">Keterangan</th><th class="amount" style="width:30mm">Jumlah (Rp)</th></tr></thead>
        @if($serviceType === 'TR')
        <tbody>
            @foreach($printItems as $it)
            @php
                $finalDo = $it->deliveryOrder;
                $order = $it->requestOrder ?? $finalDo?->requestOrder;
                $shipDate = $finalDo?->do_date?->format('d-m-y') ?? $order?->tgl_muat?->format('d-m-y') ?? $order?->order_date?->format('d-m-y') ?? '-';
            @endphp
            <tr class="tr-do-row">
                <td class="number">{{ $loop->iteration }}</td>
                <td class="date">{{ $shipDate }}</td>
                <td>{{ $it->exportWorkDescription() }}</td>
                <td class="tr-amount"><table class="amount-line"><tr><td class="currency-label">Rp.</td><td class="amount-value">{{ number_format($it->jual, 0, ',', '.') }}</td></tr></table></td>
            </tr>
            @endforeach
        </tbody>
        @else
        @foreach($ntrGroups as $group)
            @php
                $header = $group['header'];
                $finalDo = $header->deliveryOrder;
                $order = $header->requestOrder ?? $finalDo?->requestOrder;
                $shipDate = $finalDo?->do_date?->format('d-m-y') ?? $order?->tgl_muat?->format('d-m-y') ?? $order?->order_date?->format('d-m-y') ?? '-';
            @endphp
            <tbody class="ntr-do-group">
                <tr class="ntr-do-header">
                    <td class="number" rowspan="{{ $group['items']->count() + 1 }}">{{ $loop->iteration }}</td>
                    <td class="date">{{ $shipDate }}</td>
                    <td><b class="do-number">{{ $finalDo?->do_number ?? $order?->do_number ?? 'DO' }}</b><br>{{ $header->exportWorkDescription() }}</td>
                    <td></td>
                </tr>
                @foreach($group['items'] as $it)
                <tr class="ntr-work-item">
                    <td class="date">{{ $shipDate }}</td>
                    <td>* {{ $it->item_name ?: $it->description ?: 'Non-Trucking' }}@if($it->item_name && $it->description && $it->description !== $it->item_name) — {{ $it->description }}@endif</td>
                    <td class="money"><span class="currency">Rp.</span>{{ number_format($it->jual, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        @endforeach
        @endif
    </table>
    <div class="summary {{ $serviceType === 'TR' ? 'tr-summary' : '' }}">
        <table class="totals">
            <tr><td class="words">Terbilang :</td><td class="label">Total</td><td class="money"><span class="currency">Rp.</span>{{ number_format($printSubtotal, 0, ',', '.') }}</td></tr>
            @if($printPpn > 0)
            <tr><td rowspan="2">{{ ucfirst(trim(\App\Models\Invoice::terbilang($printGrand))) }} rupiah</td><td>PPN ({{ (float) $invoice->ppn_persen }}%)</td><td class="money"><span class="currency">Rp.</span>{{ number_format($printPpn, 0, ',', '.') }}</td></tr>
            <tr><td>Grand Total</td><td class="money"><span class="currency">Rp.</span>{{ number_format($printGrand, 0, ',', '.') }}</td></tr>
            @else
            <tr><td>{{ ucfirst(trim(\App\Models\Invoice::terbilang($printGrand))) }} rupiah</td><td>Grand Total</td><td class="money"><span class="currency">Rp.</span>{{ number_format($printGrand, 0, ',', '.') }}</td></tr>
            @endif
        </table>
        <table class="payment">
            <tr><td class="label">Term / Jangka</td><td>{{ $topDays ?? '-' }} HARI</td><td class="signature">{{ $invoiceDate }}</td></tr>
            <tr><td colspan="2"><b>Ditransfer Ke Rekening</b></td><td></td></tr>
            <tr><td>Bank</td><td>{{ $company['bank_name'] ?: '-' }}</td><td></td></tr>
            <tr><td>No Rekening</td><td>{{ $company['bank_account'] ?: '-' }}</td><td></td></tr>
            <tr><td>Atas Nama</td><td>{{ $company['bank_holder'] ?: '-' }}</td><td></td></tr>
        </table>
        @if($invoice->payments()->exists())
        <p>Terbayar: Rp {{ number_format($invoice->total_paid, 0, ',', '.') }} · Sisa Tagihan: Rp {{ number_format($invoice->outstanding, 0, ',', '.') }}</p>
        @endif
        <div class="digital-signature">
            <x-verified-signature :signature-qr="$signature['signatureQr']" :verification-url="$signature['verificationUrl']"
                :signer-name="$company['signatory_name']" :signer-title="$company['signatory_title']"
                :signer-phone="$company['signatory_phone']" :company-name="$company['name']" />
        </div>
    </div>
</div>
</body>
</html>
