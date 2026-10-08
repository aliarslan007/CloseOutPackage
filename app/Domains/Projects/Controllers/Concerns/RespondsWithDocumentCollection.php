<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers\Concerns;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Presenters\ProjectDocumentPresenter;
use Illuminate\Http\JsonResponse;

/**
 * The JSON payload an XHR document upload/delete returns: the affected
 * collection plus its updated rows, so the client refreshes one prop instead
 * of re-rendering the whole project page.
 */
trait RespondsWithDocumentCollection
{
    protected function documentCollectionJson(
        Project $project,
        string $collection,
        ProjectDocumentPresenter $presenter,
    ): JsonResponse {
        return response()->json([
            'collection' => $collection,
            'documents' => $presenter->presentCollection($project, $collection),
        ]);
    }
}
