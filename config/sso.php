<?php

return [
    /*
    |--------------------------------------------------------------------------
    | External SSO
    |--------------------------------------------------------------------------
    |
    | This application authenticates through an existing single sign-on
    | provider. The login route redirects here; the callback exchanges
    | the provider token for a local session.
    |
    */

    'authorization_url' => env('SSO_AUTHORIZATION_URL'),

    'logout_url' => env('SSO_LOGOUT_URL'),
];
