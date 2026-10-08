<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Actions\DeleteMediaAssetAction;
use App\Domains\MediaLibrary\Actions\UpdateMediaAssetAction;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Queries\MediaAssetQuery;
use App\Domains\MediaLibrary\Requests\ListMediaAssetsRequest;
use App\Domains\MediaLibrary\Requests\UpdateMediaAssetRequest;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\Projects\Queries\ProjectQuery;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use App\Shared\Enums\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class MediaAssetController extends Controller
{
    public function index(ListMediaAssetsRequest $request): JsonResponse|InertiaResponse
    {
        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            $assets = app(MediaAssetQuery::class)->paginate($request, $this->user());

            return response()->json($assets);
        }

        $projects = app(ProjectQuery::class)->accessibleOptions($this->user());
        $technicians = app(MediaAssetQuery::class)->teamUploaders($this->user());

        return Inertia::render('MediaLibrary/Index', [
            'projects' => $projects,
            'technicians' => $technicians,
        ]);
    }

    /**
     * Accessible projects the viewer may tag uploads to or filter by.
     */
    public function projects(): JsonResponse
    {
        $projects = app(ProjectQuery::class)->accessibleOptions($this->user());

        return response()->json([
            'data' => $projects,
        ]);
    }

    /**
     * Team folders: the viewer's assigned supervisors with their
     * visible-asset counts (admins: all supervisors and managers with
     * uploads, except themselves). Folder contents load lazily via
     * index + `user_id`.
     */
    public function team(): JsonResponse
    {
        return response()->json([
            'data' => app(MediaAssetQuery::class)->teamUploaders($this->user()),
        ]);
    }

    /**
     * Quota bar + status counts for the library header.
     */
    public function stats(ListMediaAssetsRequest $request): JsonResponse
    {
        $viewer = $this->user();
        $target = $viewer->hasRole(Role::ADMIN->value) && $request->filled('user_id')
            ? User::query()->findOrFail($request->integer('user_id'))
            : $viewer;

        $quota = app(MediaQuotaService::class);

        return response()->json([
            'quota' => [
                'used_bytes' => $quota->usedBytes($target),
                'limit_bytes' => $quota->limitFor($target),
            ],
            'counts' => app(MediaAssetQuery::class)->statusCounts($request, $viewer),
            'system' => $this->systemUsage($quota, $viewer),
        ]);
    }

    /**
     * System-wide consumption with a per-role breakdown. Admin-only so managers
     * and field supervisors cannot infer org-wide usage.
     *
     * @return array{total_bytes: int, by_role: list<array{role: string, label: string, used_bytes: int, limit_bytes: int|null}>}|null
     */
    private function systemUsage(MediaQuotaService $quota, User $viewer): ?array
    {
        if (! $viewer->hasRole(Role::ADMIN->value)) {
            return null;
        }

        $usage = $quota->systemUsage();

        $roles = [
            Role::FIELD_SUPERVISOR->value => 'Field Supervisor',
            Role::MANAGER->value => 'Project Manager',
            Role::ADMIN->value => 'Administrator',
        ];

        $byRole = [];

        foreach ($roles as $role => $label) {
            $byRole[] = [
                'role' => $role,
                'label' => $label,
                'used_bytes' => $usage['by_role'][$role] ?? 0,
                'limit_bytes' => $quota->limitForRole($role),
            ];
        }

        return [
            'total_bytes' => $usage['total_bytes'],
            'by_role' => $byRole,
        ];
    }

    /**
     * COP picker — assets the viewer may place into a category, scoped by the
     * project context + scope query params.
     */
    public function picker(ListMediaAssetsRequest $request): JsonResponse
    {
        $contextProjectId = $request->filled('project_id') ? $request->integer('project_id') : null;

        $assets = app(MediaAssetQuery::class)->paginate($request, $this->user(), $contextProjectId);

        return response()->json($assets);
    }

    public function show(MediaAsset $asset): JsonResponse
    {
        $this->authorize('view', $asset);

        return response()->json([
            'data' => $asset->load([
                'market',
                'project',
                'media',
                'owner' => fn ($q) => $q->with(['roles', 'media']),
                'sourceAsset',
                'photos.stage.package',
                'photos.categorySelection',
            ]),
        ]);
    }

    public function update(UpdateMediaAssetRequest $request, MediaAsset $asset): JsonResponse
    {
        $asset = app(UpdateMediaAssetAction::class)->execute($asset, $request->integer('project_id') ?: null);

        return response()->json([
            'data' => $asset->load([
                'market',
                'project',
                'owner' => fn ($q) => $q->with(['roles', 'media']),
            ]),
        ]);
    }

    public function destroy(MediaAsset $asset): Response
    {
        app(DeleteMediaAssetAction::class)->execute($asset);

        return response()->noContent();
    }
}
