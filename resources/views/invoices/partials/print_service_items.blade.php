<table class="items">
    <colgroup><col style="width:9mm"><col style="width:22mm"><col style="width:121mm"><col style="width:30mm"></colgroup>
    <thead><tr><th class="number" style="width:9mm">No</th><th class="date" style="width:22mm">Tgl Kirim</th><th style="width:121mm">Keterangan</th><th class="amount" style="width:30mm">Jumlah (Rp)</th></tr></thead>
    @if($serviceType === 'TR')
    <tbody>
        @foreach($printItems as $it)
        @php
            $finalDo = $it->deliveryOrder;
            $order = $it->requestOrder ?? $finalDo?->requestOrder;
            $shipDate = $finalDo?->do_date?->format('d-m-y') ?? $order?->tgl_muat?->format('d-m-y') ?? $order?->order_date?->format('d-m-y') ?? '-';
        @endphp
        <tr class="tr-do-row">
            <td class="number">{{ $loop->iteration }}</td>
            <td class="date">{{ $shipDate }}</td>
            <td class="description">@include('invoices.partials.work_description', ['item' => $it, 'showItemDetails' => true])</td>
            <td class="tr-amount"><table class="amount-line"><tr><td class="currency-label">Rp.</td><td class="amount-value">{{ number_format($it->jual, 0, ',', '.') }}</td></tr></table></td>
        </tr>
        @endforeach
    </tbody>
    @else
    @foreach($ntrGroups as $group)
        @php
            $header = $group['header'];
            $finalDo = $header->deliveryOrder;
            $order = $header->requestOrder ?? $finalDo?->requestOrder;
            $shipDate = $finalDo?->do_date?->format('d-m-y') ?? $order?->tgl_muat?->format('d-m-y') ?? $order?->order_date?->format('d-m-y') ?? '-';
        @endphp
        <tbody class="ntr-do-group">
            <tr class="ntr-do-header">
                <td class="number" rowspan="{{ $group['items']->count() + 1 }}">{{ $loop->iteration }}</td>
                <td class="date">{{ $shipDate }}</td>
                <td class="description"><b class="do-number">{{ $finalDo?->do_number ?? $order?->do_number ?? 'DO' }}</b><br>@include('invoices.partials.work_description', ['item' => $header])</td>
                <td></td>
            </tr>
            @foreach($group['items'] as $it)
            <tr class="ntr-work-item">
                <td class="date">{{ $shipDate }}</td>
                <td>* {{ $it->item_name ?: $it->description ?: 'Non-Trucking' }}@if($it->item_name && $it->description && $it->description !== $it->item_name) — {{ $it->description }}@endif</td>
                <td class="money"><span class="currency">Rp.</span>{{ number_format($it->jual, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    @endforeach
    @endif
</table>
