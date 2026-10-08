<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Models\LocationPing;
use App\Domains\Tracking\Resources\TrackingPingResource;
use App\Domains\Tracking\Services\TrackingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingController extends Controller
{
    public function __construct(private readonly TrackingService $trackingService) {}

    /**
     * Global Manager/Admin tracking overview page.
     *
     * Always scoped to exactly one project. A default of "all projects" mixed
     * every crew onto one map and one list with no way to tell whose was
     * whose, so the page opens on the first project the user can reach and the
     * query string (or the dropdown) moves it from there.
     */
    public function index(Request $request): Response
    {
        $user = $this->user();
        abort_unless($user->can('viewAny', LocationPing::class), 403);

        $projects = $this->trackingService->accessibleProjects($user);

        // Clamp to the accessible list: a stale or foreign project_id falls
        // back to the default rather than silently showing an empty map.
        $requestedProjectId = $request->integer('project_id') ?: null;
        $selectedProjectId = $requestedProjectId !== null
            && $projects->pluck('id')->contains($requestedProjectId)
            ? $requestedProjectId
            : $projects->first()?->id;

        $pings = $this->trackingService->recentPingsForUser($user, $selectedProjectId);

        return Inertia::render('Tracking/Index', [
            'projects' => $projects->map(fn (Project $p): array => [
                'id' => $p->id,
                'hash_id' => $p->hash_id,
                'name' => $p->name,
                'address' => $p->address,
            ]),
            'selectedProjectId' => $selectedProjectId,
            'pings' => TrackingPingResource::collection($pings),
        ]);
    }

    /** Global Manager/Admin JSON polling endpoint. */
    public function allPings(Request $request): JsonResponse
    {
        $user = $this->user();
        abort_unless($user->can('viewAny', LocationPing::class), 403);

        $selectedProjectId = $request->integer('project_id') ?: null;
        $pings = $this->trackingService->recentPingsForUser($user, $selectedProjectId);

        return response()->json([
            'data' => TrackingPingResource::collection($pings),
        ]);
    }

    /** Manager/admin Inertia page. */
    public function show(Project $project): Response
    {
        $this->authorize('viewTracking', [LocationPing::class, $project]);

        return Inertia::render('Tracking/Show', [
            'project' => [
                'id' => $project->id,
                'hash_id' => $project->hash_id,
                'name' => $project->name,
                'address' => $project->address,
            ],
            'pings' => TrackingPingResource::collection($this->trackingService->recentPings($project)),
        ]);
    }

    /** Manager/admin JSON polling endpoint (10s interval while page open). */
    public function pings(Project $project): JsonResponse
    {
        $this->authorize('viewTracking', [LocationPing::class, $project]);

        return response()->json([
            'data' => TrackingPingResource::collection($this->trackingService->recentPings($project)),
        ]);
    }
}
