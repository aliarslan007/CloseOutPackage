<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Actions\UpdateLocationAction;
use App\Domains\Tracking\DTOs\LocationPingDTO;
use App\Domains\Tracking\Requests\LocationPingRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TrackingPingController extends Controller
{
    public function __construct(private readonly UpdateLocationAction $updateLocationAction) {}

    /** Field tech posts their current GPS fix (every 15s while tracking page open). */
    public function __invoke(LocationPingRequest $request, Project $project): JsonResponse
    {
        $this->updateLocationAction->execute(new LocationPingDTO(
            projectId: $project->id,
            userId: (int) $request->user()->id,
            latitude: (float) $request->validated('latitude'),
            longitude: (float) $request->validated('longitude'),
            accuracy: is_numeric($request->validated('accuracy')) ? (float) $request->validated('accuracy') : null,
            status: $request->status(),
        ));

        return response()->json(['status' => 'ok']);
    }
}
