<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Users\Models\User;
use Database\Factories\LocationPingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LocationPing extends Model
{
    /** @use HasFactory<LocationPingFactory> */
    use HasFactory;

    public const ACTIVE_WINDOW_MINUTES = 30;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'project_id',
        'user_id',
        'latitude',
        'longitude',
        'accuracy',
        'status',
        'pinged_at',
    ];

    protected static function newFactory(): LocationPingFactory
    {
        return LocationPingFactory::new();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy' => 'float',
            'status' => TrackingStatus::class,
            'pinged_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Scope: only pings within the active window (single source, avoids duplicated WHERE). */
    public function scopeWithinActiveWindow($query)
    {
        return $query->where('pinged_at', '>=', now()->subMinutes(self::ACTIVE_WINDOW_MINUTES));
    }
}
