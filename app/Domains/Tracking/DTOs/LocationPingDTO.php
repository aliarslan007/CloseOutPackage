<?php

declare(strict_types=1);

namespace App\Domains\Tracking\DTOs;

use App\Domains\Tracking\Enums\TrackingStatus;

final readonly class LocationPingDTO
{
    public function __construct(
        public int $projectId,
        public int $userId,
        public float $latitude,
        public float $longitude,
        public ?float $accuracy,
        public TrackingStatus $status,
    ) {}
}
