<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi {{ $verification['number'] }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#172033;font-family:Arial,sans-serif}.wrap{min-height:100vh;display:grid;place-items:center;padding:24px}.card{width:min(560px,100%);background:#fff;border-radius:16px;box-shadow:0 12px 40px rgba(23,32,51,.12);overflow:hidden}.head{background:#111827;color:#fff;padding:28px;text-align:center;border-top:5px solid #d4ad4f}.check{width:58px;height:58px;border-radius:50%;display:grid;place-items:center;background:#fff;color:#16875b;font-size:34px;font-weight:700;margin:0 auto 14px}.head h1{font-size:21px;margin:0 0 6px}.head p{font-size:13px;margin:0;opacity:.9}.body{padding:28px}.row{display:grid;grid-template-columns:155px 1fr;gap:12px;padding:11px 0;border-bottom:1px solid #e7ebf0;font-size:14px}.row span:first-child{color:#6c7480}.row span:last-child{font-weight:700;overflow-wrap:anywhere}.foot{padding:0 28px 28px;text-align:center;font-size:12px;color:#697386;line-height:1.5}@media(max-width:480px){.row{grid-template-columns:1fr;gap:4px}.body{padding:22px}.head{padding:24px 18px}}
    </style>
</head>
<body>
<main class="wrap">
    <section class="card">
        <header class="head">
            <div class="check">&#10003;</div>
            <h1>Dokumen Terverifikasi</h1>
            <p>Tautan tanda tangan elektronik valid dan diterbitkan oleh sistem CRM.</p>
        </header>
        <div class="body">
            <div class="row"><span>Jenis dokumen</span><span>{{ $verification['label'] }}</span></div>
            <div class="row"><span>{{ $verification['number_label'] ?? 'Nomor dokumen' }}</span><span>{{ $verification['number'] }}</span></div>
            @isset($verification['attachment'])<div class="row"><span>Lampiran</span><span>{{ $verification['attachment'] }}</span></div>@endisset
            @isset($verification['subject'])<div class="row"><span>Perihal</span><span>{{ $verification['subject'] }}</span></div>@endisset
            <div class="row"><span>Tanggal</span><span>{{ $verification['date'] ?: '-' }}</span></div>
            <div class="row"><span>Ditujukan kepada</span><span>{{ $verification['counterparty'] }}</span></div>
            @isset($verification['period'])<div class="row"><span>Periode</span><span>{{ $verification['period'] }}</span></div>@endisset
            <div class="row"><span>Ditandatangani oleh</span><span>{{ $verification['signer'] }}</span></div>
            <div class="row"><span>Jabatan</span><span>{{ $verification['title'] }}</span></div>
            <div class="row"><span>Status</span><span>{{ $verification['status'] }}</span></div>
        </div>
        <div class="foot">
            {{ strtoupper($companyName) }}<br>
            Kode QR pada dokumen dapat dipindai kembali untuk membuka halaman verifikasi ini.
        </div>
    </section>
</main>
</body>
</html>
