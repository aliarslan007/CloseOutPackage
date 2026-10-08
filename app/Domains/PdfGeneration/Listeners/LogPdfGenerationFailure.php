<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Listeners;

use App\Domains\PdfGeneration\Events\PdfGenerationFailed;
use Illuminate\Support\Facades\Log;

class LogPdfGenerationFailure
{
    public function handle(PdfGenerationFailed $event): void
    {
        activity('system')
            ->performedOn($event->package)
            ->event('pdf.generation_failed')
            ->log('PDF generation failed');

        Log::error('PDF generation failed', [
            'package_id' => $event->package->id,
            'error' => str_replace(["\n", "\r"], ' ', $event->error),
        ]);
    }
}
