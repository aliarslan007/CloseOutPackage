<?php

declare(strict_types=1);

namespace App\Domains\Templates\Enums;

/**
 * The kind of template edit that was made, used to decide whether live draft
 * packages need to be re-checked for drift.
 */
enum TemplateDriftChangeType: string
{
    case BUCKET_CHANGED = 'bucket_changed';
    case EXPLAIN_CHANGED = 'explain_changed';
    case REQUIRED_CHANGED = 'required_changed';
    /** No user impact; the listener skips it. */
    case HEADING_CHANGED = 'heading_changed';
}
