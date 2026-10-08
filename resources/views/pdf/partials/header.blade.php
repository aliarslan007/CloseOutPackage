@php
    $rawSiteName = $siteInfo?->site_name ?? ($project?->name ?? 'Lomas Peak');
    $nameLength = mb_strlen($rawSiteName);
    if ($nameLength > 55) {
        $nameFontSize = '13px';
    } elseif ($nameLength > 32) {
        $nameFontSize = '15px';
    } else {
        $nameFontSize = '18px';
    }
@endphp
<table class="pdf-header">
    <tr>
        <td class="header-logo-cell header-logo-left">
            @if ($logoPath !== null)
                <img src="{{ $logoPath }}" alt="SoluComp logo">
            @endif
        </td>
        <td class="header-title-cell">
            <div class="header-site-name">SITE NAME</div>
            <div class="header-pkg-name" style="font-size: {{ $nameFontSize }};">{{ $rawSiteName }}</div>
        </td>
        <td class="header-logo-cell header-logo-right">
            @if ($partnerLogoPath !== null)
                <img src="{{ $partnerLogoPath }}" alt="Verizon logo">
            @endif
        </td>
    </tr>
</table>
