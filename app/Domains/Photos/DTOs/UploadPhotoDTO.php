<?php

declare(strict_types=1);

namespace App\Domains\Photos\DTOs;

use Carbon\CarbonImmutable;

final readonly class UploadPhotoDTO
{
    public function __construct(
        public int $closeoutPackageId,
        public int $packageStageId,
        public int $categorySelectionId,
        public int $position,
        public ?float $gpsLatitude = null,
        public ?float $gpsLongitude = null,
        public ?float $gpsAccuracy = null,
        public ?CarbonImmutable $capturedAt = null,
        public ?string $notes = null,
    ) {}
}
