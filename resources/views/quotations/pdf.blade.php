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
        .letterhead { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .letterhead td { vertical-align: middle; padding: 0; }
        .logo-cell { width: 54%; padding-right: 7mm !important; text-align: center; }
        .logo { max-width: 93mm; max-height: 25mm; }
        .logo-fallback { font-size: 18pt; font-weight: 800; color: #d4ad4f; letter-spacing: .5pt; }
        .company-cell { width: 46%; }
        .company-name {
            font-size: 13.2pt;
            line-height: 1.05;
            font-weight: 800;
            font-style: italic;
            text-decoration: underline;
            margin-bottom: 2pt;
        }
        .company-detail { font-size: 8.6pt; line-height: 1.35; }
        .accent { width: 100%; height: 4.5pt; border-collapse: collapse; margin: 5mm 0 6mm; }
        .accent td { padding: 0; height: 4.5pt; }
        .accent-gold { background: #d16308; width: 29%; }
        .accent-mid { background: #9b7b45; width: 22%; }
        .accent-blue { background: #0ea5c6; width: 22%; }
        .accent-dark { background: #020617; width: 27%; }
        .inset { margin-left: 13mm; margin-right: 11mm; }
        .meta { border-collapse: collapse; margin-bottom: 9mm; }
        .meta td { padding: 0 0 1.5pt; vertical-align: top; }
        .meta .label { width: 30mm; }
        .recipient { margin-bottom: 5mm; }
        .recipient div { margin-bottom: 1.5pt; }
        .opening, .closing { margin: 0 0 5mm; text-align: left; }
        .rates { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 0 0 5mm; }
        .rates th, .rates td { border: .7pt solid #111; padding: 3.5pt 4pt; vertical-align: top; }
        .rates th { text-align: center; font-size: 8.5pt; font-weight: 800; padding: 3pt 2pt; }
        .rates td { font-size: 8.8pt; }
        .rates .no { width: 5%; text-align: center; }
        .rates .origin { width: 15%; }
        .rates .destination { width: 16%; }
        .rates .commodity { width: 15%; }
        .rates .tonnage { width: 13%; }
        .rates .unit { width: 18%; }
        .rates .rate { width: 18%; }
        .emphasis { font-weight: 800; }
        .terms-title { font-weight: 800; font-style: italic; margin-bottom: 1.5pt; }
        .terms { margin: 0 0 6mm 5.7mm; padding: 0; font-weight: 700; }
        .terms li { padding-left: 2.2mm; margin-bottom: 1.2pt; }
        .signature-block { margin-top: 8mm; page-break-inside: avoid; }
        .signature-date { margin-bottom: 1pt; }
        .signature-image { display: block; max-width: 59mm; max-height: 28mm; margin: 3mm 0 1mm -4mm; }
        .signature-space { height: 22mm; }
        .signatory-name { margin-top: 1.5mm; }
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

    <table class="letterhead">
        <tr>
            <td class="logo-cell">
                @if($company['logo'])
                    <img src="{{ $company['logo'] }}" class="logo" alt="Logo">
                @else
                    <div class="logo-fallback">{{ strtoupper($company['name']) }}</div>
                @endif
            </td>
            <td class="company-cell">
                <div class="company-name">{{ strtoupper($company['name']) }}</div>
                @if($company['address'])<div class="company-detail">{!! nl2br(e($company['address'])) !!}</div>@endif
                @if($company['phone'])<div class="company-detail">{{ $company['phone'] }}</div>@endif
                @if($company['website'] || $company['email'])
                    <div class="company-detail">
                        {{ $company['website'] }}@if($company['website'] && $company['email']) | @endif{{ $company['email'] }}
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="accent"><tr><td class="accent-gold"></td><td class="accent-mid"></td><td class="accent-blue"></td><td class="accent-dark"></td></tr></table>

    <div class="inset">
        <table class="meta">
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
    </div>

    <table class="rates">
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
                    <td>{{ $item->origin }}</td>
                    <td>{{ $item->destination }}</td>
                    <td>{{ $item->commodity }}</td>
                    <td class="emphasis">{{ $item->tonnage }}</td>
                    <td class="emphasis">{{ $item->unit }}</td>
                    <td class="emphasis">Rp {{ number_format((float) $item->rate, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="inset">
        <div class="terms-title">Dengan syarat dan Kondisi sebagai berikut :</div>
        <ol class="terms">
            @foreach($quotation->terms ?? [] as $term)<li>{{ $term }}</li>@endforeach
        </ol>

        <p class="closing">{!! nl2br(e($closing)) !!}</p>

        <div class="signature-block">
            <div class="signature-date">{{ $quotation->city }}, {{ $dateLabel }}</div>
            <div>Mengetahui,</div>
            <div>{{ $quotation->signatory_title }} {{ $company['name'] }}</div>
            @if($company['signature'])
                <img src="{{ $company['signature'] }}" class="signature-image" alt="Tanda tangan">
            @else
                <div class="signature-space"></div>
            @endif
            <div class="signatory-name">{{ $quotation->signatory_name }}</div>
        </div>
    </div>
</body>
</html>
