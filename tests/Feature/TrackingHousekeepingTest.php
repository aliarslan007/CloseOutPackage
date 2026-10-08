<?php

use App\Console\Commands\MarkIdleLocationPings;
use App\Console\Commands\PruneStaleLocationPings;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('marks pings idle when no fix arrived within 5 minutes', function (): void {
    LocationPing::factory()->create(['pinged_at' => now()->subMinutes(10), 'status' => TrackingStatus::ACTIVE]);
    LocationPing::factory()->create(['pinged_at' => now()->subMinutes(1), 'status' => TrackingStatus::ACTIVE]);
    LocationPing::factory()->create(['pinged_at' => now()->subMinutes(10), 'status' => TrackingStatus::OFFLINE]);

    Artisan::call(MarkIdleLocationPings::class);

    expect(LocationPing::query()->where('status', TrackingStatus::IDLE->value)->count())->toBe(1)
        ->and(LocationPing::query()->where('status', TrackingStatus::ACTIVE->value)->count())->toBe(1);
});

it('does not touch fresh or already-idle pings', function (): void {
    LocationPing::factory()->create(['pinged_at' => now()->subMinutes(1), 'status' => TrackingStatus::ACTIVE]);
    LocationPing::factory()->create(['pinged_at' => now()->subMinutes(10), 'status' => TrackingStatus::IDLE]);

    Artisan::call(MarkIdleLocationPings::class);

    expect(LocationPing::query()->where('status', TrackingStatus::IDLE->value)->count())->toBe(1);
});

it('prunes pings older than 24 hours', function (): void {
    LocationPing::factory()->create(['pinged_at' => now()->subHours(30)]);
    LocationPing::factory()->create(['pinged_at' => now()->subHour()]);

    Artisan::call(PruneStaleLocationPings::class);

    expect(LocationPing::query()->count())->toBe(1);
});

it('keeps pings inside the 24-hour window', function (): void {
    LocationPing::factory()->create(['pinged_at' => now()->subHours(23)]);

    Artisan::call(PruneStaleLocationPings::class);

    expect(LocationPing::query()->count())->toBe(1);
});
