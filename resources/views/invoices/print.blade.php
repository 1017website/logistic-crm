<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice {{ $invoice->invoice_number }}</title>
<style>
    :root { --ink:#1a1a1a; --muted:#6b7280; --line:#d1d5db; --accent:#111827; --soft:#f9fafb; }
    * { font-family:'Segoe UI', Arial, Helvetica, sans-serif; box-sizing:border-box; -webkit-print-color-adjust:exact; print-color-adjust:exact; }
    body { margin:0; color:var(--ink); font-size:12px; background:#f3f4f6; }

    .sheet { width:210mm; min-height:297mm; margin:16px auto; background:#fff; padding:16mm 15mm; box-shadow:0 1px 8px rgba(0,0,0,.12); }

    .document-title { display:flex; justify-content:space-between; align-items:center; margin:0 0 16px; }
    .document-title .ttl { font-size:22px; font-weight:800; letter-spacing:2px; color:var(--accent); }
    .document-title .sub { font-size:9.5px; letter-spacing:1px; color:var(--muted); }
    .document-title .badge { display:inline-block; font-size:10px; font-weight:700; padding:3px 12px; border-radius:99px; }
    .badge.s-draft { background:#f3f4f6; color:#6b7280; }
    .badge.s-invoice { background:#dbeafe; color:#1d4ed8; }
    .badge.s-paid { background:#d1fae5; color:#047857; }

    /* Bill-to & meta */
    .topgrid { display:flex; justify-content:space-between; gap:22px; margin-bottom:16px; }
    .billto { flex:1; }
    .billto .lbl { font-size:9.5px; letter-spacing:.6px; text-transform:uppercase; color:var(--muted); font-weight:700; margin-bottom:4px; }
    .billto .name { font-size:14px; font-weight:700; }
    .billto .addr { font-size:11px; color:var(--muted); margin-top:2px; line-height:1.5; }
    .metabox { width:230px; border:1px solid var(--line); border-radius:8px; overflow:hidden; }
    .metabox .r { display:flex; justify-content:space-between; padding:6px 12px; font-size:11px; border-bottom:1px solid var(--line); }
    .metabox .r:last-child { border-bottom:0; }
    .metabox .r .k { color:var(--muted); }
    .metabox .r .v { font-weight:700; }

    /* Items */
    table.items { width:100%; border-collapse:collapse; }
    table.items thead th { background:var(--accent); color:#fff; font-size:10.5px; font-weight:700; letter-spacing:.3px; padding:9px 10px; text-align:left; }
    table.items thead th.r { text-align:right; }
    table.items tbody td { border-bottom:1px solid var(--line); padding:9px 10px; font-size:11px; vertical-align:top; }
    table.items tbody tr:nth-child(even) td { background:var(--soft); }
    table.items td.r { text-align:right; white-space:nowrap; }
    table.items td.c { text-align:center; }
    table.items .det { font-size:9.5px; color:var(--muted); line-height:1.5; margin-top:2px; }

    /* Totals */
    .totwrap { display:flex; justify-content:space-between; gap:22px; margin-top:14px; }
    .terbilang { flex:1; font-size:10.5px; color:var(--muted); padding-top:6px; }
    .terbilang b { color:var(--ink); }
    .totals { width:260px; }
    .totals .r { display:flex; justify-content:space-between; padding:5px 0; font-size:12px; }
    .totals .r .k { color:var(--muted); }
    .totals .grand { border-top:2px solid var(--accent); margin-top:4px; padding-top:8px; font-size:15px; font-weight:800; }

    /* Signature + footer */
    .sign { margin:32px 0 0 auto; width:92mm; }

    .foot { margin-top:22px; padding-top:10px; border-top:1px dashed var(--line); font-size:9.5px; color:var(--muted); text-align:center; line-height:1.6; }

    .toolbar { width:210mm; margin:16px auto 0; display:flex; gap:8px; }
    .toolbar button, .toolbar a { font-size:13px; padding:8px 16px; border-radius:8px; border:1px solid var(--line); background:#fff; cursor:pointer; text-decoration:none; color:var(--ink); font-weight:600; }
    .toolbar button.primary { background:var(--accent); color:#fff; border-color:var(--accent); }

    @media print {
        body { background:#fff; }
        .toolbar { display:none; }
        .sheet { width:auto; min-height:auto; margin:0; padding:8mm; box-shadow:none; }
        @page { size:A4; margin:8mm; }
    }
</style>
</head>
<body>

@php
    $statusClass = ['draft'=>'s-draft','invoice'=>'s-invoice','paid'=>'s-paid'][$invoice->status] ?? 's-draft';
    $statusText  = ['draft'=>'DRAFT','invoice'=>'INVOICE','paid'=>'LUNAS'][$invoice->status] ?? strtoupper($invoice->status);
@endphp

<div class="toolbar">
    <button class="primary" onclick="window.print()">Cetak</button>
    <a href="{{ route('invoices.print', [$invoice->id, 'type' => 'all']) }}">Semua</a>
    @if($invoice->items->contains('item_type', 'TR'))<a href="{{ route('invoices.print', [$invoice->id, 'type' => 'TR']) }}">Trucking</a>@endif
    @if($invoice->items->contains('item_type', 'NTR'))<a href="{{ route('invoices.print', [$invoice->id, 'type' => 'NTR']) }}">Non-Trucking</a>@endif
    <a href="{{ route('invoices.show', $invoice->id) }}">Kembali</a>
</div>

<div class="sheet">

    <x-print-letterhead :company="$company" />

    <div class="document-title">
        <div>
            <div class="ttl">INVOICE</div>
            <div class="sub">PRO FORMA</div>
        </div>
        <span class="badge {{ $statusClass }}">{{ $statusText }}</span>
    </div>

    <div class="topgrid">
        <div class="billto">
            <div class="lbl">Ditagihkan Kepada</div>
            <div class="name">{{ $invoice->customer?->company_name ?? '-' }}</div>
            <div class="addr">{{ $invoice->customer?->address ?? '' }}</div>
        </div>
        <div class="metabox">
            <div class="r"><span class="k">No. Invoice</span><span class="v">{{ $invoice->invoice_number }}</span></div>
            <div class="r"><span class="k">Tanggal</span><span class="v">{{ $invoice->tgl_buat?->format('d M Y') ?? '-' }}</span></div>
            <div class="r"><span class="k">Jatuh Tempo</span><span class="v">{{ $invoice->tgl_tempo?->format('d M Y') ?? '-' }}</span></div>
            <div class="r"><span class="k">Tipe Cetak</span><span class="v">{{ $printType === 'all' ? $invoice->jenis_label : \App\Models\Invoice::TYPES[$printType] }}</span></div>
            @if($invoice->status === 'paid' && $invoice->tgl_pencairan)
            <div class="r"><span class="k">Tgl Cair</span><span class="v">{{ $invoice->tgl_pencairan?->format('d M Y') }}</span></div>
            @endif
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width:34px" class="c">No</th>
                <th style="width:78px">Tgl Kirim</th>
                <th>Keterangan</th>
                <th style="width:130px" class="r">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 1; @endphp
            @foreach($printItems as $it)
                @php $finalDo = $it->deliveryOrder; $do = $it->requestOrder ?? $finalDo?->requestOrder; @endphp
                <tr>
                    <td class="c">{{ $no++ }}</td>
                    <td>{{ $finalDo?->do_date?->format('d-m-y') ?? $do?->tgl_muat?->format('d-m-y') ?? $do?->order_date?->format('d-m-y') ?? '-' }}</td>
                    <td>
                        <b>{{ $finalDo?->do_number ?? $do?->do_number ?? 'DO' }}</b>
                        — <b>{{ $it->item_type === 'TR' ? 'Trucking' : 'Non-Trucking' }}</b>: {{ $it->description }}
                        <br>{{ $do?->muat ?? $finalDo?->origin ?? $do?->origin ?? '-' }} &rarr; {{ $do?->bongkar ?? $finalDo?->destination ?? $do?->destination ?? $do?->tujuan ?? '-' }}
                        <div class="det">
                            @if($do?->komoditi)Komoditas: {{ $do->komoditi }} · @endif
                            @if($do?->no_container)Container: {{ $do->no_container }} · @endif
                            @if($do?->no_seal)Seal: {{ $do->no_seal }} · @endif
                            @if($do?->no_pol)No.Pol: {{ $do->no_pol }} · @endif
                            @if($do?->jenis_truck)Armada: {{ $do->jenis_truck }}@endif
                        </div>
                    </td>
                    <td class="r">{{ number_format($it->jual, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totwrap">
        <div class="terbilang">
            <b>Terbilang:</b> {{ ucwords(trim(\App\Models\Invoice::terbilang($printGrand))) }} rupiah
        </div>
        <div class="totals">
            <div class="r"><span class="k">Subtotal</span><span>Rp {{ number_format($printSubtotal, 0, ',', '.') }}</span></div>
            @if($printPpn > 0)
            <div class="r"><span class="k">PPN {{ rtrim(rtrim(number_format($invoice->ppn_persen,2),'0'),'.') }}%</span><span>Rp {{ number_format($printPpn, 0, ',', '.') }}</span></div>
            @endif
            <div class="r grand"><span>Grand Total</span><span>Rp {{ number_format($printGrand, 0, ',', '.') }}</span></div>
        </div>
    </div>

    <div class="sign">
        <x-verified-signature :signature-qr="$signature['signatureQr']" :verification-url="$signature['verificationUrl']"
            :signer-name="$company['signatory_name']" :signer-title="$company['signatory_title']"
            :company-name="$company['name']" />
    </div>

    <div class="foot">
        {{ $company['name'] }}
        @if($company['address']) · {{ str_replace(["\r\n","\n"], ' ', $company['address']) }}@endif<br>
        @if($company['phone']){{ $company['phone'] }}@endif
        @if($company['website']) · {{ $company['website'] }}@endif
        @if($company['email']) · {{ $company['email'] }}@endif
    </div>

</div>
</body>
</html>
