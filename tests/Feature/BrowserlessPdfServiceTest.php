<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domains\PdfGeneration\Services\BrowserlessPdfService;
use App\Domains\PdfGeneration\Services\BrowsershotPdfService;
use App\Shared\Exceptions\PdfRenderException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

it('generates a PDF through Browserless API with correct options and payload', function (): void {
    config([
        'services.browserless.token' => 'test-token-12345',
        'services.browserless.host' => 'production-sfo.browserless.io',
        'cop.pdf.render_timeout' => 60,
    ]);

    Http::fake([
        'https://production-sfo.browserless.io/pdf?token=test-token-12345' => Http::response('%PDF-1.4 mock binary content', 200),
    ]);

    $service = new BrowserlessPdfService;
    $result = $service->generate('<html><body><h1>Test Page</h1></body></html>');

    expect($result)->toBe('%PDF-1.4 mock binary content');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://production-sfo.browserless.io/pdf?token=test-token-12345'
            && $request['options']['format'] === 'Letter'
            && $request['options']['printBackground'] === true
            && str_contains((string) $request['html'], 'Test Page');
    });
});

it('throws PdfRenderException when BROWSERLESS_TOKEN is missing', function (): void {
    config(['services.browserless.token' => null]);

    $service = new BrowserlessPdfService;
    $service->generate('<html></html>');
})->throws(PdfRenderException::class, 'BROWSERLESS_TOKEN is not configured');

it('throws PdfRenderException when Browserless API returns an error response', function (): void {
    config([
        'services.browserless.token' => 'test-token-12345',
        'services.browserless.host' => 'production-sfo.browserless.io',
    ]);

    Http::fake([
        'https://production-sfo.browserless.io/pdf?token=test-token-12345' => Http::response('Unauthorized token', 401),
    ]);

    $service = new BrowserlessPdfService;
    $service->generate('<html></html>');
})->throws(PdfRenderException::class, 'Browserless PDF generation failed with HTTP 401');

it('routes BrowsershotPdfService to Browserless when COP_PDF_DRIVER is set to browserless', function (): void {
    config([
        'cop.pdf.driver' => 'browserless',
        'services.browserless.token' => 'test-token-route',
        'services.browserless.host' => 'production-sfo.browserless.io',
    ]);

    Http::fake([
        'https://production-sfo.browserless.io/pdf?token=test-token-route' => Http::response('%PDF-1.4 routed', 200),
    ]);

    $service = new BrowsershotPdfService;
    $result = $service->generate('<html><body>Routed Document</body></html>');

    expect($result)->toBe('%PDF-1.4 routed');
});

it('streams temporary asset through StreamPdfTempAssetController', function (): void {
    $batch = 'test-batch';
    $file = 'sample.jpg';
    $dir = storage_path("app/temp-pdf-assets/{$batch}");
    @mkdir($dir, 0755, true);
    file_put_contents("{$dir}/{$file}", 'fake-jpeg-content');

    try {
        $response = $this->get(route('pdf.temp-asset', ['batch' => $batch, 'file' => $file]));
        $response->assertOk();
        expect(file_get_contents($response->getFile()->getPathname()))->toBe('fake-jpeg-content');
    } finally {
        @unlink("{$dir}/{$file}");
        @rmdir($dir);
    }
});

it('converts local image paths to signed asset URLs when APP_URL is a public domain', function (): void {
    URL::forceRootUrl('https://pm.solucomp.com');
    config([
        'app.url' => 'https://pm.solucomp.com',
        'services.browserless.token' => 'test-token-stream',
        'services.browserless.host' => 'production-sfo.browserless.io',
    ]);

    $tempImage = tempnam(sys_get_temp_dir(), 'test_img_');
    file_put_contents($tempImage, 'image-data');

    Http::fake([
        'https://production-sfo.browserless.io/pdf?token=test-token-stream' => Http::response('%PDF-1.4 streamed', 200),
    ]);

    try {
        $service = new BrowserlessPdfService;
        $html = "<html><body><img src=\"file://{$tempImage}\"></body></html>";
        $result = $service->generate($html);

        expect($result)->toBe('%PDF-1.4 streamed');

        Http::assertSent(function ($request): bool {
            $data = $request->data();
            $html = is_array($data) ? (string) ($data['html'] ?? '') : '';

            return str_contains($html, 'pdf-temp-assets')
                && ! str_contains($html, 'data:image');
        });
    } finally {
        @unlink($tempImage);
        URL::forceRootUrl(null);
    }
});
