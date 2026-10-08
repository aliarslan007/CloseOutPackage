<?php

declare(strict_types=1);

namespace App\Domains\Projects\Enums;

enum IssueType: string
{
    case LOCK = 'lock';
    case SITE_ACCESS = 'site_access';
    case CONSTRUCTION = 'construction';
    case VERIZON_GUIDANCE = 'verizon_guidance';
    case OTHER = 'other';
}
