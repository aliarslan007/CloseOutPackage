<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Services;

use App\Domains\Templates\Enums\StageType;

final class StageAdvancementService
{
    private const SEQUENCE = [
        StageType::PRE_CONSTRUCTION,
        StageType::CONSTRUCTION,
        StageType::POST_COMPLETION,
    ];

    public function next(StageType $stage): ?StageType
    {
        $index = array_search($stage, self::SEQUENCE, true);

        if ($index === false || $index >= count(self::SEQUENCE) - 1) {
            return null;
        }

        return self::SEQUENCE[$index + 1];
    }

    public function isLast(StageType $stage): bool
    {
        return $stage === StageType::POST_COMPLETION;
    }
}
