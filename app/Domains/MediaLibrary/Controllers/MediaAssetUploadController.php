<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Actions\AbortUploadAction;
use App\Domains\MediaLibrary\Actions\AppendChunkAction;
use App\Domains\MediaLibrary\Actions\FinalizeUploadAction;
use App\Domains\MediaLibrary\Actions\InitiateChunkedUploadAction;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Requests\AppendChunkRequest;
use App\Domains\MediaLibrary\Requests\FinalizeUploadRequest;
use App\Domains\MediaLibrary\Requests\InitiateUploadRequest;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class MediaAssetUploadController extends Controller
{
    public function initiate(InitiateUploadRequest $request): JsonResponse
    {
        $project = $request->filled('project_id')
            ? Project::query()->findOrFail($request->integer('project_id'))
            : null;

        $this->authorize('create', [MediaAsset::class, $project]);

        $result = app(InitiateChunkedUploadAction::class)->execute($request, $this->user(), $project);

        return response()->json([
            'asset_id' => $result['asset_id'],
            'upload_token' => $result['upload_token'],
        ]);
    }

    public function chunk(AppendChunkRequest $request, MediaAsset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        app(AppendChunkAction::class)->execute($request, $asset, $this->user());

        return response()->json(['status' => 'ok']);
    }

    public function finalize(FinalizeUploadRequest $request, MediaAsset $asset): JsonResponse
    {
        $this->authorize('update', $asset);

        $asset = app(FinalizeUploadAction::class)->execute($request, $asset, $this->user());

        return response()->json([
            'asset_id' => $asset->id,
            'status' => $asset->status->value,
            'data' => $asset->loadMissing(['market', 'project']),
        ]);
    }

    public function abort(MediaAsset $asset): Response
    {
        app(AbortUploadAction::class)->execute($asset);

        return response()->noContent();
    }
}
