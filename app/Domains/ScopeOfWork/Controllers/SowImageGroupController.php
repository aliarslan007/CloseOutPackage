<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Controllers;

use App\Domains\ScopeOfWork\Actions\CreateSowImageGroupAction;
use App\Domains\ScopeOfWork\Actions\DeleteSowImageGroupAction;
use App\Domains\ScopeOfWork\Actions\SaveSowImageLayoutAction;
use App\Domains\ScopeOfWork\Actions\UpdateSowImageGroupAction;
use App\Domains\ScopeOfWork\Models\SowImageGroup;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Requests\DestroySowImageGroupRequest;
use App\Domains\ScopeOfWork\Requests\SaveSowImageLayoutRequest;
use App\Domains\ScopeOfWork\Requests\StoreSowImageGroupRequest;
use App\Domains\ScopeOfWork\Requests\UpdateSowImageGroupRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SowImageGroupController extends Controller
{
    public function __construct(
        private readonly CreateSowImageGroupAction $createAction,
        private readonly UpdateSowImageGroupAction $updateAction,
        private readonly DeleteSowImageGroupAction $deleteAction,
        private readonly SaveSowImageLayoutAction $saveLayoutAction,
    ) {}

    public function store(StoreSowImageGroupRequest $request, SowReport $report): JsonResponse
    {
        $group = $this->createAction->execute($report, $request->string('name')->toString());

        return response()->json(['success' => true, 'group' => $group]);
    }

    public function update(
        UpdateSowImageGroupRequest $request,
        SowReport $report,
        SowImageGroup $group,
    ): JsonResponse {
        $group = $this->updateAction->execute($report, $group, $request->string('name')->toString());

        return response()->json(['success' => true, 'group' => $group]);
    }

    public function destroy(
        DestroySowImageGroupRequest $request,
        SowReport $report,
        SowImageGroup $group,
    ): JsonResponse {
        $this->deleteAction->execute($report, $group);

        return response()->json(['success' => true]);
    }

    public function saveLayout(SaveSowImageLayoutRequest $request, SowReport $report): JsonResponse
    {
        /** @var list<array{id: int, sort_order: int}> $groups */
        $groups = $request->validated('groups');

        /** @var list<array{id: int, sow_image_group_id: int|null, sort_order: int}> $images */
        $images = $request->validated('images');

        $this->saveLayoutAction->execute($report, $groups, $images);

        return response()->json(['success' => true]);
    }
}
