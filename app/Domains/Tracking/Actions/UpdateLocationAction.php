<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Actions;

use App\Domains\Tracking\DTOs\LocationPingDTO;
use App\Domains\Tracking\Models\LocationPing;

final class UpdateLocationAction
{
    /**
     * Upsert semantics: one row per (project, user) holds the latest location,
     * so "current position of every tech" is a single fast query.
     */
    public function execute(LocationPingDTO $dto): LocationPing
    {
        return LocationPing::updateOrCreate(
            ['project_id' => $dto->projectId, 'user_id' => $dto->userId],
            [
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'accuracy' => $dto->accuracy,
                'status' => $dto->status,
                'pinged_at' => now(),
            ],
        );
    }
}
