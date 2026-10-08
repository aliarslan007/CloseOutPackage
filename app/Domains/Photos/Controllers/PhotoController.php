<?php

declare(strict_types=1);

namespace App\Domains\Photos\Controllers;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Actions\DeletePhotoAction;
use App\Domains\Photos\Actions\UploadPhotoAction;
use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Queries\PhotoQuery;
use App\Domains\Photos\Requests\StorePhotoRequest;
use App\Domains\Templates\Enums\StageType;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PhotoController extends Controller
{
    private const GPS_FIELDS = ['gps_latitude', 'gps_longitude', 'gps_accuracy'];

    public function __construct(
        private readonly PhotoQuery $photoQuery,
        private readonly UploadPhotoAction $uploadPhotoAction,
        private readonly DeletePhotoAction $deletePhotoAction,
    ) {}

    public function index(CloseoutPackage $package, StageType $stage): Response
    {
        $this->authorize('view', $package);

        $stageRecord = $package->stages()->where('stage', $stage)->firstOrFail();
        $photos = $this->photoQuery->forStage($package, $stageRecord);

        if (! Gate::allows('viewGps', Photo::class)) {
            $photos->getCollection()->each(fn (Photo $photo): Photo => $photo->setHidden(self::GPS_FIELDS));
        }

        return Inertia::render('Packages/Photos', [
            'package' => $package,
            'stage' => $stageRecord,
            'photos' => $photos,
        ]);
    }

    public function store(StorePhotoRequest $request, CloseoutPackage $package, StageType $stage): RedirectResponse|JsonResponse
    {
        $file = $request->file('photo');

        if ($file === null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'No photo file provided.'], 422);
            }

            return redirect()->back()->with('error', 'No photo file provided.');
        }

        $photo = $this->uploadPhotoAction->execute($request->toDTO(), $file, $this->user());

        // JSON responses let the batch uploader (photo-upload.service.ts) post
        // through axios instead of Inertia, so autosave/refresh visits can
        // never interrupt an in-flight batch upload.
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $photo->id,
                    'position' => $photo->position,
                    'status' => $photo->status,
                    'package_category_selection_id' => $photo->package_category_selection_id,
                ],
            ], 201);
        }

        return redirect()->back()->with('success', 'Photo uploaded.');
    }

    public function destroy(CloseoutPackage $package, Photo $photo): RedirectResponse
    {
        abort_unless($photo->closeout_package_id === $package->id, 404);

        $this->authorize('delete', $photo);

        $this->deletePhotoAction->execute($photo);

        return redirect()->back()->with('success', 'Photo removed.');
    }
}
