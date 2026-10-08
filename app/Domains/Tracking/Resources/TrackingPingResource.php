<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Resources;

use App\Domains\Tracking\Models\LocationPing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LocationPing
 */
class TrackingPingResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'user_name' => $this->user?->name,
            'user_role' => $this->user?->roles->first()?->name,
            'user_email' => $this->user?->email,
            'user_phone' => $this->user?->phone,
            'user_photo_url' => $this->user?->profilePhotoUrl(),
            'project_id' => $this->project_id,
            'project_name' => $this->project?->name,
            'latitude' => (float) $this->latitude,
            'longitude' => (float) $this->longitude,
            'accuracy' => $this->accuracy !== null ? (float) $this->accuracy : null,
            'status' => $this->status->value,
            'pinged_at' => $this->pinged_at?->toIso8601String(),
        ];
    }
}
