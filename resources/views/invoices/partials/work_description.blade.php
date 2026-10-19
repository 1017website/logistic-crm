@php
    $details = $item->workDescriptionDetails();
    $showItemDetails = $showItemDetails ?? false;
    $itemName = trim((string) $item->item_name);
    $itemDescription = trim((string) $item->description);
@endphp
@if(strcasecmp($itemName, 'Stapel') === 0)
    <div class="item-description">* {!! nl2br(e($itemDescription ?: $itemName)) !!}</div>
@else
@if($showItemDetails)
    <div class="item-name"><b>{{ $itemName ?: 'Trucking' }}</b></div>
    @if($details !== [] && $itemDescription !== '' && $itemDescription !== $itemName)
    <div class="item-description">{!! nl2br(e($itemDescription)) !!}</div>
    @endif
@endif
@if($details === [])
    @if(! $showItemDetails || $itemDescription !== $itemName)
    {{ $item->description }}
    @endif
@else
    <div class="work-description" style="line-height:1.45">
        <div><b>Muat:</b> {{ $details['muat'] }}</div>
        <div><b>Bongkar:</b> {{ $details['bongkar'] }}</div>
        <div><b>Depo:</b> {{ $details['depo'] }} &middot; <b>Tujuan:</b> {{ $details['tujuan'] }} &middot; <b>Komoditas:</b> {{ $details['komoditas'] }}</div>
        <div><b>No. Container:</b> {{ $details['container'] }} &middot; <b>No. Seal:</b> {{ $details['seal'] }}</div>
        <div><b>No. Pol:</b> {{ $details['no_pol'] }} &middot; <b>Armada:</b> {{ $details['armada'] }}</div>
    </div>
@endif
@endif
