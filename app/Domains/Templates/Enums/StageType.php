<?php

declare(strict_types=1);

namespace App\Domains\Templates\Enums;

enum StageType: string
{
    case PRE_CONSTRUCTION = 'pre_construction';
    case CONSTRUCTION = 'construction';
    case POST_COMPLETION = 'post_completion';

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(fn (self $stage): string => $stage->value, self::cases());
    }

    /**
     * Human-readable stage name. Mirrors STAGE_LABELS in the package builder UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::PRE_CONSTRUCTION => 'Pre Construction',
            self::CONSTRUCTION => 'Construction / Installation',
            self::POST_COMPLETION => 'Post Completion',
        };
    }
}
