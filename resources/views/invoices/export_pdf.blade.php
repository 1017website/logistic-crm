<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { font-family: DejaVu Sans, sans-serif; color:#111827; font-size:9px; }
        h1 { font-size:18px; margin:0 0 4px; }
        .sub { color:#6b7280; margin-bottom:16px; }
        table { width:100%; border-collapse:collapse; margin-bottom:18px; }
        th, td { border:1px solid #d1d5db; padding:5px; vertical-align:top; }
        th { background:#111827; color:white; text-align:left; }
        .r { text-align:right; white-space:nowrap; }
        .invoice-title { background:#eef2ff; font-size:11px; font-weight:bold; }
        .total { background:#f3f4f6; font-weight:bold; }
    </style>
</head>
<body>
    <h1>Rekap Rincian Invoice</h1>
    <div class="sub">Customer: {{ $customer?->company_name ?? 'Semua Customer' }} · Dicetak {{ now()->format('d M Y H:i') }}</div>
    <table>
        <thead><tr><th>No Invoice / DO</th><th>Nama</th><th>Uraian</th><th>Jenis Truck</th><th class="r">Qty</th><th class="r">Harga</th><th class="r">Jumlah</th></tr></thead>
        <tbody>
        @forelse($invoices as $invoice)
            <tr><td colspan="7" class="invoice-title">{{ $invoice->invoice_number }} · {{ $invoice->customer?->company_name }} · {{ $invoice->status_label }} · Terbayar Rp {{ number_format($invoice->total_paid, 0, ',', '.') }} · Sisa Rp {{ number_format($invoice->outstanding, 0, ',', '.') }}</td></tr>
            @forelse($invoice->items as $item)
            <tr>
                <td>{{ $item->deliveryOrder?->do_number ?? $item->requestOrder?->do_number ?? '-' }}</td>
                <td>{{ $item->item_name }}</td><td>{{ $item->description }}</td><td>{{ $item->truck_type ?: '-' }}</td>
                <td class="r">{{ number_format((float)$item->quantity, 3, ',', '.') }}</td>
                <td class="r">Rp {{ number_format((float)$item->unit_price, 0, ',', '.') }}</td>
                <td class="r">Rp {{ number_format((float)$item->jual, 0, ',', '.') }}</td>
            </tr>
            @empty
            <tr>
                <td>-</td><td>{{ $invoice->jenis_label ?: 'Invoice' }}</td>
                <td>{{ $invoice->notes ?: 'Ringkasan invoice (rincian DO tidak tersedia)' }}</td>
                <td>-</td><td class="r">1</td>
                <td class="r">Rp {{ number_format((float)$invoice->total_jual, 0, ',', '.') }}</td>
                <td class="r">Rp {{ number_format((float)$invoice->total_jual, 0, ',', '.') }}</td>
            </tr>
            @endforelse
            <tr class="total"><td colspan="6" class="r">Grand Total {{ $invoice->invoice_number }}</td><td class="r">Rp {{ number_format((float)$invoice->grand_total, 0, ',', '.') }}</td></tr>
        @empty
            <tr><td colspan="7" style="text-align:center">Tidak ada invoice untuk filter ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
