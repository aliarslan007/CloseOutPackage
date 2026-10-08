<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Entity Feedback Settings
    |--------------------------------------------------------------------------
    |
    | Central configuration for the review conversation shared by change
    | orders, close-out packages and issues.
    |
    */

    /*
     * How long the author of a message may still correct it, counted from when
     * they wrote it. A typo should not be permanent, and a reply should not be
     * silently rewritten days later either — the window is the compromise.
     *
     * Applies to both sides: a customer's message and a manager's answer. The
     * server enforces it; the client only decides whether to offer the menu
     * item, so a stale tab cannot edit past it.
     */
    'edit_window_minutes' => (int) env('FEEDBACK_EDIT_WINDOW_MINUTES', 5),

];
