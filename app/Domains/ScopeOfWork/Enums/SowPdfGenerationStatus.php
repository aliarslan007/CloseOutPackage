<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Enums;

enum SowPdfGenerationStatus: string
{
    case IDLE = 'idle';
    case GENERATING = 'generating'; // set before job dispatch; cleared on success or failure
    case READY = 'ready';
    case FAILED = 'failed'; // surface to manager as actionable error
}
