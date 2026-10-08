@php($details = $item->workDescriptionDetails())
@if($details === [])
    {{ $item->description }}
@else
    <div class="work-description" style="line-height:1.45">
        <div><b>Muat:</b> {{ $details['muat'] }}</div>
        <div><b>Bongkar:</b> {{ $details['bongkar'] }}</div>
        <div><b>Depo:</b> {{ $details['depo'] }} &middot; <b>Tujuan:</b> {{ $details['tujuan'] }} &middot; <b>Komoditas:</b> {{ $details['komoditas'] }}</div>
        <div><b>No. Container:</b> {{ $details['container'] }} &middot; <b>No. Seal:</b> {{ $details['seal'] }}</div>
        <div><b>No. Pol:</b> {{ $details['no_pol'] }} &middot; <b>Armada:</b> {{ $details['armada'] }}</div>
    </div>
@endif
