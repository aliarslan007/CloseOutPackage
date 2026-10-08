<?php

declare(strict_types=1);

use App\Shared\Services\BrandLogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();
    config()->set('services.solucomp.logo_url', 'https://solucomp.com/images/backgrounds/logo.png');
    config()->set('services.solucomp.logo_cache_seconds', 3600);
    config()->set('cop.logo_path', 'assets/logo.png');
});

/** A real 1x1 PNG, so the bytes are a decodable image rather than noise. */
function logoPngBytes(): string
{
    return base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    );
}

it('inlines the remote logo as a data uri for pdf templates', function (): void {
    Http::fake(['*' => Http::response(logoPngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $dataUri = app(BrandLogoService::class)->remoteDataUri();

    expect($dataUri)->toStartWith('data:image/png;base64,')
        ->and(base64_decode(substr((string) $dataUri, strlen('data:image/png;base64,'))))
        ->toBe(logoPngBytes());

    Http::assertSent(fn (Request $request): bool => $request->url() === config('services.solucomp.logo_url'));
});

it('serves the cached copy rather than refetching on every render', function (): void {
    Http::fake(['*' => Http::response(logoPngBytes(), 200, ['Content-Type' => 'image/png'])]);

    $service = app(BrandLogoService::class);
    $service->remoteDataUri();
    $service->remoteDataUri();
    $service->remoteDataUri();

    // One fetch for three PDF renders — a render-time network call per document
    // would be both slow and a hard dependency on the logo host.
    Http::assertSentCount(1);
});

it('picks up a newly published logo after the cache is forgotten', function (): void {
    Http::fakeSequence()
        ->push(logoPngBytes(), 200, ['Content-Type' => 'image/png'])
        ->push('new-logo-bytes', 200, ['Content-Type' => 'image/png']);

    $service = app(BrandLogoService::class);
    $first = $service->remoteDataUri();

    $service->forget();
    $second = $service->remoteDataUri();

    expect($first)->not->toBe($second);
});

it('falls back to the bundled asset when the logo host is down', function (): void {
    Http::fake(['*' => Http::response('Service Unavailable', 503)]);

    $dataUri = (string) app(BrandLogoService::class)->dataUri();

    // public_path() reads the real filesystem, so this is the repo's own asset —
    // the point is that a real image came back and the 503 body did not.
    expect($dataUri)->toStartWith('data:image/')
        ->and($dataUri)->not->toContain('Service Unavailable')
        ->and(base64_decode(substr($dataUri, (int) strpos($dataUri, ',') + 1)))->not->toBe('');
});

it('falls back to the bundled asset when the host returns html, not an image', function (): void {
    // A captive portal or a soft 404 would otherwise be embedded as an image
    // and render as a broken logo inside a customer-facing PDF.
    Http::fake(['*' => Http::response('<html>captive portal</html>', 200, ['Content-Type' => 'text/html'])]);

    $dataUri = (string) app(BrandLogoService::class)->dataUri();

    expect($dataUri)->toStartWith('data:image/')
        ->and($dataUri)->not->toContain('text/html')
        ->and($dataUri)->not->toContain('captive');
});

it('still returns the bundled asset when no url is configured', function (): void {
    config()->set('services.solucomp.logo_url', null);
    Http::fake();

    expect(app(BrandLogoService::class)->dataUri())->toStartWith('data:image/');

    Http::assertNothingSent();
});

it('never reaches the network while the suite runs', function (): void {
    // PDF renderers call dataUri() on every document. Without this guard the
    // suite would depend on solucomp.com being up and be slow.
    Http::fake();

    $dataUri = app(BrandLogoService::class)->dataUri();

    expect($dataUri)->toStartWith('data:image/');
    Http::assertNothingSent();
});

it('trims transparent padding so the artwork fills its box', function (): void {
    // A 200x60 mark on a 400x200 transparent canvas — 70% dead space, the same
    // shape as the configured lockup.
    $canvas = imagecreatetruecolor(400, 200);
    imagealphablending($canvas, false);
    imagesavealpha($canvas, true);
    imagefill($canvas, 0, 0, (int) (127 << 24)); // fully transparent
    imagefilledrectangle($canvas, 100, 70, 299, 129, 0x0000FF00);
    ob_start();
    imagepng($canvas, null, 6);
    $padded = (string) ob_get_clean();
    imagedestroy($canvas);

    $size = (function (string $bytes): array {
        $im = imagecreatefromstring($bytes);

        return [imagesx($im), imagesy($im)];
    })($padded);

    expect($size)->toBe([400, 200]);

    Http::fake(['*' => Http::response($padded, 200, ['Content-Type' => 'image/png'])]);

    $dataUri = (string) app(BrandLogoService::class)->remoteDataUri();
    $trimmed = base64_decode(substr($dataUri, (int) strpos($dataUri, ',') + 1));

    $trimmedSize = (function (string $bytes): array {
        $im = imagecreatefromstring($bytes);

        return [imagesx($im), imagesy($im)];
    })($trimmed);

    // Padded canvas must not be handed to a small box, or the logo shrinks to
    // fit dead space and becomes unreadable.
    expect($trimmedSize[0])->toBeLessThan(400)
        ->and($trimmedSize[1])->toBeLessThan(200)
        // and it must still be a real image, not a blank
        ->and(strlen($trimmed))->toBeGreaterThan(0);
});

it('keeps an already tight logo untouched', function (): void {
    $img = imagecreatetruecolor(60, 20);
    imagefilledrectangle($img, 0, 0, 59, 19, 0x0000FF00);
    ob_start();
    imagepng($img, null, 6);
    $tight = (string) ob_get_clean();
    imagedestroy($img);

    Http::fake(['*' => Http::response($tight, 200, ['Content-Type' => 'image/png'])]);

    $dataUri = (string) app(BrandLogoService::class)->remoteDataUri();

    expect($dataUri)->toStartWith('data:image/png;base64,');
});
