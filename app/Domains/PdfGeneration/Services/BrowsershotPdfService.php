<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Shared\Exceptions\ChromiumNotFoundException;
use App\Shared\Exceptions\PdfRenderException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\Process\Exception\ProcessFailedException;

class BrowsershotPdfService
{
    public function generate(string $html): string
    {
        /** @var string $driver */
        $driver = (string) config('cop.pdf.driver', 'browsershot');
        $browserlessToken = config('services.browserless.token');

        if ($driver === 'browserless' || ($driver !== 'browsershot' && is_string($browserlessToken) && trim($browserlessToken) !== '')) {
            return app(BrowserlessPdfService::class)->generate($html);
        }

        try {
            $browsershot = Browsershot::html($html)
                ->format('Letter')
                ->showBackground()
                ->margins(0, 0, 0, 0)
                ->hideBrowserHeaderAndFooter()
                ->noSandbox()
                ->addChromiumArguments([
                    'disable-dev-shm-usage',
                    'disable-gpu',
                    'disable-setuid-sandbox',
                    'no-zygote',
                    'single-process',
                ]);

            /** @var string|null $nodeBinary */
            $nodeBinary = config('services.browsershot.node_binary');
            if (is_string($nodeBinary) && $nodeBinary !== '') {
                $browsershot->setNodeBinary($nodeBinary);
            }

            /** @var string|null $npmBinary */
            $npmBinary = config('services.browsershot.npm_binary');
            if (is_string($npmBinary) && $npmBinary !== '') {
                $browsershot->setNpmBinary($npmBinary);
            }

            /** @var string|null $chromePath */
            $chromePath = config('services.browsershot.chrome_path');
            if (is_string($chromePath) && $chromePath !== '') {
                $browsershot->setChromePath($chromePath);
            }

            /** @var string|null $includePath */
            $includePath = config('services.browsershot.include_path');
            if (is_string($includePath) && $includePath !== '') {
                $browsershot->setIncludePath($includePath);
            }

            return $browsershot->pdf();
        } catch (ProcessFailedException $exception) {
            $errorOutput = trim($exception->getProcess()->getErrorOutput());
            if ($errorOutput === '') {
                $errorOutput = trim($exception->getProcess()->getOutput());
            }

            Log::error('Browsershot PDF generation process failed', [
                'error_output' => $errorOutput,
                'message' => $exception->getMessage(),
            ]);

            if (Str::contains($exception->getMessage(), ['not found', 'No such file'])) {
                throw new ChromiumNotFoundException(
                    'Chromium/Chrome is not available. Install it and configure CHROME_PATH.',
                );
            }

            $detail = $errorOutput !== '' ? "PDF rendering failed: {$errorOutput}" : 'PDF rendering failed.';

            throw new PdfRenderException($detail, 0, $exception);
        }
    }
}
