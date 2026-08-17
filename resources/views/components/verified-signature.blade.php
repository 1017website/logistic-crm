@props(['signatureQr', 'verificationUrl', 'signerName', 'signerTitle', 'companyName'])
@once
<style>
    .verified-signature{width:100%;border:1.2pt solid #111827;border-collapse:separate;border-spacing:0;page-break-inside:avoid;background:#fff}.verified-signature td{border:0!important;vertical-align:middle;padding:6pt!important}.verified-signature .qr-cell{width:27mm;text-align:center}.verified-signature .qr-cell img{display:block;width:23mm;height:23mm;margin:0 auto}.verified-signature .copy-cell{text-align:left;font-size:7.5pt;line-height:1.3}.verified-signature .signed-by{font-size:7pt;color:#4b5563}.verified-signature .signer-name{font-size:9pt;font-weight:800;margin:2pt 0}.verified-signature .signer-title{font-weight:700}.verified-signature .verify-copy{font-size:6.7pt;color:#4b5563;margin-top:3pt}
</style>
@endonce
<table class="verified-signature">
    <tr>
        <td class="qr-cell"><a href="{{ $verificationUrl }}" target="_blank" rel="noopener"><img src="{{ $signatureQr }}" alt="QR verifikasi tanda tangan elektronik"></a></td>
        <td class="copy-cell">
            <div class="signed-by">Dokumen ini ditandatangani secara elektronik oleh:</div>
            <div class="signer-name">{{ $signerName }}</div>
            <div class="signer-title">{{ $signerTitle }}</div>
            <div>{{ strtoupper($companyName) }}</div>
            <div class="verify-copy">Pindai QR untuk memverifikasi keaslian dokumen.</div>
        </td>
    </tr>
</table>
