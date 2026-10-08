<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

/**
 * A single unsafe difference between a package's snapshot and its live template.
 */
final readonly class DriftConflict
{
    public const TYPE_BUCKET_DECREASED = 'bucket_decreased';

    public function __construct(
        /** One of the TYPE_* constants. */
        public string $type,
        public int $stageId,
        public string $stageName,
        public int $categoryId,
        public string $categoryName,
        /** Live template bucket capacity. */
        public int $newValue,
        /** Number of currently uploaded (non-replaced) photos. */
        public int $currentPhotoCount = 0,
    ) {}
}
