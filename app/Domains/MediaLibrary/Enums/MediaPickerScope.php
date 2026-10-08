<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Enums;

enum MediaPickerScope: string
{
    case PROJECT = 'project';
    case MARKET = 'market';
    case ALL = 'all';
}
