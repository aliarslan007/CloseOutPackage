<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Actions\AnnotateMediaAssetAction;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Requests\AnnotateMediaAssetRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class MediaAssetAnnotateController extends Controller
{
    public function __invoke(
        AnnotateMediaAssetRequest $request,
        MediaAsset $asset,
        AnnotateMediaAssetAction $action,
    ): JsonResponse {
        $saveMode = $request->string('save_mode')->value();

        if ($saveMode === 'overwrite') {
            $this->authorize('update', $asset);
        } else {
            $this->authorize('view', $asset);
            $this->authorize('create', [MediaAsset::class, $asset->project]);
        }

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $request->file('image');

        $result = $action->execute(
            sourceAsset: $asset,
            uploadedFile: $uploadedFile,
            user: $this->user(),
            saveMode: $saveMode,
            layer: $request->validated('layer'),
        );

        return response()->json([
            'success' => true,
            'data' => $result->loadMissing(['market', 'project', 'owner', 'sourceAsset']),
        ]);
    }
}
