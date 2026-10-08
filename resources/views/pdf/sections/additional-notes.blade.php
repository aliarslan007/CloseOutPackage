<div class="page">
    @include('pdf.partials.header', [
        'logoPath' => $logoPath,
        'partnerLogoPath' => $partnerLogoPath,
        'siteInfo' => $siteInfo,
        'project' => $project,
        'package' => $package,
    ])

    <div class="page-title">
        {{ ucwords(strtolower((string) ($package->template?->name ?? 'Closeout'))) }} Additional Notes and Comments
    </div>

    <div class="additional-notes-box" style="margin-top: 14px; width: 100%; border: 1.5px solid #000000; box-sizing: border-box;">
        <div class="additional-notes-header"
            style="background-color: #000000 !important; color: #ffffff !important; font-weight: 900; font-size: 15px; text-transform: uppercase; padding: 7px 12px; letter-spacing: 0.5px; border-bottom: 1.5px solid #000000; -webkit-print-color-adjust: exact !important; print-color-adjust: exact !important;">
            ADDITIONAL NOTES AND COMMENTS
        </div>
        <div class="additional-notes-body"
            style="padding: 16px 18px; min-height: 680px; font-size: 14px; color: #000000; line-height: 1.55; background-color: #ffffff; word-wrap: break-word; overflow-wrap: break-word; font-family: 'Calibri', 'Segoe UI', Arial, sans-serif;">
            @if ($hasNotesText)
                <div style="{{ $notesIsTruncated ? 'display: -webkit-box; -webkit-line-clamp: 28; -webkit-box-orient: vertical; overflow: hidden;' : '' }} white-space: pre-wrap;">{{ $notesText }}</div>
                @if ($notesIsTruncated)
                    <div style="text-align: center; font-style: italic; color: #555555; font-size: 12.5px; margin-top: 12px; padding-top: 10px; border-top: 1.5px dotted #888888;">
                        Note truncated — additional notes exceed 28 lines. Please visit the Templates page to view the full content.
                    </div>
                @endif
            @else
                <span style="color: #666666; font-style: italic;">No additional notes available.</span>
            @endif
        </div>
    </div>

    @include('pdf.partials.footer', ['page' => $page])
</div>
