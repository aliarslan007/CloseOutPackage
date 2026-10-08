<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Enums\SowStatus;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DeleteAnnotatedImageAction
{
    public function execute(SowAnnotatedImage $image): void
    {
        $report = $image->report;

        if ($report === null || ! in_array($report->status, [SowStatus::DRAFT, SowStatus::IN_REVIEW], true)) {
            throw new InvalidStateTransitionException('Only draft or in-review SOWs can have photos removed.');
        }

        $wasCover = $image->is_cover;
        $compositePath = $image->composite_path;

        $image->delete(); // soft delete

        if ($wasCover && $report->cover_media_asset_id === $image->media_asset_id) {
            $report->update(['cover_media_asset_id' => null]);
        }

        if ($compositePath !== null) {
            try {
                Storage::disk('private')->delete($compositePath);
            } catch (Throwable $e) {
                Log::warning('Failed to unlink SOW composite image', [
                    'path' => $compositePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
