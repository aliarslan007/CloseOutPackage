<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Close-Out Package</title>
    <style>
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
            font-family: Arial, Helvetica, sans-serif;
            color: #000000;
            margin: 0;
            padding: 0;
            font-size: 15px;
            background: #ffffff;
            line-height: 1.4;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .page {
            position: relative;
            width: 210mm;
            height: 297mm;
            page-break-after: always;
            padding: 14px 32px 48px 32px;
            box-sizing: border-box;
            background: #ffffff;
        }

        .page:last-child {
            page-break-after: auto;
        }

        /* Standard Header (Pages 2+) */
        table.pdf-header {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 14px;
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
            height: 38px;
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
            height: 28px;
            width: auto;
            object-fit: contain;
            display: block;
            margin-left: auto;
        }

        table.pdf-header td.header-title-cell {
            text-align: center;
            vertical-align: top;
            padding: 0 10px;
        }

        .pdf-header .header-site-name {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            font-weight: 800;
            color: #2c3e50;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            line-height: 1.2;
            margin-bottom: 2px;
        }

        .pdf-header .header-pkg-name {
            font-family: Arial, Helvetica, sans-serif;
            font-weight: 700;
            color: #2c3e50;
            letter-spacing: 0.3px;
            line-height: 1.25;
            word-break: break-word;
            overflow-wrap: break-word;
        }

        /* Global Footer (All Pages) */
        .pdf-footer-wrapper {
            position: absolute;
            bottom: 24px;
            left: 32px;
            right: 32px;
        }

        .pdf-footer-line {
            width: 100%;
            height: 2.5px;
            background-color: #000000 !important;
            margin-top: 8px;
            -webkit-print-color-adjust: exact !important;
        }

        .pdf-footer {
            width: 100%;
            display: table;
            table-layout: fixed;
        }

        .pdf-footer-left {
            display: table-cell;
            width: 20%;
            vertical-align: middle;
            font-size: 11px;
            font-weight: 400;
            color: #111111;
            text-align: left;
        }

        .pdf-footer-center {
            display: table-cell;
            width: 60%;
            text-align: center;
            vertical-align: middle;
        }

        .pdf-footer-right {
            display: table-cell;
            width: 20%;
            text-align: right;
            vertical-align: middle;
            font-size: 11px;
            color: #111111;
            font-weight: 400;
        }

        .proprietary-badge {
            display: inline-block;
            background-color: #ff0000 !important;
            color: #000000 !important;
            border: 1px solid #000000;
            padding: 7px 40px 8px 40px;
            letter-spacing: 0.3px;
            text-transform: none;
            white-space: nowrap;
            text-align: center;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .proprietary-badge .proprietary-title {
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            line-height: 1.1;
            color: #000000 !important;
        }

        .proprietary-badge .powered-by {
            margin-top: 2px;
            font-size: 9.5px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: none;
            color: #000000 !important;
        }

        .proprietary-badge .powered-by a {
            color: #000000 !important;
            text-decoration: underline !important;
            text-underline-offset: 1.5px;
        }

        /* Headings & Section Underline Rules */
        .page-title {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 21px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 14px 0;
            padding-bottom: 5px;
            border-bottom: 1.5px solid #000000 !important;
            display: block;
            width: 100%;
        }

        /* Tables & Information Blocks */
        table.table-custom {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: -1.5px;
            border: 1.5px solid #000000;
        }

        table.table-custom th,
        table.table-custom td {
            border: 1px solid #000000;
            padding: 6px 9px;
            font-size: 14px;
            vertical-align: middle;
            color: #000000;
        }

        table.table-custom th.label-cell {
            background-color: #f2f2f2 !important;
            font-weight: 800;
            text-align: right;
            color: #000000;
            font-size: 13px;
            text-transform: uppercase;
            border-right: none !important;
            white-space: nowrap;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table.table-custom th.label-cell+td {
            border-left: none !important;
        }

        table.table-custom th.table-banner-red {
            background-color: #ff0000 !important;
            color: #ffffff !important;
            font-weight: 900 !important;
            text-align: center !important;
            font-size: 15px !important;
            text-transform: uppercase !important;
            padding: 4px 8px !important;
            border: 1px solid #000000 !important;
            letter-spacing: 0.5px !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table.table-custom th.table-banner-black {
            background-color: #000000 !important;
            color: #ffffff !important;
            font-weight: 900 !important;
            text-align: center !important;
            font-size: 15px !important;
            text-transform: uppercase !important;
            padding: 4px 8px !important;
            border: 1px solid #000000 !important;
            letter-spacing: 0.5px !important;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Cover Page Specific */
        .cover-logo-center {
            text-align: center;
            margin-top: 10px;
            margin-bottom: 50px;
            padding: 0;
            line-height: 1;
        }

        .cover-logo-center img {
            max-height: 80px;
            width: auto;
            display: block;
            margin: 0 auto;
            padding: 0;
        }

        .cover-titles {
            text-align: center;
            margin-top: 0;
            margin-bottom: 28px;
            padding: 0;
        }

        .cover-title-primary {
            margin-top: 0;
            font-family: 'Calibri', 'Trebuchet MS', 'Segoe UI', Arial, sans-serif;
            font-size: 34px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 20px;
            line-height: 1.25;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            text-overflow: ellipsis;
            max-height: 2.5em;
            word-break: break-word;
        }

        .cover-title-secondary {
            font-family: 'Calibri', 'Trebuchet MS', 'Segoe UI', Arial, sans-serif;
            font-size: 29px;
            font-weight: 800;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            line-height: 1.4;
        }

        .cover-photo-box {
            border: 1.5px solid #000000;
            margin-bottom: 16px;
            background: #ffffff;
            width: 62%;
            margin-left: auto;
            margin-right: auto;
        }

        .cover-photo-container {
            height: 215px;
            text-align: center;
            padding: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .cover-photo-container img {
            max-height: 210px;
            max-width: 100%;
            object-fit: contain;
        }

        /* 2x2 Photo Grid Table */
        .photo-stage-heading {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin: 0 0 4px 0;
            padding-bottom: 0;
            border-bottom: none !important;
            display: block;
            width: 100%;
        }

        .photo-sub-heading {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 15px;
            font-weight: 700;
            color: #444444;
            margin: 0 0 14px 0;
            display: block;
            width: 100%;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        th.photo-grid-instruction {
            color: #ff0000 !important;
            font-size: 11.5px;
            font-weight: 800;
            text-align: center !important;
            padding: 5px 8px;
            border-bottom: 1.5px solid #000000;
            background-color: #ffffff !important;
            letter-spacing: 0.2px;
            text-transform: none !important;
            vertical-align: middle;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table.photo-grid-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            border: 1.5px solid #000000;
            margin-top: 0;
            margin-bottom: 20px;
        }

        table.photo-grid-table td {
            width: 50%;
            vertical-align: top;
            padding: 0;
            border: 1.5px solid #000000;
        }

        table.photo-grid-table th {
            vertical-align: middle;
            padding: 0;
            border: 1.5px solid #000000;
            text-align: center !important;
            font-weight: normal;
        }

        .photo-cell-header {
            background-color: #ff0000 !important;
            color: #ffffff !important;
            font-weight: 900;
            font-size: 13.5px;
            text-align: center !important;
            padding: 5px 8px;
            border-bottom: 1.5px solid #000000;
            text-transform: none !important;
            white-space: normal;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
            line-height: 1.25;
            letter-spacing: 0.3px;
            vertical-align: middle;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .photo-cell-image-box {
            height: 332px;
            text-align: center;
            padding: 4px;
            background-color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-cell-image-box img {
            max-width: 100%;
            max-height: 322px;
            object-fit: contain;
            display: inline-block;
        }

        .photo-cell-image-box.has-note,
        .photo-cell-image-box.has-note-sm {
            height: 282px;
        }

        .photo-cell-image-box.has-note img,
        .photo-cell-image-box.has-note-sm img {
            max-height: 272px;
        }

        .photo-cell-image-box.has-note-md {
            height: 258px;
        }

        .photo-cell-image-box.has-note-md img {
            max-height: 248px;
        }

        .photo-cell-image-box.has-note-lg {
            height: 226px;
        }

        .photo-cell-image-box.has-note-lg img {
            max-height: 216px;
        }

        .photo-cell-note-box {
            background-color: #fcfcfc !important;
            border-top: 1px solid #000000;
            padding: 5px 8px;
            height: 64px;
            overflow: hidden;
            font-size: 13px;
            line-height: 1.35;
            color: #1a1a1a;
            text-align: left;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
            word-break: break-word;
        }

        .photo-cell-note-box.note-sm {
            height: 64px;
            font-size: 13px;
            line-height: 1.35;
        }

        .photo-cell-note-box.note-md {
            height: 90px;
            font-size: 12px;
            line-height: 1.3;
        }

        .photo-cell-note-box.note-lg {
            height: 152px;
            font-size: 11.5px;
            line-height: 1.25;
            padding: 4px 8px;
        }

        .photo-note-label {
            font-weight: 800;
            color: #cc0000;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            margin-right: 4px;
        }

        .photo-cell-note-box.note-md .photo-note-label {
            font-size: 12px;
        }

        .photo-cell-note-box.note-lg .photo-note-label {
            font-size: 11.5px;
        }

        .photo-section-notes {
            background-color: #ff0000 !important;
            color: #ffffff !important;
            font-size: 13px;
            font-weight: 800;
            text-align: center;
            padding: 5px 6px;
            border-top: 1.5px solid #000000;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Required Pictures List Styling */
        .required-stage-group {
            margin-bottom: 10px;
        }

        .required-stage-header {
            background-color: #f2f2f2 !important;
            color: #000000 !important;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 13.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 4px 8px;
            border: 1px solid #000000;
            border-left: 5px solid #ff0000;
            margin-top: 10px;
            margin-bottom: 6px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        ul.required-pictures-list {
            margin-top: 4px;
            padding-left: 0;
            list-style: none;
            font-family: 'Calibri', 'Segoe UI', 'Candara', Arial, sans-serif;
            font-size: 14.5px;
            line-height: 1.45;
            font-weight: 500;
            color: #000000;
        }

        ul.required-pictures-list li {
            margin-bottom: 3.5px;
            padding-left: 0;
            font-weight: 500;
        }

        ul.required-pictures-list span.num-bold {
            font-weight: 800 !important;
            font-size: 15px;
            color: #000000;
            display: inline-block;
            width: 28px;
            text-align: right;
            margin-right: 8px;
        }

        /* Checklist Styling */
        table.checklist-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000000;
            table-layout: fixed;
        }

        table.checklist-table th.checklist-banner {
            background-color: #ff0000 !important;
            color: #000000 !important;
            font-weight: 900;
            text-align: center;
            font-size: 15px;
            padding: 7px 8px;
            border: 1.5px solid #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table.checklist-table td,
        table.checklist-table th {
            border: 1px solid #000000;
            padding: 6px 8px;
            font-size: 13px;
            vertical-align: middle;
            color: #000000;
        }

        /* Hub / Site Details Styling */
        table.site-details-table {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000000;
            table-layout: fixed;
        }

        table.site-details-table th.site-details-banner {
            background-color: #ff0000 !important;
            color: #ffffff !important;
            font-weight: 900;
            text-align: center;
            font-size: 15px;
            padding: 7px 8px;
            border: 1.5px solid #000000;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        table.site-details-table td,
        table.site-details-table th {
            border: 1px solid #000000;
            padding: 6px 8px;
            font-size: 13px;
            vertical-align: middle;
            color: #000000;
        }

        .check-box {
            display: inline-block;
            width: 17px;
            height: 17px;
            border: 1.5px solid #000000;
            text-align: center;
            line-height: 15px;
            font-weight: 900;
            font-size: 12.5px;
            margin-right: 6px;
            vertical-align: middle;
            background: #ffffff;
            color: #000000;
        }

        .check-box-red {
            border-color: #ff0000 !important;
            color: #ff0000 !important;
        }

        .explain-text {
            color: #ff0000;
            font-weight: 800;
            font-size: 11.5px;
        }
    </style>
</head>
