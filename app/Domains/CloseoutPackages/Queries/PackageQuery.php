<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Queries;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\Models\PackageRevision;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class PackageQuery
{
    public function __construct(private readonly PackageStatusFilter $statusFilter) {}

    /**
     * @return LengthAwarePaginator<int, CloseoutPackage>
     */
    public function forProject(Project $project, ?string $status = null): LengthAwarePaginator
    {
        $base = $project->packages()->getQuery();
        $query = (clone $base)
            ->with(['template', 'stages', 'project:id,manager_id', 'lockedBy:id,name', 'creator:id,name'])
            ->withPendingFeedbackCount()
            ->latest();

        $this->statusFilter->apply($query, $base, $status);

        return $query->paginate(20);
    }

    /**
     * @return LengthAwarePaginator<int, CloseoutPackage>
     */
    public function trashedForAdmin(): LengthAwarePaginator
    {
        $query = CloseoutPackage::query()
            ->onlyTrashed()
            ->with(['project.market', 'template', 'creator', 'deleter'])
            ->latest();

        return $query->paginate(20);
    }

    public function latestFinalRevision(CloseoutPackage $package): ?PackageRevision
    {
        return $package->latestFinalRevision()->first();
    }

    public function show(CloseoutPackage $package): CloseoutPackage
    {
        $package->load([
            'project.manager',
            'template.sections.categories',
            'stages.categorySelections',
            'photos.media',
            'photos.categorySelection',
            'photos.uploader',
            'photos.photoApproval',
            'siteInfo',
            'checklistResponses.checklistItem',
            'checklistResponses.media',
            'creator',
            'packageRevisions.generatedBy',
            'lockedBy:id,name',
            'activeFeedbackRound.notes',
        ]);

        $this->attachPackageNoteImages($package);
        $this->attachPhotosSizeBytes($package);

        return $package;
    }

    /**
     * Payload for the package-level site-notes image strip. Built here rather
     * than as a model accessor so the model stays free of URL concerns. Public
     * so the customer portal reuses the exact same strip (read-only there).
     */
    public function attachPackageNoteImages(CloseoutPackage $package): void
    {
        $package->setAttribute(
            'site_note_images',
            $package->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)
                ->sortBy('order_column')
                ->values()
                ->map(fn (Media $media): array => [
                    'id' => $media->id,
                    'file_name' => $media->file_name,
                    'url' => route('packages.notes-images.show', [
                        'package' => $package->getRouteKey(),
                        'media' => $media->id,
                    ]),
                    'thumb_url' => route('packages.notes-images.show', [
                        'package' => $package->getRouteKey(),
                        'media' => $media->id,
                    ]),
                ])
                ->all(),
        );

        // Virtual payload only: keep it out of the dirty attributes so a
        // later $package->save() never tries to write a missing column.
        $package->syncOriginalAttribute('site_note_images');
    }

    /**
     * Total stored bytes of the package's current photos plus site-note
     * images — what a PDF render (and storage) actually carries. Derived via
     * SUM(media.size) on every show, never persisted: Spatie's media table is
     * the single source of truth, so replacements/deletions can never let a
     * cached total drift. Replaced photos are excluded so the number reflects
     * live content. Post-compression sizes (Phase 1/4) keep it honest.
     */
    private function attachPhotosSizeBytes(CloseoutPackage $package): void
    {
        $photosBytes = (int) Media::query()
            ->join('photos', 'photos.id', '=', 'media.model_id')
            ->where('media.model_type', (new Photo)->getMorphClass())
            ->where('photos.closeout_package_id', $package->getKey())
            ->whereNull('photos.replaced_at')
            ->where('media.collection_name', Photo::COLLECTION)
            ->sum('media.size');

        $noteImagesBytes = (int) $package->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)
            ->sum(fn (Media $media): int => (int) $media->size);

        $package->setAttribute('photos_size_bytes', $photosBytes + $noteImagesBytes);

        // Virtual payload: same dirty-attribute guard as site_note_images.
        $package->syncOriginalAttribute('photos_size_bytes');
    }
}
