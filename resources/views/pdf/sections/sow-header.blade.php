{{--
    Shared SOW PDF header — used on every page (pages 1…N).

    Centered title block:
      line 1: package name "Fiber Feasibility Report" (hardcoded)
      line 2: report site name (dynamic, falls back to project, then title)

    Both lines share the same navy color and tracking so the visual reads as
    a single title block per the client reference PDF.
--}}
@php
    $headerSiteName = $report->site_name ?: ($project?->name ?: $report->title);
    $headerLen = mb_strlen((string) $headerSiteName);
    $isCover = $isCover ?? false;
    $headerFontSize = $isCover
        ? ($headerLen > 70 ? '19px' : ($headerLen > 40 ? '21px' : '24px'))
        : ($headerLen > 55 ? '9.5px' : ($headerLen > 38 ? '10.5px' : '12px'));
@endphp
<div class="sow-pdf-page-header">
    <div class="sow-pdf-page-header-eyebrow">Fiber Feasibility Report</div>
    <div class="sow-pdf-page-header-title" style="font-size: {{ $headerFontSize }};">{{ $headerSiteName }}</div>
</div>
