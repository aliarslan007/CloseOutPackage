<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Scope of Work - {{ $report->title }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@500;600;700;800&display=swap');

        @page {
            size: A4 portrait;
            margin: 0;
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            color-adjust: exact !important;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #000000;
            margin: 0;
            padding: 0;
            font-size: 13px;
            background: #ffffff;
            line-height: 1.35;
            letter-spacing: -0.01em;
        }

        .page {
            position: relative;
            width: 210mm;
            height: 297mm;
            page-break-after: always;
            padding: 14px 28px 44px 28px;
            box-sizing: border-box;
            background: #ffffff;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Legacy 3-column logo header kept only for the cover page footer to
           retain the SoluComp logo placement. */
        table.pdf-header {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 12px;
            table-layout: fixed;
        }

        table.pdf-header td {
            border: none;
            padding: 0;
            vertical-align: middle;
        }

        table.pdf-header td.header-logo-left {
            width: 130px;
            text-align: left;
            vertical-align: top;
        }

        table.pdf-header td.header-logo-left img {
            height: 36px;
            width: auto;
            object-fit: contain;
            display: block;
        }

        table.pdf-header td.header-logo-right {
            width: 130px;
            text-align: right;
            vertical-align: top;
        }

        table.pdf-header td.header-logo-right img {
            height: 26px;
            width: auto;
            object-fit: contain;
            display: block;
            margin-left: auto;
        }

        table.pdf-header td.header-title-cell {
            text-align: center;
            vertical-align: middle;
            padding: 0 10px;
        }

        /* Global Footer */
        .pdf-footer-wrapper {
            position: absolute;
            bottom: 20px;
            left: 28px;
            right: 28px;
        }

        .pdf-footer-line {
            width: 100%;
            height: 2px;
            background-color: #000000 !important;
            margin-top: 6px;
        }

        .pdf-footer {
            width: 100%;
            display: table;
            table-layout: fixed;
        }

        .pdf-footer-left {
            display: table-cell;
            width: 25%;
            vertical-align: middle;
            font-size: 10px;
            color: #111111;
            text-align: left;
        }

        .pdf-footer-center {
            display: table-cell;
            width: 50%;
            text-align: center;
            vertical-align: middle;
        }

        .pdf-footer-right {
            display: table-cell;
            width: 25%;
            text-align: right;
            vertical-align: middle;
            font-size: 10px;
            color: #111111;
        }

        .proprietary-badge {
            display: inline-block;
            background-color: #ff0000 !important;
            color: #000000 !important;
            border: 1px solid #000000;
            padding: 4px 24px;
            letter-spacing: 0.3px;
            text-align: center;
        }

        .proprietary-badge .proprietary-title {
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
        }

        .proprietary-badge .powered-by {
            margin-top: 1px;
            font-size: 8px;
            font-weight: 700;
            text-transform: none;
        }

        .proprietary-badge .powered-by a {
            color: #000000 !important;
            text-decoration: none;
            display: inline-block;
        }

        .proprietary-badge .powered-by a:hover {
            text-decoration: underline;
        }

        /* Shared page header — centered "Fiber Feasibility Report" + site name.
           Used on every page via sections/sow-header.blade.php. */
        .sow-pdf-page-header {
            text-align: center;
            margin: 0 0 14px 0;
            padding: 4px 0 8px 0;
        }

        /* Not bold on the inner pages — the cover keeps its own heavier
           override via .sow-cover-header. */
        .sow-pdf-page-header-eyebrow {
            font-size: 22px;
            font-weight: 400;
            letter-spacing: 0.4px;
            color: #2c3e50;
            margin-bottom: 4px;
        }

        /* Not bold on the inner pages — the cover keeps its heavier override
           via .sow-cover-header. */
        .sow-pdf-page-header-title {
            font-size: 12px;
            font-weight: 400;
            letter-spacing: 1.4px;
            color: #2c3e50;
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        /* Section Headings (Summary, Site access notes, Survey Checklist) —
           consistent font family, size (16px), soft normal black (#222222), and thin 1px horizontal line. */
        .section-heading-wrapper,
        .summary-heading-wrapper {
            margin: 8px 0 4px 0;
        }

        .section-heading-title,
        .summary-heading-title {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #222222;
            letter-spacing: 0.5px;
            margin: 0 0 3px 0;
            display: block;
        }

        .section-heading-line,
        .summary-heading-line {
            width: 100%;
            height: 1px;
            background-color: #222222;
            border: none;
            margin: 0 0 7px 0;
        }

        .page-title {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 16px;
            font-weight: 700;
            color: #222222;
            letter-spacing: 0.5px;
            margin: 8px 0 4px 0;
            padding-bottom: 3px;
            border-bottom: 1px solid #222222 !important;
            display: block;
            width: 100%;
        }

        /* Survey Checklist table — SVG check boxes with full-width
           explanation rows. */
        table.checklist-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000000;
            table-layout: fixed;
        }

        table.checklist-table td,
        table.checklist-table th {
            border: 1px solid #000000;
            padding: 8px 10px;
            font-size: 13.5px;
            line-height: 1.35;
            vertical-align: middle;
            color: #000000;
            font-weight: 400;
            text-align: left;
        }

        table.checklist-table th.checklist-label {
            font-weight: 400;
            text-align: left;
        }

        table.checklist-table td.checklist-choice {
            text-align: center;
            white-space: nowrap;
            padding: 6px 4px;
            font-size: 13.5px;
            font-weight: 400;
        }

        .explain-text {
            color: #ff0000;
            font-weight: 800;
            font-size: 13px;
        }

        .explain-value {
            color: #000000;
            font-size: 13px;
            line-height: 1.4;
        }

        /* Project Data & Survey Data tables — matches reference image.png */
        table.summary-section {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 9px;
            border: 1.5px solid #000000;
            table-layout: fixed;
        }

        table.summary-section tr.summary-banner td,
        table.summary-section tr.summary-banner th {
            background-color: #ff0000 !important;
            color: #000000 !important;
            font-weight: 800;
            font-size: 13.5px;
            text-align: center;
            padding: 4px 8px;
            border: 1px solid #000000;
            letter-spacing: 0.4px;
        }

        table.summary-section td,
        table.summary-section th {
            border: 1px solid #000000;
            padding: 3.5px 8px;
            font-size: 12px;
            box-sizing: border-box;
            vertical-align: middle;
        }

        table.summary-section td.summary-label,
        table.summary-section th.summary-label {
            background-color: #000000 !important;
            color: #ffffff !important;
            font-weight: 700;
            font-size: 12px;
            text-align: right;
            white-space: nowrap;
            vertical-align: middle;
            padding: 3.5px 8px;
        }

        table.summary-section td.summary-value {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-weight: 500;
            font-size: 12px;
            line-height: 1.35;
            padding: 3.5px 8px;
            overflow-wrap: anywhere;
            word-break: break-word;
            vertical-align: middle;
        }

        /* Site Access Notes Box — matches reference image.png */
        .site-access-notes-box {
            border: 1px solid #777777;
            background-color: #ffffff;
            padding: 8px 10px;
            font-size: 12px;
            line-height: 1.4;
            font-weight: 400;
            color: #000000;
            white-space: pre-line;
            word-break: break-word;
            overflow-wrap: anywhere;
            box-sizing: border-box;
            min-height: 50px;
        }

        /* Cover Page (Page 1) — five stacked blocks separated by one equal
           measure of whitespace: SoluComp logo, hero header, site photo, meta
           tables, and the InSite Telecom logo. The page is a flex column and
           the body uses space-evenly so the gaps stay identical regardless of
           how tall the site name or photo turns out. */
        .page.sow-cover-page {
            display: flex;
            flex-direction: column;
            /* Reserve the bottom band occupied by the absolutely positioned footer. */
            padding-bottom: 24mm;
        }

        .sow-cover-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-evenly;
            align-items: center;
            width: 100%;
            min-height: 0;
        }

        .sow-cover-logo {
            text-align: center;
            line-height: 1;
        }

        .sow-cover-logo img {
            height: 60px;
            width: auto;
            object-fit: contain;
            display: inline-block;
        }

        .sow-cover-titles {
            text-align: center;
            margin-top: 48px;
        }

        .sow-cover-title-primary {
            font-size: 22px;
            font-weight: 700;
            color: #3f3f3f;
            letter-spacing: 0.4px;
            line-height: 1.45;
        }

        .sow-cover-title-secondary {
            font-size: 22px;
            font-weight: 700;
            color: #3f3f3f;
            letter-spacing: 0.4px;
            line-height: 1.45;
        }

        .sow-cover-photo {
            text-align: center;
        }

        .sow-cover-photo img {
            width: 130mm;
            max-height: 90mm;
            object-fit: contain;
            display: inline-block;
        }

        .sow-cover-no-photo {
            width: 130mm;
            height: 90mm;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-size: 12px;
            font-weight: 600;
        }

        /* Cover hero header — sits below the SoluComp logo and above the site
           photo, constrained to the photo's width so it centers over it. */
        .sow-cover-header {
            width: 175mm;
            max-width: 100%;
            margin: 0 auto;
            text-align: center;
        }

        .sow-cover-header .sow-pdf-page-header-eyebrow {
            font-size: 26px;
            font-weight: 800;
        }

        /* Cover-only title bump — the hero site name wraps onto a second line
           if long so the complete name is visible, matching user requirements. */
        .sow-cover-header .sow-pdf-page-header-title {
            font-size: 24px;
            font-weight: 800;
            white-space: normal !important;
            overflow: visible !important;
            text-overflow: clip !important;
            word-wrap: break-word;
            line-height: 1.25;
            max-width: 100%;
        }

        .sow-cover-meta {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: stretch;
            gap: 6mm;
        }

        table.sow-cover-meta-table {
            width: calc(50% - 3mm);
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.sow-cover-meta-table td,
        table.sow-cover-meta-table th {
            border: 1.5px solid #000000;
            padding: 7px 10px;
            font-size: 13.5px;
            line-height: 1.25;
            vertical-align: middle;
            color: #000000;
            box-sizing: border-box;
            height: 35px;
        }

        table.sow-cover-meta-table td.sow-cover-meta-label,
        table.sow-cover-meta-table th.sow-cover-meta-label {
            background-color: #000000 !important;
            color: #ffffff !important;
            font-weight: 700;
            white-space: nowrap;
            text-align: right;
            font-size: 13.5px;
            padding: 7px 10px;
        }

        table.sow-cover-meta-table td.sow-cover-meta-value {
            background-color: #ffffff !important;
            color: #000000 !important;
            font-weight: 700;
            font-size: 13.5px;
            padding: 7px 10px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 0;
        }

        /* InSite Telecom mark — the fifth cover block, sized by width so the
           wide 3:1 artwork keeps its proportions. */
        .sow-cover-sow-logo {
            text-align: center;
            line-height: 1;
        }

        .sow-cover-sow-logo img {
            width: 68mm;
            height: auto;
            object-fit: contain;
            display: inline-block;
        }

        /* Photo Pages — 2 photos per page matching reference image */
        .photo-page {
            /* 18mm bottom padding reserves the absolutely-positioned footer band,
               so content stays clear of it even if a group heading wraps to a
               second line. */
            padding: 10mm 15mm 18mm 15mm;
            position: relative;
            /* Column flex so the body absorbs whatever height the optional
               group heading takes — no hard-coded millimetres to keep in sync. */
            display: flex;
            flex-direction: column;
        }

        .photo-page-heading {
            margin: 0 0 6px 0;
        }

        .photo-page-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 7mm;
            min-height: 0;
        }

        .sow-photo-card {
            width: 100%;
            /* Grow to share the page evenly, but cap at the two-card height so a
               lone photo on a section's last page can never stretch tall. */
            flex: 1 1 0;
            min-height: 0;
            max-height: 114mm;
            border: 1px solid #bfbfbf;
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            box-sizing: border-box;
        }

        .photo-inner-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            max-width: 100%;
            max-height: 100%;
        }

        .photo-inner-wrapper img {
            display: block;
            max-width: 100%;
            max-height: 113mm;
            width: auto;
            height: auto;
            object-fit: contain;
        }

        .photo-svg-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }

        .photo-unavailable {
            height: 100%;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 12px;
        }
    </style>
</head>

<body>

    {{-- ========================================== --}}
    {{-- PAGE 1: COVER PAGE                         --}}
    {{-- ========================================== --}}
    <div class="page sow-cover-page">
        {{-- Five blocks, evenly spaced by .sow-cover-body: logo, hero header,
           site photo, meta tables, InSite Telecom logo. --}}
        <div class="sow-cover-body">
        {{-- 1. SoluComp Logo (top center) --}}
        <div class="sow-cover-logo">
            @if ($logoDataUri)
                <img src="{{ $logoDataUri }}" alt="SoluComp Logo">
            @endif
        </div>

        {{-- 2. Cover hero header — directly below the SoluComp logo --}}
        <div class="sow-cover-header">
            @include('pdf.sections.sow-header', ['report' => $report, 'project' => $project, 'isCover' => true])
        </div>

        {{-- 3. Site Photo --}}
        <div class="sow-cover-photo">
            @if ($coverPhotoDataUri)
                <img src="{{ $coverPhotoDataUri }}" alt="Site">
            @else
                <div class="sow-cover-no-photo">Site photo not available</div>
            @endif
        </div>

        {{-- 4. Bottom Meta Tables (left + right, matching client reference layout) --}}
        @php
            $coverSiteName = $report->site_name ?: ($project?->name ?: 'N/A');
            $siteNameLen = mb_strlen((string) $coverSiteName);
            $siteNameFontSize = $siteNameLen > 45 ? '10.5px' : ($siteNameLen > 30 ? '11.5px' : ($siteNameLen > 24 ? '12.5px' : '13.5px'));

            $coverProjectId = $report->ps_number ?: ($project?->ps_number ?: 'N/A');
            $projectIdLen = mb_strlen((string) $coverProjectId);
            $projectIdFontSize = $projectIdLen > 30 ? '11px' : ($projectIdLen > 20 ? '12px' : '13.5px');
        @endphp
        <div class="sow-cover-meta">
            <table class="sow-cover-meta-table">
                <colgroup>
                    <col style="width: 27%;">
                    <col style="width: 73%;">
                </colgroup>
                <tr>
                    <th scope="row" class="sow-cover-meta-label">Site Name:</th>
                    <td class="sow-cover-meta-value" style="font-size: {{ $siteNameFontSize }};">{{ $coverSiteName }}</td>
                </tr>
                <tr>
                    <th scope="row" class="sow-cover-meta-label">Project ID:</th>
                    <td class="sow-cover-meta-value" style="font-size: {{ $projectIdFontSize }};">{{ $coverProjectId }}</td>
                </tr>
            </table>
            <table class="sow-cover-meta-table">
                <colgroup>
                    <col style="width: 35%;">
                    <col style="width: 65%;">
                </colgroup>
                <tr>
                    <th scope="row" class="sow-cover-meta-label">Report Date:</th>
                    <td class="sow-cover-meta-value">
                        {{ $report->report_date ? \Carbon\Carbon::parse($report->report_date)->format('n/j/y') : now()->format('n/j/y') }}
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="sow-cover-meta-label">Fiber provider:</th>
                    <td class="sow-cover-meta-value">{{ $report->fiber_provider ?: 'VZB' }}</td>
                </tr>
            </table>
        </div>

        {{-- 5. InSite Telecom logo — sits after the tables --}}
        <div class="sow-cover-sow-logo">
            @if ($sowLogoDataUri)
                <img src="{{ $sowLogoDataUri }}" alt="InSite Telecom Logo">
            @endif
        </div>
        </div>

        {{-- Footer --}}
        <div class="pdf-footer-wrapper">
            <div class="pdf-footer">
                <div class="pdf-footer-left">
                    {{ $revisionLabel }} {{ $revisionDate }}
                </div>
                <div class="pdf-footer-center">
                    <div class="proprietary-badge">
                        <div class="proprietary-title">Verizon Wireless Proprietary Information</div>
                        <div class="powered-by">
                            Prepared by <a href="https://www.solucomp.com" target="_blank" rel="noopener noreferrer">www.SoluComp.com</a>
                        </div>
                    </div>
                </div>
                <div class="pdf-footer-right">
                    1 ({{ $totalPages }})
                </div>
            </div>
            <div class="pdf-footer-line"></div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- PAGE 2: SUMMARY DATA (Project & Survey)    --}}
    {{-- ========================================== --}}
    <div class="page">
        @include('pdf.sections.sow-header', ['report' => $report, 'project' => $project])

        <div class="section-heading-wrapper">
            <div class="section-heading-title">Summary</div>
            <div class="section-heading-line"></div>
        </div>

        {{-- Table 1: Project Data — matches reference image.png --}}
        <table class="summary-section">
            <colgroup>
                <col style="width: 21%;">
                <col style="width: 79%;">
            </colgroup>
            <thead>
                <tr class="summary-banner">
                    <th colspan="2" scope="col">Project Data</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row" class="summary-label">Site Address:</th>
                    <td class="summary-value">{{ $report->site_address ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Site City:</th>
                    <td class="summary-value">{{ $report->site_city ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Site County:</th>
                    <td class="summary-value">
                        {{ $report->site_county ? (str_ends_with(strtolower($report->site_county), 'county') ? $report->site_county : $report->site_county . ' County') : '' }}
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Coordinates:</th>
                    <td class="summary-value">
                        @if ($report->latitude !== null && $report->longitude !== null)
                            {{ $report->latitude }}, {{ $report->longitude }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Project Description:</th>
                    <td class="summary-value">{{ $report->project_description ?: ($project?->description ?: '') }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Table 2: Survey Data — matches reference image.png --}}
        @php
            $fieldSurveyDate = $report->field_survey_date
                ? \Carbon\Carbon::parse($report->field_survey_date)->format('n/j/y')
                : '';
        @endphp
        <table class="summary-section">
            <colgroup>
                <col style="width: 19%;">
                <col style="width: 31%;">
                <col style="width: 19%;">
                <col style="width: 31%;">
            </colgroup>
            <thead>
                <tr class="summary-banner">
                    <th colspan="4" scope="col">Survey Data</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row" class="summary-label">Field Survey date:</th>
                    <td class="summary-value">{{ $fieldSurveyDate }}</td>
                    <th scope="row" class="summary-label">PS #:</th>
                    <td class="summary-value">{{ $report->ps_number ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Fiber provider:</th>
                    <td class="summary-value">{{ $report->fiber_provider ?: 'VZB' }}</td>
                    <th scope="row" class="summary-label">Survey contractor:</th>
                    <td class="summary-value">{{ $report->survey_contractor_name ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Provider contact:</th>
                    <td class="summary-value">{{ $report->provider_contact_name ?: '' }}</td>
                    <th scope="row" class="summary-label">Survey contact:</th>
                    <td class="summary-value">{{ $report->survey_contact_name ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Contact phone:</th>
                    <td class="summary-value">{{ $report->provider_contact_phone ?: '' }}</td>
                    <th scope="row" class="summary-label">Contact phone:</th>
                    <td class="summary-value">{{ $report->survey_contact_phone ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Contact email:</th>
                    <td class="summary-value">{{ $report->provider_contact_email ?: '' }}</td>
                    <th scope="row" class="summary-label">Contact email:</th>
                    <td class="summary-value">{{ $report->survey_contact_email ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Demarcation:</th>
                    <td class="summary-value" colspan="3">{{ $report->demarcation ?: '' }}</td>
                </tr>
                <tr>
                    <th scope="row" class="summary-label">Scope of work:</th>
                    <td class="summary-value" colspan="3">{{ $report->scope_of_work_description ?: '' }}</td>
                </tr>
            </tbody>
        </table>

        {{-- Site access notes — matches reference image.png --}}
        <div class="section-heading-wrapper" style="margin-top: 10px;">
            <div class="section-heading-title">Site access notes</div>
            <div class="section-heading-line"></div>
        </div>
        <div class="site-access-notes-box">{{ $report->site_access_notes ?: '' }}</div>

        {{-- Footer --}}
        <div class="pdf-footer-wrapper">
            <div class="pdf-footer">
                <div class="pdf-footer-left">
                    {{ $revisionLabel }} {{ $revisionDate }}
                </div>
                <div class="pdf-footer-center">
                    <div class="proprietary-badge">
                        <div class="proprietary-title">Verizon Wireless Proprietary Information</div>
                        <div class="powered-by">
                            Prepared by <a href="https://www.solucomp.com" target="_blank" rel="noopener noreferrer">www.SoluComp.com</a>
                        </div>
                    </div>
                </div>
                <div class="pdf-footer-right">
                    2 ({{ $totalPages }})
                </div>
            </div>
            <div class="pdf-footer-line"></div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- PAGE 3: SURVEY CHECKLIST                   --}}
    {{-- ========================================== --}}
    <div class="page">
        @include('pdf.sections.sow-header', ['report' => $report, 'project' => $project])

        <div class="section-heading-wrapper" style="margin-top: 6px; margin-bottom: 8px;">
            <div class="section-heading-title">Survey Checklist</div>
            <div class="section-heading-line"></div>
        </div>

        <table class="checklist-table">
            <colgroup>
                <col style="width: 70%;">
                <col style="width: 10%;">
                <col style="width: 10%;">
                <col style="width: 10%;">
            </colgroup>
            <tbody>
                @forelse ($checklist as $item)
                    @php
                        $response = $item['response'] ?? null;
                        $isYes = $response === 'yes';
                        $isNo = $response === 'no';
                        $isNotApplicable = $response === 'n_a';
                        $explanation = trim((string) ($item['explanation'] ?? ''));
                    @endphp
                    <tr>
                        <th scope="row" class="checklist-label">
                            {{ $item['label'] ?? '' }}
                            @if (!empty($item['is_required']))
                                <span style="color: #dc2626;">*</span>
                            @endif
                        </th>
                        <td class="checklist-choice">
                            <svg width="18" height="18" viewBox="0 0 16 16"
                                style="vertical-align: middle; margin-right: 5px; display: inline-block;">
                                <rect x="0.75" y="0.75" width="14.5" height="14.5" fill="#ffffff" stroke="#000000"
                                    stroke-width="1.5" />
                                @if ($isYes)
                                    <line x1="0.75" y1="0.75" x2="15.25" y2="15.25" stroke="#000000"
                                        stroke-width="1.5" />
                                    <line x1="15.25" y1="0.75" x2="0.75" y2="15.25" stroke="#000000"
                                        stroke-width="1.5" />
                                @endif
                            </svg> YES
                        </td>
                        <td class="checklist-choice">
                            <svg width="18" height="18" viewBox="0 0 16 16"
                                style="vertical-align: middle; margin-right: 5px; display: inline-block;">
                                <rect x="0.75" y="0.75" width="14.5" height="14.5" fill="#ffffff"
                                    stroke="{{ $isNo ? '#ff0000' : '#000000' }}" stroke-width="1.5" />
                                @if ($isNo)
                                    <line x1="0.75" y1="0.75" x2="15.25" y2="15.25" stroke="#ff0000"
                                        stroke-width="1.5" />
                                    <line x1="15.25" y1="0.75" x2="0.75" y2="15.25" stroke="#ff0000"
                                        stroke-width="1.5" />
                                @endif
                            </svg> NO
                        </td>
                        <td class="checklist-choice">
                            <svg width="18" height="18" viewBox="0 0 16 16"
                                style="vertical-align: middle; margin-right: 5px; display: inline-block;">
                                <rect x="0.75" y="0.75" width="14.5" height="14.5" fill="#ffffff" stroke="#000000"
                                    stroke-width="1.5" />
                                @if ($isNotApplicable)
                                    <line x1="0.75" y1="0.75" x2="15.25" y2="15.25" stroke="#000000"
                                        stroke-width="1.5" />
                                    <line x1="15.25" y1="0.75" x2="0.75" y2="15.25" stroke="#000000"
                                        stroke-width="1.5" />
                                @endif
                            </svg> N/A
                        </td>
                    </tr>
                    @if ($explanation !== '')
                        <tr>
                            <td colspan="4" style="background-color: #ffffff; padding: 7px 10px;">
                                <span class="explain-text" style="margin-right: 6px;">EXPLANATION:</span>
                                <span class="explain-value">{{ $explanation }}</span>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="4" style="text-align: center; color: #666666; font-style: italic;">
                            No survey checklist questions configured for this report.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Footer --}}
        <div class="pdf-footer-wrapper">
            <div class="pdf-footer">
                <div class="pdf-footer-left">
                    {{ $revisionLabel }} {{ $revisionDate }}
                </div>
                <div class="pdf-footer-center">
                    <div class="proprietary-badge">
                        <div class="proprietary-title">Verizon Wireless Proprietary Information</div>
                        <div class="powered-by">
                            Prepared by <a href="https://www.solucomp.com" target="_blank" rel="noopener noreferrer">www.SoluComp.com</a>
                        </div>
                    </div>
                </div>
                <div class="pdf-footer-right">
                    3 ({{ $totalPages }})
                </div>
            </div>
            <div class="pdf-footer-line"></div>
        </div>
    </div>

    {{-- ========================================== --}}
    {{-- PAGES 4+: ANNOTATED FIELD PHOTOGRAPHS      --}}
    {{-- 2 photos per page, grouped into named       --}}
    {{-- sections (Site Plan, Fiber Pathway, …).     --}}
    {{-- ========================================== --}}
    @foreach ($photoPages as $pageIndex => $photoPage)
        @php
            $currentPageNumber = 4 + $pageIndex;
        @endphp
        <div class="page photo-page">
            @include('pdf.sections.sow-header', ['report' => $report, 'project' => $project])

            {{-- Group heading. Absent for reports that use no groups, so their
               output stays identical to the pre-grouping layout. --}}
            @if (!empty($photoPage['heading']))
                <div class="section-heading-wrapper photo-page-heading">
                    <div class="section-heading-title">
                        {{ $photoPage['heading'] }}{{ !empty($photoPage['isContinued']) ? ' (continued)' : '' }}
                    </div>
                    <div class="section-heading-line"></div>
                </div>
            @endif

            <div class="photo-page-body">
                @foreach ($photoPage['photos'] as $photo)
                    <div class="sow-photo-card">
                        @if ($photo['imageDataUri'])
                            <div class="photo-inner-wrapper">
                                <img src="{{ $photo['imageDataUri'] }}" alt="{{ $photoPage['heading'] ?? 'Site detail' }}">
                                @if (!empty($photo['svgOverlay']))
                                    <svg class="photo-svg-overlay"
                                        viewBox="0 0 {{ $photo['baseWidth'] }} {{ $photo['logicalHeight'] }}">
                                        {!! $photo['svgOverlay'] !!}
                                    </svg>
                                @endif
                            </div>
                        @else
                            <div class="photo-unavailable">
                                Photo asset unavailable
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <div class="pdf-footer-wrapper">
                <div class="pdf-footer">
                    <div class="pdf-footer-left">
                        {{ $revisionLabel }} {{ $revisionDate }}
                    </div>
                    <div class="pdf-footer-center">
                        <div class="proprietary-badge">
                            <div class="proprietary-title">Verizon Wireless Proprietary Information</div>
                            <div class="powered-by">
                                Prepared by <a href="https://www.solucomp.com" target="_blank" rel="noopener noreferrer">www.SoluComp.com</a>
                            </div>
                        </div>
                    </div>
                    <div class="pdf-footer-right">
                        {{ $currentPageNumber }} ({{ $totalPages }})
                    </div>
                </div>
                <div class="pdf-footer-line"></div>
            </div>
        </div>
    @endforeach

</body>

</html>
