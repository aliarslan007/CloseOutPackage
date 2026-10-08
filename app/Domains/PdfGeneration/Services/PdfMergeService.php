<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Shared\Exceptions\PdfRenderException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Concatenates chunk PDFs, in chunk order, into a single document.
 *
 * Ghostscript's pdfwrite device re-encodes page content streams but applies
 * no downsampling or color conversion here (-dAutoRotatePages=/None,
 * -dColorConversionStrategy=/LeaveColorUnchanged, downsampling disabled), so
 * the visual output of every merged page is preserved.
 */
final class PdfMergeService
{
    /**
     * @param  list<string>  $pdfPaths  absolute paths of chunk PDFs, in order
     * @param  string  $destinationPath  absolute path to write the merged PDF
     *
     * @throws PdfRenderException
     */
    public function merge(array $pdfPaths, string $destinationPath): void
    {
        if ($pdfPaths === []) {
            throw new PdfRenderException('Cannot merge an empty chunk list.');
        }

        if (count($pdfPaths) === 1) {
            // Single chunk: a byte-level copy preserves the PDF exactly.
            if (! copy($pdfPaths[0], $destinationPath)) {
                throw new PdfRenderException("Failed to copy single chunk PDF to {$destinationPath}.");
            }

            return;
        }

        /** @var string $binary */
        $binary = (string) config('cop.pdf.gs_binary', 'gs');

        $command = array_merge([
            $binary,
            '-dSAFER',
            '-dNOPAUSE',
            '-dBATCH',
            '-dAutoRotatePages=/None',
            '-dColorConversionStrategy=/LeaveColorUnchanged',
            '-dDownsampleColorImages=false',
            '-dDownsampleGrayImages=false',
            '-dDownsampleMonoImages=false',
            '-sDEVICE=pdfwrite',
            '-sOutputFile='.$destinationPath,
        ], $pdfPaths);

        $process = new Process($command, null, null, null, 600.0);

        try {
            $process->run();
        } catch (Throwable $exception) {
            throw new PdfRenderException('PDF merge process failed to start: '.$exception->getMessage(), 0, $exception);
        }

        if (! $process->isSuccessful()) {
            Log::error('PDF merge failed', [
                'stderr' => $process->getErrorOutput(),
                'chunks' => count($pdfPaths),
            ]);

            throw new PdfRenderException('PDF merge failed: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }
}
