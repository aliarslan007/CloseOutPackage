<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'authcenter' => [
        'url' => env('AUTHCENTER_URL'),
        'key' => env('AUTHCENTER_KEY', 'solucomp_cop'),
        'user_origin' => env('AUTHCENTER_USER_ORIGIN', 'solucomp_cop'),
        'pm_secret' => env('AUTHCENTER_PM_SECRET'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
        'url' => env('BREVO_API_URL', 'https://api.brevo.com/v3/smtp/email'),
    ],

    'sync' => [
        'secret' => env('SYNC_SECRET'),
    ],

    'redirect' => [
        'url' => env('SSO_REDIRECT_URL'),
        'development_url' => env('SSO_DEV_REDIRECT_URL'),
        'production_url' => env('SSO_PROD_REDIRECT_URL'),
    ],

    'browsershot' => [
        'node_binary' => env('NODE_BINARY'),
        'npm_binary' => env('NPM_BINARY'),
        'chrome_path' => env('CHROME_PATH'),
        'include_path' => env('NODE_INCLUDE_PATH'),
    ],

    'browserless' => [
        'token' => env('BROWSERLESS_TOKEN'),
        'host' => env('BROWSERLESS_HOST', 'production-sfo.browserless.io'),
    ],

    /**
     * Brand logo. Single source of truth for the SoluComp mark: point
     * SOLUCOMP_LOGO_URL at any image and the sidebar, emails and generated PDFs
     * all pick it up. Resolved through BrandLogoService, which caches the bytes
     * and falls back to the bundled asset if the host is unreachable.
     */
    'solucomp' => [
        'logo_url' => env('SOLUCOMP_LOGO_URL', 'https://solucomp.com/images/backgrounds/logo.png'),
        'logo_cache_seconds' => (int) env('SOLUCOMP_LOGO_CACHE_SECONDS', 3600),
    ],

];
