<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Close-Out Package Application Configuration
    |--------------------------------------------------------------------------
    |
    | Central configuration for COP-specific settings: PDF branding, upload
    | limits, revision behavior, and temporary file lifecycle.
    |
    */

    /**
     * Bundled fallback. Only used when the remote logo in
     * `services.solucomp.logo_url` cannot be fetched, so a logo host outage
     * never leaves a customer-facing PDF or email without a mark.
     */
    'logo_path' => env('COP_LOGO_PATH', 'assets/logo.png'),

    // Same canonical value as services.solucomp.logo_url — see BrandLogoService.
    'email_logo_url' => env('COP_EMAIL_LOGO_URL') ?: env('SOLUCOMP_LOGO_URL', 'https://solucomp.com/images/backgrounds/logo.png'),

    'verizon_logo_path' => env('COP_VERIZON_LOGO_PATH', 'assets/verizon-logo.png'),

    'sow_logo_path' => env('SOW_LOGO_PATH', 'assets/sow-logo.png'),

    'max_photo_size_mb' => (int) env('COP_MAX_PHOTO_SIZE_MB', 20),

    /*
    | Upload-limit hierarchy (deliberate, do NOT "fix" by equalizing):
    | - cop.max_photo_size_mb (20 MB): enforced in Store/ReplacePhotoRequest —
    |   the effective cap for COP photo uploads.
    | - media-library.max_file_size (50 MB): Spatie hard backstop; must sit
    |   ABOVE the request cap so validation messages stay user-friendly.
    | - cop.media_max_file_mb (50 MB): chunked/generic media uploads (not the
    |   COP photo flow). Raising/lowering it never changes photo limits.
    | Rule: keep media-library.max_file_size >= cop.max_photo_size_mb.
    */

    'min_photo_size_kb' => (int) env('COP_MIN_PHOTO_SIZE_KB', 10),

    /*
    | Server-side compression (COP photo paths only — not Media Library).
    | Client-side canvas compression can silently fall back to the original
    | file, so an oversized phone photo can still reach the server. Files whose
    | longest side is at or below `max_dimension` AND whose bytes are at or
    | below the recompress threshold are stored untouched (no double
    | compression). Set COP_IMAGE_MAX_DIMENSION=0 to disable server-side
    | processing entirely (kill switch, no deploy needed).
    */
    'image' => [
        'max_dimension' => (int) env('COP_IMAGE_MAX_DIMENSION', 2560),
        'jpeg_quality' => (int) env('COP_IMAGE_QUALITY', 85),
        'recompress_threshold_mb' => (int) env('COP_IMAGE_RECOMPRESS_THRESHOLD_MB', 3),
    ],

    'revision_minor_threshold' => (int) env('COP_REVISION_MINOR_THRESHOLD', 10),

    'temp_pdf_ttl_hours' => (int) env('COP_TEMP_PDF_TTL_HOURS', 24),

    /*
    | Client-facing name of a generated package PDF, used for downloads and
    | email attachments. Change the template (or set the env var) to rename
    | without touching code — the placeholders are resolved by
    | PackagePdfFilename::for(). Available placeholders:
    |
    |   {ps_number} {project_number} {project} {market} {customer}
    |   {package_id} {revision} {major} {minor} {date}
    |
    | {date} is the revision date printed on the PDF, as MM-DD-YYYY. Unknown
    | placeholders are left literal so a typo is visible. The name is always
    | made space-free and given a .pdf extension.
    */
    'pdf_filename_template' => env('COP_PDF_FILENAME_TEMPLATE', '{ps_number}-{project}-COP-{date}-Rev{major}.{minor}'),

    'email_attachment_max_bytes' => ((int) env('COP_EMAIL_ATTACHMENT_MAX_MB', 5)) * 1024 * 1024,

    /*
    | Customer email kill switch. When disabled, every customer-facing
    | email (change orders, portal access, final/progress PDFs, feedback
    | rounds, issue forwards) is recorded in email_logs as "suppressed"
    | instead of being sent, so nothing ever reaches customers while the
    | delivery approach is being reworked. Internal staff emails are
    | unaffected. Re-enable with CUSTOMER_EMAILS_ENABLED=true.
    */
    'customer_emails_enabled' => filter_var(env('CUSTOMER_EMAILS_ENABLED', false), FILTER_VALIDATE_BOOL),

    'renderer_version' => env('COP_RENDERER_VERSION', 'browsershot:5.4/chromium:120'),

    /*
    | PDF generation strategy.
    |
    | 'legacy'  — one job renders the whole document in a single Chromium
    |             process (original behavior, instant rollback target).
    | 'chunked' — the document is split into bounded page-range chunks,
    |             rendered as separate Chromium processes on a Bus batch,
    |             then merged (Ghostscript). Cost grows linearly with page
    |             count instead of crashing on one huge render.
    */
    'pdf' => [
        'driver' => env('COP_PDF_DRIVER', 'browsershot'),
        'strategy' => env('COP_PDF_STRATEGY', 'legacy'),
        'chunk_pages' => max(1, (int) env('COP_PDF_CHUNK_PAGES', 8)),
        'render_timeout' => (int) env('COP_PDF_RENDER_TIMEOUT', 120),
        'merge_driver' => env('COP_PDF_MERGE_DRIVER', 'gs'),
        'gs_binary' => env('GS_BINARY', 'gs'),
    ],

    /*
    | PDF size guardrails (warn-first, never a hard upload block).
    |
    | Field techs legitimately batch-upload on poor signal, so the product
    | policy is advisory: packages at/above `large_package_photo_count`
    | surface a warning banner (rendering takes minutes), and generate
    | responses carry the photo count so the UI can confirm before queueing
    | very large documents. A hard upload cap in the photo-upload Requests
    | is deliberately absent — do NOT add one without product sign-off.
    */
    'pdf_large_package_photo_count' => (int) env('COP_PDF_LARGE_PACKAGE_PHOTO_COUNT', 300),

    'default_market' => env('COP_DEFAULT_MARKET'),

    /*
    | Media Library — single source of truth for upload limits. The frontend
    | receives these through the /media-library/config endpoint, so validation
    | rules must never hardcode these values.
    */
    'media_allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif', 'image/gif'],

    'media_max_file_mb' => (int) env('COP_MEDIA_MAX_FILE_MB', 50),

    'media_chunk_size_mb' => (int) env('COP_MEDIA_CHUNK_SIZE_MB', 5),

    // Upload token lifetime in seconds (15 minutes covers slow mobile uploads).
    'media_upload_token_ttl' => (int) env('COP_MEDIA_UPLOAD_TOKEN_TTL', 900),

    // Rate limits per minute for uploads
    'media_rate_limit_initiate' => (int) env('COP_MEDIA_RATE_LIMIT_INITIATE', 120),
    'media_rate_limit_chunk' => (int) env('COP_MEDIA_RATE_LIMIT_CHUNK', 300),

    'admin' => [
        'name' => env('ADMIN_NAME', 'Administrator'),
        'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
