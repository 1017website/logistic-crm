@props(['company', 'printOnly' => false])
@php
    $logo = $company['logo'] ?? null;
    $logoUrl = $logo && str_starts_with($logo, 'data:')
        ? $logo
        : ($logo ? \Illuminate\Support\Facades\Storage::url($logo) : null);
@endphp
@once
<style>
    .document-letterhead{width:100%;margin:0 0 6mm}.document-letterhead-table{width:100%;border-collapse:collapse;table-layout:fixed}.document-letterhead-table td{vertical-align:middle;padding:0}.document-letterhead-logo-cell{width:54%;padding-right:7mm!important;text-align:center}.document-letterhead-logo{max-width:93mm;max-height:25mm;object-fit:contain}.document-letterhead-fallback{font-size:18pt;font-weight:800;color:#d4ad4f;letter-spacing:.5pt}.document-letterhead-company{width:46%}.document-letterhead-name{font-family:"DejaVu Sans",Arial,sans-serif;font-size:13.8pt;line-height:1.05;font-weight:bold;font-style:italic;text-decoration:underline;margin-bottom:2pt;color:#111827;text-shadow:.18pt 0 #111827,-.18pt 0 #111827}.document-letterhead-detail{font-size:8.6pt;line-height:1.35;color:#111827}.document-letterhead-accent{width:100%;height:4.5pt;border-collapse:collapse;margin-top:5mm}.document-letterhead-accent td{padding:0;height:4.5pt}.document-letterhead-accent .gold{background:#d16308;width:29%}.document-letterhead-accent .mid{background:#9b7b45;width:22%}.document-letterhead-accent .blue{background:#0ea5c6;width:22%}.document-letterhead-accent .dark{background:#020617;width:27%}.document-letterhead.print-only{display:none}@media print{.document-letterhead.print-only{display:block!important}}
</style>
@endonce
<div class="document-letterhead {{ $printOnly ? 'print-only' : '' }}">
    <table class="document-letterhead-table">
        <tr>
            <td class="document-letterhead-logo-cell">
                @if($logoUrl)
                    <img src="{{ $logoUrl }}" class="document-letterhead-logo" alt="Logo">
                @else
                    <div class="document-letterhead-fallback">{{ strtoupper($company['name']) }}</div>
                @endif
            </td>
            <td class="document-letterhead-company">
                <div class="document-letterhead-name">{{ strtoupper($company['name']) }}</div>
                @if(!empty($company['address']))<div class="document-letterhead-detail">{!! nl2br(e($company['address'])) !!}</div>@endif
                @if(!empty($company['phone']))<div class="document-letterhead-detail">{{ $company['phone'] }}</div>@endif
                @if(!empty($company['website']) || !empty($company['email']))
                    <div class="document-letterhead-detail">{{ $company['website'] ?? '' }}@if(!empty($company['website']) && !empty($company['email'])) | @endif{{ $company['email'] ?? '' }}</div>
                @endif
            </td>
        </tr>
    </table>
    <table class="document-letterhead-accent"><tr><td class="gold"></td><td class="mid"></td><td class="blue"></td><td class="dark"></td></tr></table>
</div>
