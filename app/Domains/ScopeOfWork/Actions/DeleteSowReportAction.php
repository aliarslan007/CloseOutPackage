<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DeleteSowReportAction
{
    public function execute(SowReport $report): void
    {
        $report->delete(); // soft delete

        $this->releaseStoredFiles($report);
    }

    /**
     * Unlink the composite renders from the private disk. Split from execute()
     * so the project cascade can release bytes only once its transaction commits.
     */
    public function releaseStoredFiles(SowReport $report): void
    {
        /** @var list<string> $compositePaths */
        $compositePaths = $report->annotatedImages()
            ->whereNotNull('composite_path')
            ->pluck('composite_path')
            ->all();

        foreach ($compositePaths as $path) {
            try {
                Storage::disk('private')->delete($path);
            } catch (Throwable $e) {
                Log::warning('Failed to unlink SOW composite image during report delete', [
                    'path' => $path,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
