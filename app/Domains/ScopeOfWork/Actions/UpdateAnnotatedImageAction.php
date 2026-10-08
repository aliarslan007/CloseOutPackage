<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\DTOs\AnnotationLayerDTO;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowPdfGenerationStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SowAnnotationTooLargeException;
use App\Shared\Exceptions\SowStaleAnnotationsException;

final class UpdateAnnotatedImageAction
{
    private const MAX_LAYER_JSON_BYTES = 512 * 1024; // §4.3 step 4

    public function execute(SowAnnotatedImage $image, AnnotationLayerDTO $dto): SowAnnotatedImage
    {
        $report = $image->report;

        if ($report === null || ! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can be annotated.');
        }

        if ($report->pdf_generation_status === SowPdfGenerationStatus::GENERATING) {
            throw new InvalidStateTransitionException('PDF generation is in progress; annotations are locked.');
        }

        if ($dto->layer !== null && strlen((string) json_encode($dto->layer)) > self::MAX_LAYER_JSON_BYTES) {
            throw new SowAnnotationTooLargeException('The annotation layer is too large (max 512 KB).');
        }

        $this->assertNotStale($image, $dto);

        $data = [];

        if ($dto->layer !== null) {
            $data['annotation_layer'] = $dto->layer;
            $data['annotations_updated_at'] = now();
        }

        if ($dto->pageTitle !== null) {
            $data['page_title'] = $dto->pageTitle;
        }

        if ($dto->includeInPdf !== null) {
            $data['include_in_pdf'] = $dto->includeInPdf;
        }

        if ($data !== []) {
            $image->update($data);
        }

        if ($dto->isCover !== null) {
            $this->applyCover($image, $report, $dto->isCover);
        }

        if ($dto->layer !== null) {
            $this->applyCompositeStatus($image);
        }

        return $image->refresh();
    }

    /** §9.3 — optimistic lock: reject stale writes with 409. */
    private function assertNotStale(SowAnnotatedImage $image, AnnotationLayerDTO $dto): void
    {
        if (
            $dto->layer !== null
            && $dto->annotationsUpdatedAt !== null
            && $image->annotations_updated_at !== null
            && ! $image->annotations_updated_at->equalTo($dto->annotationsUpdatedAt)
        ) {
            throw new SowStaleAnnotationsException('Someone else updated this image. Refresh to load the latest version.');
        }
    }

    private function applyCover(SowAnnotatedImage $image, SowReport $report, bool $isCover): void
    {
        if ($isCover) {
            // Only one cover photo — demote any other image flagged as cover.
            $report->annotatedImages()
                ->where('id', '!=', $image->id)
                ->update(['is_cover' => false]);
            $image->update(['is_cover' => true]);
            $report->update(['cover_media_asset_id' => $image->media_asset_id]);
        } elseif ($report->cover_media_asset_id === $image->media_asset_id) {
            $image->update(['is_cover' => false]);
            $report->update(['cover_media_asset_id' => null]);
        }
    }

    /** §4.3 steps 8-9 / §9.12 — empty layer → NOT_NEEDED (no job); non-empty → PENDING. */
    private function applyCompositeStatus(SowAnnotatedImage $image): void
    {
        $layer = $image->annotation_layer;
        $nodes = is_array($layer['nodes'] ?? null) ? $layer['nodes'] : [];
        $hasNodes = count($nodes) > 0;

        if (! $hasNodes) {
            $image->update([
                'composite_status' => CompositeStatus::NOT_NEEDED,
                'composite_path' => null,
            ]);

            return;
        }

        $image->update(['composite_status' => CompositeStatus::PENDING]);
        // ponytail: dispatch CompositeAnnotatedImageJob here in Phase 4 — server-side
        // compositing doesn't exist yet, so annotated images stay PENDING until then.
    }
}
