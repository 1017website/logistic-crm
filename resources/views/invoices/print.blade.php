<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Pro Forma Invoice {{ $invoice->invoice_number }}</title>
<style>
    * { font-family: Arial, sans-serif; box-sizing: border-box; }
    body { margin: 24px; color: #111; font-size: 12px; }
    .head { text-align: center; margin-bottom: 12px; }
    .head h1 { margin: 0; font-size: 20px; letter-spacing: 1px; }
    .head .sub { font-size: 11px; color: #555; }
    .tag { background: #ffeb3b; padding: 2px 8px; font-weight: bold; font-size: 11px; display: inline-block; }
    .meta { width: 100%; margin: 10px 0; }
    .meta td { vertical-align: top; padding: 1px 4px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.items th, table.items td { border: 1px solid #333; padding: 5px 7px; font-size: 11px; }
    table.items th { background: #f0f0f0; text-align: left; }
    .right { text-align: right; }
    .foot { margin-top: 6px; font-size: 10px; text-align: center; color: #444; }
    @media print { .noprint { display: none; } body { margin: 8px; } }
</style>
</head>
<body>
    <div class="noprint" style="margin-bottom:12px">
        <button onclick="window.print()" style="padding:6px 14px;cursor:pointer">🖨️ Cetak</button>
    </div>

    <div class="head">
        <span class="tag">PRO FORMA INVOICE</span>
        <h1>FIRMAN TANGGUH</h1>
        <div class="sub">THE TRANSPORTER</div>
    </div>

    <table class="meta">
        <tr>
            <td style="width:55%">
                <b>PENERIMA</b><br>
                {{ $invoice->customer?->company_name ?? '-' }}<br>
                {{ $invoice->customer?->address ?? '' }}
            </td>
            <td style="width:45%">
                <table style="width:100%">
                    <tr><td>Tanggal</td><td>: {{ $invoice->tgl_buat?->format('d M Y') ?? '-' }}</td></tr>
                    <tr><td>No Invoice</td><td>: {{ $invoice->invoice_number }}</td></tr>
                    <tr><td>Jatuh Tempo</td><td>: {{ $invoice->tgl_tempo?->format('d M Y') ?? '-' }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width:30px">No</th>
                <th style="width:80px">Tgl Kirim</th>
                <th>Keterangan</th>
                <th style="width:120px" class="right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($invoice->items as $it)
            @php $do = $it->requestOrder; @endphp
            <tr>
                <td>{{ $no++ }}</td>
                <td>{{ $do?->tgl_muat?->format('d-m-y') ?? $do?->order_date?->format('d-m-y') ?? '-' }}</td>
                <td>
                    Depo: {{ $do?->depo ?? '-' }} | Muat: {{ $do?->muat ?? $do?->origin ?? '-' }} | Bongkar: {{ $do?->bongkar ?? $do?->destination ?? '-' }}
                    | Tujuan: {{ $do?->tujuan ?? '-' }}, Komoditas: {{ $do?->komoditi ?? '-' }}
                    (No. Container: {{ $do?->no_container ?? '-' }}, No Seal: {{ $do?->no_seal ?? '-' }}, No. Pol: {{ $do?->no_pol ?? '-' }}, Armada: {{ $do?->jenis_truck ?? '-' }})
                </td>
                <td class="right">{{ number_format($it->jual, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3"><b>Terbilang :</b> {{ ucwords(trim(\App\Models\Invoice::terbilang($invoice->grand_total ?: $invoice->total_jual))) }} rupiah</td>
                <td class="right"><b>Total</b><br>{{ number_format($invoice->total_jual, 0, ',', '.') }}</td>
            </tr>
            @if(($ppnPersen ?? 0) > 0 || $invoice->ppn_nominal > 0)
            <tr><td colspan="3" class="right">PPN {{ rtrim(rtrim(number_format($invoice->ppn_persen,2),'0'),'.') }}%</td><td class="right">{{ number_format($invoice->ppn_nominal, 0, ',', '.') }}</td></tr>
            @endif
            <tr><td colspan="3" class="right"><b>Grand Total</b></td><td class="right"><b>{{ number_format($invoice->grand_total ?: $invoice->total_jual, 0, ',', '.') }}</b></td></tr>
        </tfoot>
    </table>

    <div class="foot">
        PT. Firman Tangguh Logistik<br>
        Jl. Griya Kebraon Barat Block CK 23, Karangpilang, Surabaya | 031-76800968<br>
        ft-logistik.com | info@ft-logistik.com
    </div>
</body>
</html>
