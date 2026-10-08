<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Enums;

enum CompositeStatus: string
{
    case NOT_NEEDED = 'not_needed'; // empty annotation_layer — use raw source image as-is
    case PENDING = 'pending'; // queued; job not yet started
    case PROCESSING = 'processing'; // job running
    case READY = 'ready'; // composite_path is valid and usable
    case FAILED = 'failed'; // all retries exhausted; user must remove and re-add image
}
