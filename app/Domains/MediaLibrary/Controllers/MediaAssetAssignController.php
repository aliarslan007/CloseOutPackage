<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Actions\AssignMediaAssetAction;
use App\Domains\MediaLibrary\Requests\AssignMediaAssetRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MediaAssetAssignController extends Controller
{
    public function __invoke(AssignMediaAssetRequest $request): JsonResponse
    {
        $assetIds = $request->has('media_asset_ids')
            ? array_values(array_map('intval', (array) $request->input('media_asset_ids')))
            : [$request->integer('media_asset_id')];

        $photos = app(AssignMediaAssetAction::class)->executeMany(
            $assetIds,
            $request->integer('category_selection_id'),
            $this->user(),
        );

        $loadedPhotos = array_map(
            fn ($photo) => $photo->load(['stage.package', 'categorySelection.templatePictureCategory']),
            $photos,
        );

        return response()->json([
            'data' => count($loadedPhotos) === 1 && ! $request->has('media_asset_ids')
                ? $loadedPhotos[0]
                : $loadedPhotos,
            'count' => count($loadedPhotos),
        ], 201);
    }
}
