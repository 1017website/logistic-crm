<div class="toolbar" @if($isPdf ?? false) style="display:none" @endif>
    <button type="button" onclick="window.print()">Cetak</button>
    @foreach(['all' => 'Semua', 'TR' => 'Trucking', 'NTR' => 'Non-Trucking'] as $type => $label)
        @if($type === 'all' || $invoice->items->contains('item_type', $type))
            <a href="{{ route('invoices.print', [$invoice->id, 'type' => $type, 'document' => $documentMode]) }}"
               @if($printType === $type) class="active" aria-current="page" @endif>{{ $label }}</a>
        @endif
    @endforeach
    <a href="{{ route('invoices.pdf', [$invoice->id, 'type' => $printType, 'document' => $documentMode]) }}">Unduh PDF</a>
    <a href="{{ route('invoices.print', [$invoice->id, 'type' => $printType, 'document' => $documentMode === 'proforma' ? 'invoice' : 'proforma']) }}">{{ $documentMode === 'proforma' ? 'Versi Invoice' : 'Versi Pro Forma' }}</a>
    <a href="{{ route('invoices.show', $invoice) }}">Kembali</a>
</div>
