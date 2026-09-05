<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $quotation->subject }} - {{ $quotation->quotation_number }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 11mm 15mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #111827;
            font-family: "DejaVu Sans", Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.38;
        }
        .inset { margin-left: 13mm; margin-right: 11mm; }
        .meta { border-collapse: collapse; margin-bottom: 9mm; }
        .meta td { padding: 0 0 1.5pt; vertical-align: top; }
        .meta .label { width: 30mm; }
        .recipient { margin-bottom: 5mm; }
        .recipient div { margin-bottom: 1.5pt; }
        .opening, .closing { margin: 0 0 5mm; text-align: left; }
        .rates { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 0 5mm; }
        .rates th, .rates td {
            border: .7pt solid #111;
            padding: 3.5pt 3pt;
            vertical-align: middle;
            line-height: 1.28;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .rates th { text-align: center; font-size: 7.6pt; font-weight: 800; padding: 3pt 2pt; white-space: nowrap; }
        .rates td { font-size: 8.3pt; }
        .rates .no { text-align: center; }
        .rates .tonnage { text-align: center; }
        .rates .rate { white-space: nowrap; }
        .emphasis { font-weight: 800; }
        .terms-title { font-weight: 800; font-style: italic; margin-bottom: 1.5pt; }
        .terms { margin: 0 0 6mm 5.7mm; padding: 0; font-weight: 700; }
        .terms li { padding-left: 2.2mm; margin-bottom: 1.2pt; }
        .signature-block { margin-top: 8mm; page-break-inside: avoid; }
        .signature-date { margin-bottom: 1pt; }
        .signature-verified { width: 92mm; margin-top: 3mm; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }
    </style>
</head>
<body>
    @php
        $months = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $date = $quotation->quotation_date;
        $dateLabel = $date->format('d') . ' ' . $months[(int) $date->format('n')] . ' ' . $date->format('Y');
        $opening = $quotation->opening ?: 'Berikut kami memberikan pengajuan penawaran harga kepada Bapak/Ibu Pimpinan ' . $quotation->company_name . ' untuk pekerjaan berdasarkan detail di bawah ini :';
        $contact = trim(collect([$quotation->contact_name, $quotation->contact_phone])->filter()->join(' di '));
        $closing = $quotation->closing ?: 'Demikian Surat Penawaran Harga ini kami buat, selanjutnya kami tunggu kabar baik dari Bapak/Ibu' . ($contact ? ', atau Bapak/Ibu bisa menghubungi ' . $contact : '') . '. Terima kasih.';
    @endphp

    <x-print-letterhead :company="$company" />

    <div class="inset">
        <table class="meta">
            <tr><td class="label">Nomor</td><td>:&nbsp; {{ $quotation->quotation_number }}</td></tr>
            <tr><td class="label">Lampiran</td><td>:&nbsp; {{ $quotation->attachment }}</td></tr>
            <tr><td class="label">Perihal</td><td>:&nbsp; {{ $quotation->subject }}</td></tr>
        </table>

        <div class="recipient">
            <div>{{ $quotation->recipient_name }}</div>
            <div>{{ $quotation->recipient_title }}</div>
            <div>{{ $quotation->company_name }}</div>
            @if($quotation->recipient_address)<div>{!! nl2br(e($quotation->recipient_address)) !!}</div>@endif
        </div>

        <p class="opening">{!! nl2br(e($opening)) !!}</p>
        <table class="rates">
        <colgroup>
            <col style="width:6%">
            <col style="width:15%">
            <col style="width:16%">
            <col style="width:15%">
            <col style="width:13%">
            <col style="width:17%">
            <col style="width:18%">
        </colgroup>
        <thead>
            <tr>
                <th class="no">NO</th>
                <th class="origin">ORIGIN</th>
                <th class="destination">DESTINATION</th>
                <th class="commodity">COMDTY</th>
                <th class="tonnage">TONASE</th>
                <th class="unit">UNIT</th>
                <th class="rate">RATE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($quotation->items as $index => $item)
                <tr>
                    <td class="no">{{ $index + 1 }}</td>
                    <td class="origin">{{ $item->origin }}</td>
                    <td class="destination">{{ $item->destination }}</td>
                    <td class="commodity">{{ $item->commodity }}</td>
                    <td class="tonnage emphasis">{{ $item->tonnage }}</td>
                    <td class="unit emphasis">{{ $item->unit }}</td>
                    <td class="rate emphasis">Rp {{ number_format((float) $item->rate, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
        </table>

        <div class="terms-title">Dengan syarat dan Kondisi sebagai berikut :</div>
        <ol class="terms">
            @foreach($quotation->terms ?? [] as $term)<li>{{ $term }}</li>@endforeach
        </ol>

        <p class="closing">{!! nl2br(e($closing)) !!}</p>

        <div class="signature-block">
            <div class="signature-date">{{ $quotation->city }}, {{ $dateLabel }}</div>
            <div>Mengetahui,</div>
            <div class="signature-verified">
                <x-verified-signature :signature-qr="$signatureQr" :verification-url="$verificationUrl"
                    :signer-name="$quotation->signatory_name" :signer-title="$quotation->resolvedSignatoryTitle()"
                    :company-name="$company['name']" />
            </div>
        </div>
    </div>
</body>
</html>
