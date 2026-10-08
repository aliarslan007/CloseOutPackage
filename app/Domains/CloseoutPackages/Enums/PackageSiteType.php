<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Enums;

enum PackageSiteType: string
{
    case HUB = 'hub';
    case SMALLCELL = 'smallcell';

    /**
     * Label shown in the manager UI (Cell Site / Hub Site).
     */
    public function label(): string
    {
        return match ($this) {
            self::HUB => 'Hub Site',
            self::SMALLCELL => 'Cell Site',
        };
    }
}
