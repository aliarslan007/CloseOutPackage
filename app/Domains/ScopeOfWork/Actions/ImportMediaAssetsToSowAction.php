<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\ScopeOfWork\DTOs\ImportMediaAssetsDTO;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SowImageLimitExceededException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImportMediaAssetsToSowAction
{
    public function execute(SowReport $report, ImportMediaAssetsDTO $dto): int
    {
        $this->assertEditable($report);

        /** @var Collection<int, MediaAsset> $assets */
        $assets = MediaAsset::query()
            ->with('duplicateOf')
            ->whereIn('id', $dto->mediaAssetIds)
            ->get();

        // A re-upload of an image already in the library arrives as a pointer
        // row that owns no bytes. Attach the canonical asset instead, so the
        // report references the real file and an image cannot be added twice
        // under two different ids. A pointer whose canonical row is gone
        // resolves to null and is dropped — it has no file to render.
        $canonicalIds = $assets
            ->map(fn (MediaAsset $asset): ?MediaAsset => $asset->canonical())
            ->filter()
            ->unique('id')
            ->pluck('id');

        $newAssetIds = $canonicalIds
            ->diff($report->annotatedImages()->pluck('media_asset_id'))
            ->values();

        if ($newAssetIds->isEmpty()) {
            return 0;
        }

        $currentCount = $report->annotatedImages()->count();
        if ($currentCount + $newAssetIds->count() > SowReport::MAX_IMAGES_PER_REPORT) {
            throw new SowImageLimitExceededException(
                sprintf('A SOW report can hold at most %d photos.', SowReport::MAX_IMAGES_PER_REPORT),
            );
        }

        $maxSort = $report->annotatedImages()->max('sort_order');
        $nextSort = is_numeric($maxSort) ? (int) $maxSort : 0;

        DB::transaction(function () use ($report, $newAssetIds, $nextSort): void {
            foreach ($newAssetIds->values() as $index => $assetId) {
                SowAnnotatedImage::create([
                    'sow_report_id' => $report->id,
                    'media_asset_id' => $assetId,
                    'annotation_layer' => [],
                    'composite_status' => CompositeStatus::NOT_NEEDED,
                    'sort_order' => $nextSort + $index + 1,
                    'include_in_pdf' => true,
                ]);
            }
        });

        return $newAssetIds->count();
    }

    private function assertEditable(SowReport $report): void
    {
        if (! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can accept photos.');
        }
    }
}
