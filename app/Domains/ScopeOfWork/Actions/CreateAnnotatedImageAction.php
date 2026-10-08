<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\ScopeOfWork\DTOs\CreateAnnotatedImageDTO;
use App\Domains\ScopeOfWork\Enums\CompositeStatus;
use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Exceptions\InvalidStateTransitionException;
use App\Shared\Exceptions\SowImageLimitExceededException;

final class CreateAnnotatedImageAction
{
    public function execute(CreateAnnotatedImageDTO $dto): SowAnnotatedImage
    {
        $report = SowReport::query()->findOrFail($dto->sowReportId);

        $this->assertEditable($report);

        $asset = MediaAsset::query()->findOrFail($dto->mediaAssetId);

        if ($report->annotatedImages()->where('media_asset_id', $asset->id)->exists()) {
            throw new InvalidStateTransitionException('This photo is already attached to the SOW.');
        }

        if ($report->annotatedImages()->count() >= SowReport::MAX_IMAGES_PER_REPORT) {
            throw new SowImageLimitExceededException(
                sprintf('A SOW report can hold at most %d photos.', SowReport::MAX_IMAGES_PER_REPORT),
            );
        }

        $maxSort = $report->annotatedImages()->max('sort_order');
        $nextSort = is_numeric($maxSort) ? (int) $maxSort : 0;

        return SowAnnotatedImage::create([
            'sow_report_id' => $report->id,
            'media_asset_id' => $asset->id,
            'annotation_layer' => [],
            'composite_status' => CompositeStatus::NOT_NEEDED,
            'sort_order' => $nextSort + 1,
            'include_in_pdf' => true,
        ]);
    }

    private function assertEditable(SowReport $report): void
    {
        if (! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can accept photos.');
        }
    }
}
