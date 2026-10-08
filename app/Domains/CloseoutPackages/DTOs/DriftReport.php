<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

/**
 * Result of comparing a package against its live template. Serialized as an
 * Inertia prop and carried by TemplateDriftConflictException.
 */
final readonly class DriftReport
{
    /** @param list<DriftConflict> $conflicts */
    public function __construct(
        public bool $hasUnsafeDrift,
        public array $conflicts,
    ) {}

    public static function clean(): self
    {
        return new self(hasUnsafeDrift: false, conflicts: []);
    }
}
