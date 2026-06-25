<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Surat Jalan {{ $deliveryOrder->do_number }}</title>
<style>
    * { font-family: Arial, Helvetica, sans-serif; box-sizing: border-box; }
    body { margin: 24px; color: #111; font-size: 12px; }

    .sj-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #111; padding-bottom: 10px; margin-bottom: 12px; }
    .sj-head .company h1 { margin: 0; font-size: 18px; letter-spacing: .5px; }
    .sj-head .company .sub { font-size: 11px; color: #555; line-height: 1.5; margin-top: 2px; }
    .sj-head .company img { max-height: 54px; margin-bottom: 6px; }

    .sj-title { text-align: right; }
    .sj-title .lbl { font-size: 16px; font-weight: 700; letter-spacing: 1px; }
    .sj-title .internal { display: inline-block; background: #111; color: #fff; font-size: 10px; padding: 2px 8px; border-radius: 3px; margin-top: 4px; }
    .sj-title .barcode { margin-top: 6px; }
    .sj-title .no { font-size: 12px; margin-top: 4px; font-weight: 600; }

    table.meta { width: 100%; margin-bottom: 10px; border-collapse: collapse; }
    table.meta td { vertical-align: top; padding: 2px 6px; font-size: 11px; }
    table.meta td.k { color: #555; width: 110px; }
    .box { border: 1px solid #999; border-radius: 4px; padding: 8px 10px; }

    table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.items th, table.items td { border: 1px solid #333; padding: 6px 8px; font-size: 11px; }
    table.items th { background: #f0f0f0; text-align: left; }
    table.items td.c { text-align: center; }
    table.items td.r { text-align: right; }

    .sign { display: flex; justify-content: space-between; margin-top: 36px; text-align: center; font-size: 11px; }
    .sign .col { width: 30%; }
    .sign .line { margin-top: 56px; border-top: 1px solid #111; padding-top: 4px; }

    .foot { margin-top: 14px; font-size: 9px; color: #666; text-align: center; }

    .noprint { margin-bottom: 14px; }
    @media print {
        .noprint { display: none; }
        body { margin: 10mm; }
    }
</style>
</head>
<body>

    <div class="noprint">
        <button onclick="window.print()" style="padding:6px 14px;cursor:pointer">🖨️ Cetak Surat Jalan</button>
        <a href="{{ route('delivery-orders.show', $deliveryOrder->id) }}" style="padding:6px 14px;margin-left:6px">← Kembali</a>
    </div>

    @php
        $do   = $deliveryOrder;
        $ro   = $do->requestOrder;
        $items = $ro?->items ?? collect();
    @endphp

    <div class="sj-head">
        <div class="company">
            @if(!empty($company['logo']))
                <img src="{{ \Illuminate\Support\Facades\Storage::url($company['logo']) }}" alt="Logo">
            @endif
            <h1>{{ $company['name'] }}</h1>
            <div class="sub">
                {!! nl2br(e($company['address'])) !!}<br>
                @if($company['phone'])Telp: {{ $company['phone'] }}@endif
                @if($company['email']) · {{ $company['email'] }}@endif
            </div>
        </div>
        <div class="sj-title">
            <div class="lbl">SURAT JALAN</div>
            <div class="internal">ARMADA INTERNAL</div>
            <div class="barcode">
                <svg id="sjBarcode"></svg>
            </div>
            <div class="no">{{ $do->do_number }}</div>
        </div>
    </div>

    <table class="meta">
        <tr>
            <td style="width:50%">
                <div class="box">
                    <table style="width:100%">
                        <tr><td class="k">Tanggal</td><td>: {{ $do->do_date?->format('d M Y') ?? '-' }}</td></tr>
                        <tr><td class="k">Tujuan / Penerima</td><td>: {{ $do->customer?->company_name ?? '-' }}</td></tr>
                        <tr><td class="k">Alamat Tujuan</td><td>: {{ $do->destination ?? ($do->customer?->address ?? '-') }}</td></tr>
                        <tr><td class="k">Asal</td><td>: {{ $do->origin ?? '-' }}</td></tr>
                    </table>
                </div>
            </td>
            <td style="width:50%">
                <div class="box">
                    <table style="width:100%">
                        <tr><td class="k">Armada</td><td>: {{ $do->fleet_info ?? '-' }}</td></tr>
                        <tr><td class="k">Driver</td><td>: {{ $do->driver_name ?? '-' }}</td></tr>
                        <tr><td class="k">No. HP Driver</td><td>: {{ $do->driver_phone ?? '-' }}</td></tr>
                        <tr><td class="k">Ref. Request DO</td><td>: {{ $ro?->do_number ?? '-' }}</td></tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:36px" class="c">No</th>
                <th>Deskripsi Barang / Layanan</th>
                <th style="width:90px" class="c">Qty</th>
                <th style="width:70px" class="c">Satuan</th>
                <th style="width:90px" class="c">Tonase</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $i => $it)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $it->service_name }}@if($it->description)<br><small style="color:#666">{{ $it->description }}</small>@endif</td>
                <td class="r">{{ rtrim(rtrim(number_format((float)$it->qty, 3, ',', '.'), '0'), ',') }}</td>
                <td class="c">{{ $it->unit ?? '-' }}</td>
                <td class="r">{{ $it->tonnage ? number_format((float)$it->tonnage, 2, ',', '.') : '-' }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="c" style="color:#888">Tidak ada rincian barang.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="sign">
        <div class="col">
            Diserahkan oleh,
            <div class="line">( Petugas Gudang )</div>
        </div>
        <div class="col">
            Pengirim / Driver,
            <div class="line">( {{ $do->driver_name ?? '..................' }} )</div>
        </div>
        <div class="col">
            Diterima oleh,
            <div class="line">( Penerima )</div>
        </div>
    </div>

    <div class="foot">
        Dokumen ini sah tanpa tanda tangan basah jika disertai barcode {{ $do->do_number }}.
        Dicetak {{ now()->format('d M Y H:i') }}.
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        try {
            JsBarcode("#sjBarcode", "{{ $do->do_number }}", {
                format: "CODE128",
                width: 1.6,
                height: 42,
                displayValue: false,
                margin: 0
            });
        } catch (e) { /* fallback: nomor tetap tampil di bawah barcode */ }
    </script>
</body>
</html>
