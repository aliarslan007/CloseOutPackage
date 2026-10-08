<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

use App\Shared\Exceptions\UploadTokenExpiredException;
use App\Shared\Exceptions\UploadTokenInvalidException;

/**
 * Stateless upload tokens (HMAC-SHA256 signed with the app key).
 *
 * Token format: base64(json({asset_id, user_id, expires_at})).hex-signature
 * No DB row is created; every chunk/finalize request re-verifies the token.
 */
final class UploadTokenService
{
    /**
     * Issue a time-limited upload token for an asset.
     */
    public function issue(int $assetId, int $userId): string
    {
        /** @var int $ttl */
        $ttl = config('cop.media_upload_token_ttl', 900);

        $payload = base64_encode(json_encode([
            'asset_id' => $assetId,
            'user_id' => $userId,
            'expires_at' => now()->addSeconds($ttl)->timestamp,
        ], JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signature($payload);
    }

    /**
     * Verify a token for the given user and return its payload.
     *
     * @return array{asset_id: int, user_id: int, expires_at: int}
     *
     * @throws UploadTokenExpiredException
     * @throws UploadTokenInvalidException
     */
    public function verify(string $token, int $expectedUserId): array
    {
        $parts = explode('.', $token, 2);

        if (count($parts) !== 2) {
            throw new UploadTokenInvalidException('Invalid upload token.');
        }

        [$payload, $signature] = $parts;

        // Constant-time comparison prevents timing attacks.
        if (! hash_equals($this->signature($payload), $signature)) {
            throw new UploadTokenInvalidException('Invalid upload token signature.');
        }

        $data = $this->decodePayload($payload);

        if ($data['expires_at'] < now()->timestamp) {
            throw new UploadTokenExpiredException(
                'Your upload session has expired. Please re-select your files and try again.',
            );
        }

        if ($data['user_id'] !== $expectedUserId) {
            throw new UploadTokenInvalidException('Upload token does not belong to this user.');
        }

        return $data;
    }

    /**
     * @return array{asset_id: int, user_id: int, expires_at: int}
     *
     * @throws UploadTokenInvalidException
     */
    private function decodePayload(string $payload): array
    {
        try {
            /** @var array<string, mixed>|null $data */
            $data = json_decode(base64_decode($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new UploadTokenInvalidException('Invalid upload token.');
        }

        foreach (['asset_id', 'user_id', 'expires_at'] as $key) {
            if (! is_array($data) || ! isset($data[$key]) || ! is_int($data[$key])) {
                throw new UploadTokenInvalidException('Invalid upload token.');
            }
        }

        return [
            'asset_id' => $data['asset_id'],
            'user_id' => $data['user_id'],
            'expires_at' => $data['expires_at'],
        ];
    }

    private function signature(string $payload): string
    {
        /** @var string $key */
        $key = config('app.key');

        return hash_hmac('sha256', $payload, $key);
    }
}
