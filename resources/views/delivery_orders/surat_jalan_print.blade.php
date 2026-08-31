<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Surat Jalan {{ $deliveryOrder->do_number }}</title>
<style>
    :root { --ink:#1a1a1a; --muted:#6b7280; --line:#d1d5db; --accent:#111827; }
    * { font-family: 'Segoe UI', Arial, Helvetica, sans-serif; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    body { margin:0; color:var(--ink); font-size:12px; background:#f3f4f6; }

    .sheet {
        width: 210mm; min-height: 297mm; margin: 16px auto; background:#fff;
        padding: 18mm 16mm; box-shadow: 0 1px 8px rgba(0,0,0,.12);
    }

    .document-title { display:flex; justify-content:space-between; align-items:center; margin:0 0 16px; }
    .document-title .ttl { font-size:18px; font-weight:800; letter-spacing:1.5px; color:var(--accent); }
    .document-title .pill { display:inline-block; background:var(--accent); color:#fff; font-size:9.5px; letter-spacing:.5px; padding:3px 10px; border-radius:99px; }
    .document-title .nodo { font-size:12px; font-weight:700; color:var(--accent); text-align:right; }

    /* Info grid */
    .grid { display:grid; grid-template-columns:1fr 1fr; gap:14px; margin-bottom:14px; }
    .panel { border:1px solid var(--line); border-radius:8px; overflow:hidden; }
    .panel .ph { background:#f9fafb; border-bottom:1px solid var(--line); padding:6px 12px; font-size:10px; font-weight:700; letter-spacing:.4px; color:var(--muted); text-transform:uppercase; }
    .panel .pb { padding:9px 12px; }
    .panel .row { display:flex; gap:8px; padding:2.5px 0; font-size:11.5px; }
    .panel .row .k { color:var(--muted); width:108px; flex-shrink:0; }
    .panel .row .v { font-weight:600; }

    /* Items table */
    table.items { width:100%; border-collapse:collapse; margin-top:2px; }
    table.items thead th { background:var(--accent); color:#fff; font-size:10.5px; font-weight:700; letter-spacing:.3px; padding:8px 10px; text-align:left; }
    table.items tbody td { border-bottom:1px solid var(--line); padding:8px 10px; font-size:11.5px; vertical-align:top; }
    table.items tbody tr:nth-child(even) td { background:#fafafa; }
    table.items .c { text-align:center; }
    table.items .r { text-align:right; }
    table.items .desc { font-size:10px; color:var(--muted); margin-top:2px; }
    table.items .empty { text-align:center; color:#9ca3af; padding:18px; }

    /* Signatures */
    .sign { display:flex; justify-content:space-between; gap:24px; margin-top:34px; align-items:flex-end; }
    .sign .manual { flex:1; display:flex; gap:18px; }
    .sign .col { flex:1; text-align:center; font-size:11px; }
    .sign .col .role { color:var(--muted); }
    .sign .col .signature-space { height:54px; display:flex; align-items:flex-end; justify-content:center; }
    .sign .col .line { border-top:1px solid var(--ink); padding-top:5px; font-weight:600; }
    .sign .tracking { width:50mm; flex-shrink:0; }
    .tracking-card { border:1.2px solid var(--accent); padding:8px; text-align:center; page-break-inside:avoid; }
    .tracking-card img { width:25mm; height:25mm; display:block; margin:0 auto 5px; }
    .tracking-card .tracking-title { font-size:9.5px; font-weight:800; color:var(--accent); }
    .tracking-card .tracking-copy { margin-top:3px; font-size:7.5px; line-height:1.35; color:var(--muted); }

    .foot { margin-top:18px; padding-top:10px; border-top:1px dashed var(--line); font-size:9px; color:var(--muted); display:flex; justify-content:space-between; }

    /* Toolbar */
    .toolbar { width:210mm; margin:16px auto 0; display:flex; gap:8px; }
    .toolbar button, .toolbar a { font-size:13px; padding:8px 16px; border-radius:8px; border:1px solid var(--line); background:#fff; cursor:pointer; text-decoration:none; color:var(--ink); font-weight:600; }
    .toolbar button.primary { background:var(--accent); color:#fff; border-color:var(--accent); }

    @media print {
        body { background:#fff; }
        .toolbar { display:none; }
        .sheet { width:auto; min-height:auto; margin:0; padding:8mm; box-shadow:none; }
        @page { size: A4; margin: 8mm; }
    }
</style>
</head>
<body>

@php
    $do    = $deliveryOrder;
    $ro    = $do->requestOrder;
    $items = $ro?->items ?? collect();
@endphp

<div class="toolbar">
    <button class="primary" onclick="window.print()">🖨️ Cetak</button>
    <a href="{{ route('delivery-orders.show', $do->id) }}">← Kembali</a>
</div>

<div class="sheet">

    <x-print-letterhead :company="$company" />

    <div class="document-title">
        <div>
            <div class="ttl">SURAT JALAN</div>
            <div class="pill">ARMADA INTERNAL</div>
        </div>
        <div class="nodo">{{ $do->do_number }}</div>
    </div>

    <div class="grid">
        <div class="panel">
            <div class="ph">Penerima &amp; Tujuan</div>
            <div class="pb">
                <div class="row"><span class="k">Tanggal</span><span class="v">{{ $do->do_date?->format('d M Y') ?? '-' }}</span></div>
                <div class="row"><span class="k">Penerima</span><span class="v">{{ $do->customer?->company_name ?? '-' }}</span></div>
                <div class="row"><span class="k">Alamat Tujuan</span><span class="v">{{ $do->destination ?? ($do->customer?->address ?? '-') }}</span></div>
                <div class="row"><span class="k">Asal</span><span class="v">{{ $do->origin ?? '-' }}</span></div>
            </div>
        </div>
        <div class="panel">
            <div class="ph">Armada &amp; Pengemudi</div>
            <div class="pb">
                <div class="row"><span class="k">Armada</span><span class="v">{{ $do->fleet_info ?? '-' }}</span></div>
                <div class="row"><span class="k">Driver</span><span class="v">{{ $do->driver_name ?? '-' }}</span></div>
                <div class="row"><span class="k">No. HP Driver</span><span class="v">{{ $do->driver_phone ?? '-' }}</span></div>
                <div class="row"><span class="k">Ref. Request DO</span><span class="v">{{ $ro?->do_number ?? '-' }}</span></div>
            </div>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:36px" class="c">No</th>
                <th>Deskripsi Barang / Layanan</th>
                <th style="width:90px" class="r">Qty</th>
                <th style="width:70px" class="c">Satuan</th>
                <th style="width:90px" class="r">Tonase</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $it)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $it->service_name }}@if($it->description)<div class="desc">{{ $it->description }}</div>@endif</td>
                <td class="r">{{ rtrim(rtrim(number_format((float)$it->qty, 3, ',', '.'), '0'), ',') }}</td>
                <td class="c">{{ $it->unit ?? '-' }}</td>
                <td class="r">{{ $it->tonnage ? number_format((float)$it->tonnage, 2, ',', '.') : '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="empty">Tidak ada rincian barang.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div class="manual">
            <div class="col">
                <div class="role">Pengirim / Driver,</div>
                <div class="signature-space"></div>
                <div class="line">( {{ $do->driver_name ?: '..................' }} )</div>
            </div>
            <div class="col">
                <div class="role">Diterima oleh,</div>
                <div class="signature-space"></div>
                <div class="line">( Penerima )</div>
            </div>
        </div>
        <div class="tracking">
            <div class="tracking-card">
                <a href="{{ $tracking['trackingUrl'] }}" target="_blank" rel="noopener">
                    <img src="{{ $tracking['trackingQr'] }}" alt="QR tracking Surat Jalan {{ $do->do_number }}">
                </a>
                <div class="tracking-title">SCAN UNTUK TRACKING</div>
                <div class="tracking-copy">Gunakan kamera ponsel untuk melihat status terbaru Surat Jalan.</div>
            </div>
        </div>
    </div>

    <div class="foot">
        <span>QR digunakan untuk tracking Surat Jalan {{ $do->do_number }}.</span>
        <span>Dicetak {{ now()->format('d M Y H:i') }}</span>
    </div>

</div>

</body>
</html>
