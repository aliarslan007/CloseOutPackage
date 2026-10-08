<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Controllers;

use App\Domains\CloseoutPackages\Actions\AttachPackageNoteLibraryImagesAction;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Requests\AttachPackageNoteLibraryImagesRequest;
use App\Domains\CloseoutPackages\Requests\StorePackageNoteImageRequest;
use App\Http\Controllers\Controller;
use App\Shared\Services\ImageCompressionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PackageNoteImageController extends Controller
{
    public function __construct(private readonly ImageCompressionService $imageCompression) {}

    public function store(StorePackageNoteImageRequest $request, CloseoutPackage $package): RedirectResponse
    {
        /** @var list<UploadedFile> $files */
        $files = $request->file('images');

        foreach ($files as $file) {
            $compressed = $this->imageCompression->compressUploadedFile($file);
            $package->addMedia($compressed)->toMediaCollection(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION);
        }

        $count = count($files);

        return redirect()->back()->with(
            'success',
            $count === 1 ? 'Image added.' : "{$count} images added.",
        );
    }

    /**
     * Library pull: duplicate ready media-library assets into the site-notes
     * collection (compressed when oversized; the library master is untouched).
     */
    public function storeFromLibrary(AttachPackageNoteLibraryImagesRequest $request, CloseoutPackage $package): RedirectResponse
    {
        $assetIds = array_values(array_map(
            static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0,
            (array) $request->input('media_asset_ids'),
        ));

        $count = app(AttachPackageNoteLibraryImagesAction::class)
            ->execute($assetIds, $package);

        return redirect()->back()->with(
            'success',
            $count === 1 ? 'Image added.' : "{$count} images added.",
        );
    }

    public function show(CloseoutPackage $package, Media $media): BinaryFileResponse
    {
        $this->authorize('view', $package);

        $this->guardOwnership($package, $media);

        $path = $media->hasGeneratedConversion('thumb')
            ? $media->getPath('thumb')
            : $media->getPath();

        return response()->file($path);
    }

    public function destroy(CloseoutPackage $package, Media $media): RedirectResponse
    {
        $this->authorize('updateSiteNotes', $package);

        $this->guardOwnership($package, $media);

        $media->delete();

        return redirect()->back()->with('success', 'Image removed.');
    }

    private function guardOwnership(CloseoutPackage $package, Media $media): void
    {
        abort_unless(
            $media->model_type === CloseoutPackage::class
                && (int) $media->model_id === (int) $package->id
                && $media->collection_name === CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION,
            404,
        );
    }
}
