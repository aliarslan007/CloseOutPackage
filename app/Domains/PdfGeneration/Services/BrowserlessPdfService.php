<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Shared\Exceptions\PdfRenderException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

class BrowserlessPdfService
{
    private const MAX_INLINE_DIMENSION = 650;

    private const JPEG_INLINE_QUALITY = 75;

    public function generate(string $html): string
    {
        /** @var string|null $token */
        $token = config('services.browserless.token');
        /** @var string $host */
        $host = (string) config('services.browserless.host', 'production-sfo.browserless.io');

        if (! is_string($token) || trim($token) === '') {
            throw new PdfRenderException('BROWSERLESS_TOKEN is not configured in .env');
        }

        $batchId = Str::uuid()->toString();
        $batchDir = storage_path("app/temp-pdf-assets/{$batchId}");

        try {
            $htmlWithAssets = $this->prepareImagesForBrowserless($html, $batchId, $batchDir);
            $url = "https://{$host}/pdf?token={$token}";
            $timeoutSeconds = (int) config('cop.pdf.render_timeout', 180);
            $timeoutMs = $timeoutSeconds * 1000;

            $response = Http::timeout($timeoutSeconds + 30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'User-Agent' => 'browserless-cop-server',
                ])
                ->post($url, [
                    'html' => $htmlWithAssets,
                    'gotoOptions' => [
                        'timeout' => $timeoutMs,
                        'waitUntil' => 'networkidle2',
                    ],
                    'options' => [
                        'timeout' => $timeoutMs,
                        'format' => 'Letter',
                        'printBackground' => true,
                        'margin' => [
                            'top' => '0mm',
                            'right' => '0mm',
                            'bottom' => '0mm',
                            'left' => '0mm',
                        ],
                        'displayHeaderFooter' => false,
                        'preferCSSPageSize' => true,
                    ],
                ]);

            if (! $response->successful()) {
                $status = $response->status();
                $body = $response->body();
                Log::error('Browserless PDF generation failed', [
                    'status' => $status,
                    'body' => $body,
                ]);

                throw new PdfRenderException("Browserless PDF generation failed with HTTP {$status}: {$body}");
            }

            return $response->body();
        } catch (Throwable $e) {
            if ($e instanceof PdfRenderException) {
                throw $e;
            }

            Log::error('Browserless HTTP request exception', ['message' => $e->getMessage()]);

            throw new PdfRenderException('Browserless PDF generation exception: '.$e->getMessage(), 0, $e);
        } finally {
            if (is_dir($batchDir)) {
                File::deleteDirectory($batchDir);
            }
        }
    }

    private function prepareImagesForBrowserless(string $html, string $batchId, string $batchDir): string
    {
        $appUrl = (string) config('app.url', '');
        $isPublicUrl = str_starts_with($appUrl, 'http://') || str_starts_with($appUrl, 'https://');
        $isLocalHost = Str::contains($appUrl, ['localhost', '127.0.0.1', '.test', '.local']);
        $useHttpStreaming = $isPublicUrl && ! $isLocalHost;

        if ($useHttpStreaming && ! is_dir($batchDir)) {
            File::makeDirectory($batchDir, 0755, true);
        }

        $imageIndex = 0;

        return preg_replace_callback('/<img[^>]+src=["\']([^"\']+)["\']/i', function (array $matches) use ($useHttpStreaming, $batchId, $batchDir, &$imageIndex): string {
            $src = (string) $matches[1];
            if (str_starts_with($src, 'data:') || str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
                return $matches[0];
            }

            $localPath = str_replace('file://', '', $src);
            if (! is_file($localPath)) {
                return $matches[0];
            }

            $compressed = $this->readCompressedImage($localPath);
            if ($compressed === null) {
                return $matches[0];
            }

            if ($useHttpStreaming) {
                $imageIndex++;
                $filename = "img_{$imageIndex}.jpg";
                file_put_contents("{$batchDir}/{$filename}", $compressed);

                $signedUrl = URL::temporarySignedRoute(
                    'pdf.temp-asset',
                    now()->addMinutes(30),
                    ['batch' => $batchId, 'file' => $filename]
                );

                return str_replace($src, $signedUrl, $matches[0]);
            }

            $mime = mime_content_type($localPath) ?: 'image/jpeg';
            $b64 = 'data:'.$mime.';base64,'.base64_encode($compressed);

            return str_replace($src, $b64, $matches[0]);
        }, $html) ?? $html;
    }

    private function readCompressedImage(string $path): ?string
    {
        try {
            $image = ImageManager::gd()->read($path);
            $image->scaleDown(self::MAX_INLINE_DIMENSION, self::MAX_INLINE_DIMENSION);

            return (string) $image->toJpeg(self::JPEG_INLINE_QUALITY);
        } catch (Throwable) {
            $raw = file_get_contents($path);

            return $raw !== false ? $raw : null;
        }
    }
}
