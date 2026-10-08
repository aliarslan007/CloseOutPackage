<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Enums;

enum MediaAssetStatus: string
{
    case PROCESSING = 'processing';
    case READY = 'ready';
    case FAILED = 'failed';
}
