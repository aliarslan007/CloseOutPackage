<?php

use App\Domains\MediaLibrary\Services\UploadTokenService;
use App\Shared\Exceptions\UploadTokenExpiredException;
use App\Shared\Exceptions\UploadTokenInvalidException;

it('round-trips an issued token', function (): void {
    $service = app(UploadTokenService::class);

    $token = $service->issue(12, 34);
    $payload = $service->verify($token, 34);

    expect($payload['asset_id'])->toBe(12)
        ->and($payload['user_id'])->toBe(34)
        ->and($payload['expires_at'])->toBeGreaterThan(now()->timestamp);
});

it('rejects a token with a tampered signature', function (): void {
    $service = app(UploadTokenService::class);
    $token = $service->issue(1, 2);

    $flipped = substr($token, 0, -1).(substr($token, -1) === 'a' ? 'b' : 'a');

    expect(fn () => $service->verify($flipped, 2))
        ->toThrow(UploadTokenInvalidException::class, 'Invalid upload token signature.');
});

it('rejects a token presented by a different user', function (): void {
    $service = app(UploadTokenService::class);
    $token = $service->issue(1, 2);

    expect(fn () => $service->verify($token, 99))
        ->toThrow(UploadTokenInvalidException::class, 'Upload token does not belong to this user.');
});

it('rejects an expired token', function (): void {
    config()->set('cop.media_upload_token_ttl', -1);
    $service = app(UploadTokenService::class);

    $token = $service->issue(1, 2);

    expect(fn () => $service->verify($token, 2))
        ->toThrow(UploadTokenExpiredException::class, 'Your upload session has expired.');
});

it('rejects a malformed token', function (): void {
    $service = app(UploadTokenService::class);

    expect(fn () => $service->verify('not-a-token', 1))
        ->toThrow(UploadTokenInvalidException::class, 'Invalid upload token.');
});
