<?php

declare(strict_types=1);

namespace App\Shared\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single source for the SoluComp brand mark.
 *
 * The URL is configuration, not code, so the mark can be swapped without a
 * deploy. Two access paths, because the two consumers need different things:
 *
 *  - url()    — handed to the browser for <img src>. The client caches it.
 *  - dataUri() — embedded in generated PDF/email HTML. A remote <img src> there
 *    would be fetched by the renderer's headless browser on every single
 *    render, and a slow or failed fetch would silently produce a logo-less
 *    document. Fetching here, caching, and inlining as a data URI makes PDF
 *    output deterministic and removes the render-time network dependency.
 *
 * Never throws: if the host is unreachable, the bundled asset is used. A logo
 * is not worth failing a customer-facing document over.
 */
final class BrandLogoService
{
    /** Guards against a misconfigured URL pointing at something huge. */
    private const MAX_BYTES = 4 * 1024 * 1024;

    private const CACHE_KEY = 'brand.solucomp.logo.data_uri';

    /**
     * The configured remote logo URL, or null when unset.
     */
    private function url(): ?string
    {
        $url = config('services.solucomp.logo_url');

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * The logo as an inline data URI, for PDF and email templates.
     *
     * Order of preference: the configured remote image (cached), then the
     * bundled asset, then null. Only real image bytes are ever returned — a
     * captive portal or a 404 HTML page must not be embedded as an image.
     */
    public function dataUri(): ?string
    {
        // Tests never touch the network — the suite would be slow, flaky and
        // externally dependent. Same guard the Inertia middleware uses; the
        // fetch itself stays testable through remoteDataUri().
        if (app()->runningUnitTests()) {
            return $this->bundledDataUri();
        }

        return $this->remoteDataUri() ?? $this->bundledDataUri();
    }

    /**
     * The configured remote logo, inlined and cached. Null when it cannot be
     * fetched or the response is not an image.
     */
    public function remoteDataUri(): ?string
    {
        $url = $this->url();

        if ($url === null) {
            return null;
        }

        $cached = $this->cacheSeconds();

        if ($cached > 0) {
            $memo = Cache::get(self::CACHE_KEY);

            if (is_string($memo) && $memo !== '') {
                return $memo;
            }
        }

        $dataUri = $this->fetchDataUri($url);

        if ($cached > 0 && $dataUri !== null) {
            Cache::put(self::CACHE_KEY, $dataUri, $cached);
        }

        return $dataUri;
    }

    /**
     * Drop the cached copy so a newly-published logo shows up immediately
     * instead of after the TTL.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function fetchDataUri(string $url): ?string
    {
        try {
            // The configured logo is a few hundred KB. A short total timeout
            // made this fail intermittently and silently fall back to the
            // bundled mark, which looks exactly like "the logo never updated".
            // Connect fast (a dead host should not hang), but allow the body
            // proper time to arrive.
            $response = Http::timeout(60)
                ->connectTimeout(5)
                ->retry(2, 200, throw: false)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('Brand logo fetch returned a non-success status', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));

            if (! str_starts_with($contentType, 'image/')) {
                Log::warning('Brand logo URL did not return an image', [
                    'url' => $url,
                    'content_type' => $contentType,
                ]);

                return null;
            }

            $body = $response->body();

            if ($body === '' || strlen($body) > self::MAX_BYTES) {
                Log::warning('Brand logo response was empty or too large', [
                    'url' => $url,
                    'bytes' => strlen($body),
                ]);

                return null;
            }

            // Logos ship on padded canvases — the configured one is 44% empty
            // space. Trimming it means the artwork fills whatever box the
            // consumer gives it, instead of being shrunk to fit dead space.
            $body = $this->trimTransparentPadding($body) ?? $body;

            return 'data:'.$contentType.';base64,'.base64_encode($body);
        } catch (Throwable $exception) {
            // A logo host problem must never break a render.
            Log::warning('Brand logo fetch failed', [
                'url' => $url,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Crop fully-transparent margins. Alpha-bounds detection, so it is correct
     * for whatever image the client points us at rather than tuned to one
     * asset. Returns null when the image cannot be processed, and the caller
     * then keeps the original bytes — a failed trim must never lose the logo.
     */
    private function trimTransparentPadding(string $bytes): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            return null;
        }

        try {
            $width = imagesx($image);
            $height = imagesy($image);

            // Sample every 4th pixel: bounds detection does not need full
            // resolution and this keeps a 585 KB logo well under a second.
            $step = 4;
            $top = $height;
            $bottom = -1;
            $left = $width;
            $right = -1;

            for ($y = 0; $y < $height; $y += $step) {
                for ($x = 0; $x < $width; $x += $step) {
                    $rgba = imagecolorat($image, $x, $y);
                    // GD alpha runs 0 (opaque) to 127 (transparent).
                    $transparency = ($rgba >> 24) & 0x7F;

                    if ($transparency >= 127) {
                        continue;
                    }

                    if ($y < $top) {
                        $top = $y;
                    }
                    if ($y > $bottom) {
                        $bottom = $y;
                    }
                    if ($x < $left) {
                        $left = $x;
                    }
                    if ($x > $right) {
                        $right = $x;
                    }
                }
            }

            if ($bottom < 0) {
                return null; // Entirely transparent — nothing to keep.
            }

            $pad = 1;
            $top = max(0, $top - $pad);
            $left = max(0, $left - $pad);
            $right = min($width - 1, $right + $pad);
            $bottom = min($height - 1, $bottom + $pad);

            $cropWidth = $right - $left + 1;
            $cropHeight = $bottom - $top + 1;

            if ($cropWidth === $width && $cropHeight === $height) {
                return null; // Already tight.
            }

            $out = imagecreatetruecolor($cropWidth, $cropHeight);
            imagealphablending($out, false);
            imagesavealpha($out, true);
            imagecopy($out, $image, 0, 0, $left, $top, $cropWidth, $cropHeight);

            ob_start();
            $ok = imagepng($out, null, 6);
            $trimmed = (string) ob_get_clean();

            imagedestroy($out);

            return $ok && $trimmed !== '' ? $trimmed : null;
        } finally {
            imagedestroy($image);
        }
    }

    private function bundledDataUri(): ?string
    {
        $relative = config('cop.logo_path');
        $absolute = is_string($relative) ? public_path($relative) : null;

        if ($absolute === null || ! is_file($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';
        $contents = file_get_contents($absolute);

        if ($contents === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    private function cacheSeconds(): int
    {
        $seconds = config('services.solucomp.logo_cache_seconds');

        return is_numeric($seconds) ? max(0, (int) $seconds) : 3600;
    }
}
