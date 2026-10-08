<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Issue Settings
    |--------------------------------------------------------------------------
    |
    | Central configuration for issue attachment behavior.
    |
    */

    /*
     * When false (default), attachments on a resolved issue are locked —
     * no one can add, remove or replace media. Set the
     * ISSUES_ALLOW_MEDIA_EDIT_WHEN_RESOLVED env var to true to keep
     * media editable after resolution without a code change.
     */
    'allow_media_edit_when_resolved' => (bool) env('ISSUES_ALLOW_MEDIA_EDIT_WHEN_RESOLVED', false),

];
