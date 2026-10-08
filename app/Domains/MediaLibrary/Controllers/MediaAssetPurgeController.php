<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Controllers;

use App\Domains\MediaLibrary\Jobs\PurgeMediaLibraryJob;
use App\Domains\MediaLibrary\Requests\StartMediaPurgeRequest;
use App\Domains\MediaLibrary\Services\MediaPurgeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class MediaAssetPurgeController extends Controller
{
    /**
     * Dry run. Everything destructive is described here first, so the admin
     * confirms real numbers rather than a button label.
     */
    public function preview(MediaPurgeService $purge): JsonResponse
    {
        return response()->json([
            'preview' => $purge->preview(),
            'confirmation_phrase' => MediaPurgeService::CONFIRMATION_PHRASE,
            'progress' => $purge->progress(),
        ]);
    }

    /**
     * Starts the sweep. Re-reads the preview and refuses to run when the totals
     * moved since the admin read them, because a purge cannot be undone and the
     * whole point of the dry run is that the numbers are trustworthy.
     */
    public function store(StartMediaPurgeRequest $request, MediaPurgeService $purge): JsonResponse
    {
        if ($purge->isRunning()) {
            return response()->json([
                'message' => 'A media library purge is already running.',
                'progress' => $purge->progress(),
            ], 409);
        }

        $preview = $purge->preview();
        $expected = $request->integer('expected_total');

        if ($preview['total_assets'] !== $expected) {
            return response()->json([
                'message' => 'The library changed since you reviewed it. Review the updated numbers and confirm again.',
                'preview' => $preview,
                'progress' => $purge->progress(),
            ], 409);
        }

        if ($preview['deletable_assets'] === 0) {
            return response()->json([
                'message' => 'There is nothing to delete — every asset is protected by a SOW reference or still uploading.',
                'preview' => $preview,
            ], 409);
        }

        $purge->start($expected, $request->user());
        PurgeMediaLibraryJob::dispatch();

        return response()->json([
            'message' => 'Purge started.',
            'preview' => $preview,
            'progress' => $purge->progress(),
        ], 202);
    }

    /** Polled by the dialog while the sweep runs. */
    public function status(MediaPurgeService $purge): JsonResponse
    {
        return response()->json(['progress' => $purge->progress()]);
    }
}
