@php
    $rawScope = (string) ($siteInfo?->scope_of_work ?? '');
    $rawNotes = (string) ($siteInfo?->site_access_notes ?? '');

    $hasScope = trim($rawScope) !== '';
    $hasNotes = trim($rawNotes) !== '';

    $scopeContent = $hasScope ? preg_replace("/(\r?\n){3,}/", "\n\n", $rawScope) : 'Empty';
    $notesContent = $hasNotes ? preg_replace("/(\r?\n){3,}/", "\n\n", $rawNotes) : 'Empty';

    $scopeLen = $hasScope ? mb_strlen($rawScope) : 0;
    $notesLen = $hasNotes ? mb_strlen($rawNotes) : 0;
    $totalLen = $scopeLen + $notesLen;
    $newlines = substr_count($rawScope, "\n") + substr_count($rawNotes, "\n");
    $effectiveLen = $totalLen + ($newlines * 70);

    if ($effectiveLen > 5600) {
        $fontSize = '10px';
        $lineHeight = '1.26';
        $cellPadding = '4px 7px';
        $labelFontSize = '10.5px';
        $bannerFontSize = '12.5px';
        $bannerPadding = '3.5px 7px';
        $titleMarginBottom = '10px';
        $labelWidth = '18%';
        $contentWidth = '82%';
    } elseif ($effectiveLen > 3500) {
        $fontSize = '11px';
        $lineHeight = '1.30';
        $cellPadding = '4.5px 8px';
        $labelFontSize = '11.5px';
        $bannerFontSize = '13px';
        $bannerPadding = '4px 8px';
        $titleMarginBottom = '10px';
        $labelWidth = '18%';
        $contentWidth = '82%';
    } elseif ($effectiveLen > 2000) {
        $fontSize = '12px';
        $lineHeight = '1.35';
        $cellPadding = '5px 8px';
        $labelFontSize = '12px';
        $bannerFontSize = '13.5px';
        $bannerPadding = '4.5px 8px';
        $titleMarginBottom = '12px';
        $labelWidth = '19%';
        $contentWidth = '81%';
    } elseif ($effectiveLen > 900) {
        $fontSize = '13px';
        $lineHeight = '1.38';
        $cellPadding = '6px 9px';
        $labelFontSize = '12.5px';
        $bannerFontSize = '14px';
        $bannerPadding = '5px 8px';
        $titleMarginBottom = '12px';
        $labelWidth = '19%';
        $contentWidth = '81%';
    } else {
        $fontSize = '14px';
        $lineHeight = '1.4';
        $cellPadding = '6px 9px';
        $labelFontSize = '13px';
        $bannerFontSize = '14px';
        $bannerPadding = '5px 8px';
        $titleMarginBottom = '14px';
        $labelWidth = '20%';
        $contentWidth = '80%';
    }
@endphp
<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package,
    ])

    <div class="page-title" style="margin-bottom: {{ $titleMarginBottom }};">Scope of Work / Site Access</div>

    <table class="table-custom">
        <thead>
            <tr>
                <th colspan="2" class="table-banner-black"
                    style="background-color: #000000; color: #ffffff; font-weight: 900; text-align: center; font-size: {{ $bannerFontSize }}; text-transform: uppercase; padding: {{ $bannerPadding }}; border: 1px solid #000000; letter-spacing: 0.5px;">
                    SCOPE OF WORK AND SITE ACCESS</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th scope="row" class="label-cell" style="width: {{ $labelWidth }}; vertical-align: top; white-space: nowrap; border-right: none !important; font-size: {{ $labelFontSize }}; padding: {{ $cellPadding }};">SCOPE OF WORK:</th>
                <td style="width: {{ $contentWidth }}; vertical-align: top; line-height: {{ $lineHeight }}; font-size: {{ $fontSize }}; padding: {{ $cellPadding }}; border-left: none !important; word-break: break-word; overflow-wrap: break-word; white-space: normal;">
                    {{ $scopeContent }}
                </td>
            </tr>
            <tr>
                <th scope="row" class="label-cell" style="width: {{ $labelWidth }}; vertical-align: top; white-space: nowrap; border-right: none !important; font-size: {{ $labelFontSize }}; padding: {{ $cellPadding }};">SITE ACCESS NOTES:</th>
                <td style="width: {{ $contentWidth }}; vertical-align: top; line-height: {{ $lineHeight }}; font-size: {{ $fontSize }}; padding: {{ $cellPadding }}; border-left: none !important; word-break: break-word; overflow-wrap: break-word; white-space: pre-line;">
                    {{ $notesContent }}
                </td>
            </tr>
        </tbody>
    </table>

    @include('pdf.partials.footer', ['page' => $page])
</div>
