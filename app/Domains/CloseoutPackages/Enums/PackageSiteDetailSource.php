<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Enums;

enum PackageSiteDetailSource: string
{
    case AUTH_CENTER = 'auth_center';
    case MANUAL = 'manual';
}
