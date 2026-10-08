<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Actions\UpdateLocationAction;
use App\Domains\Tracking\DTOs\LocationPingDTO;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingOfflineController extends Controller
{
    public function __construct(private readonly UpdateLocationAction $updateLocationAction) {}

    /** Fired via navigator.sendBeacon on page unload — marks the tech offline. */
    public function __invoke(Request $request, Project $project): JsonResponse
    {
        abort_unless($request->user()?->can('ping', [LocationPing::class, $project]), 403);

        $this->updateLocationAction->execute(new LocationPingDTO(
            projectId: $project->id,
            userId: (int) $request->user()->id,
            latitude: 0.0,
            longitude: 0.0,
            accuracy: null,
            status: TrackingStatus::OFFLINE,
        ));

        return response()->json(['status' => 'ok']);
    }
}
