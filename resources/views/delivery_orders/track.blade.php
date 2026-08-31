<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Tracking Surat Jalan {{ $deliveryOrder->do_number }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;background:#f3f4f6;color:#172033;font-family:Arial,sans-serif}.wrap{min-height:100vh;padding:20px;display:grid;place-items:center}.card{width:min(620px,100%);background:#fff;border-radius:18px;box-shadow:0 12px 40px rgba(23,32,51,.12);overflow:hidden}.head{background:#111827;color:#fff;padding:26px;text-align:center;border-top:5px solid #0ea5c6}.eyebrow{font-size:11px;letter-spacing:1.2px;opacity:.72}.head h1{font-size:22px;margin:7px 0}.status{display:inline-block;padding:6px 12px;border-radius:99px;background:#0ea5c6;color:#fff;font-size:12px;font-weight:700}.body{padding:26px}.route{display:grid;grid-template-columns:1fr 36px 1fr;align-items:center;gap:8px;background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin-bottom:18px}.route .place{font-size:15px;font-weight:800}.route .label{font-size:10px;color:#6b7280;text-transform:uppercase;margin-bottom:4px}.route .arrow{text-align:center;font-size:20px;color:#0ea5c6}.info{display:grid;grid-template-columns:145px 1fr;gap:10px;padding:10px 0;border-bottom:1px solid #edf0f3;font-size:13px}.info span:first-child{color:#6b7280}.info span:last-child{font-weight:700;overflow-wrap:anywhere}.activity-title{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:#6b7280;margin:22px 0 10px;font-weight:800}.activity{position:relative;padding:0 0 15px 22px;border-left:2px solid #dbe5ec;margin-left:5px}.activity:last-child{padding-bottom:0}.activity:before{content:"";position:absolute;left:-6px;top:2px;width:10px;height:10px;border-radius:50%;background:#0ea5c6;border:2px solid #fff}.activity b{display:block;font-size:13px}.activity small{color:#6b7280}.foot{text-align:center;padding:0 26px 24px;color:#6b7280;font-size:11px;line-height:1.5}@media(max-width:480px){.wrap{padding:0}.card{min-height:100vh;border-radius:0}.body{padding:22px 18px}.head{padding:24px 18px}.info{grid-template-columns:1fr;gap:4px}.route{grid-template-columns:1fr 25px 1fr;padding:13px}.route .place{font-size:13px}}
    </style>
</head>
<body>
@php
    $do = $deliveryOrder;
    $lastUpdate = $do->statusLogs->first()?->created_at ?? $do->updated_at;
@endphp
<main class="wrap">
    <section class="card">
        <header class="head">
            <div class="eyebrow">TRACKING SURAT JALAN</div>
            <h1>{{ $do->do_number }}</h1>
            <span class="status">{{ $do->flow_label }}</span>
        </header>
        <div class="body">
            <div class="route">
                <div><div class="label">Asal</div><div class="place">{{ $do->origin ?: '-' }}</div></div>
                <div class="arrow">&rarr;</div>
                <div><div class="label">Tujuan</div><div class="place">{{ $do->destination ?: '-' }}</div></div>
            </div>

            <div class="info"><span>Penerima</span><span>{{ $do->customer?->company_name ?? '-' }}</span></div>
            <div class="info"><span>Tanggal Surat Jalan</span><span>{{ $do->do_date?->translatedFormat('d F Y') ?? '-' }}</span></div>
            <div class="info"><span>Referensi Request</span><span>{{ $do->requestOrder?->do_number ?? '-' }}</span></div>
            <div class="info"><span>Pembaruan terakhir</span><span>{{ $lastUpdate?->translatedFormat('d F Y H:i') ?? '-' }}</span></div>

            <div class="activity-title">Riwayat Perjalanan</div>
            @forelse($do->statusLogs as $log)
                <div class="activity">
                    <b>{{ \App\Models\DeliveryOrder::FLOW[$log->to_status] ?? ucfirst(str_replace('_', ' ', $log->to_status)) }}</b>
                    <small>{{ $log->created_at?->translatedFormat('d F Y H:i') }}</small>
                </div>
            @empty
                <div class="activity"><b>{{ $do->flow_label }}</b><small>Status awal Surat Jalan</small></div>
            @endforelse
        </div>
        <footer class="foot">
            {{ strtoupper($companyName) }}<br>
            Halaman tracking resmi dari QR Surat Jalan. Tidak diperlukan aplikasi scanner khusus.
        </footer>
    </section>
</main>
</body>
</html>
