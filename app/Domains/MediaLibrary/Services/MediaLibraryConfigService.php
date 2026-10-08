<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Services;

/**
 * Typed access to the media settings in config/cop.php — the single source of
 * truth for upload limits. Server-side validation and the /config endpoint
 * both read from here so the values can never drift apart.
 */
final class MediaLibraryConfigService
{
    public function maxFileBytes(): int
    {
        return $this->configInt('cop.media_max_file_mb', 50) * 1024 * 1024;
    }

    public function chunkSizeBytes(): int
    {
        return $this->configInt('cop.media_chunk_size_mb', 5) * 1024 * 1024;
    }

    public function chunkSizeKb(): int
    {
        return $this->configInt('cop.media_chunk_size_mb', 5) * 1024;
    }

    /**
     * @return list<string>
     */
    public function allowedMimes(): array
    {
        /** @var mixed $mimes */
        $mimes = config('cop.media_allowed_mimes', []);

        return is_array($mimes)
            ? array_values(array_filter($mimes, 'is_string'))
            : [];
    }

    private function configInt(string $key, int $default): int
    {
        /** @var mixed $value */
        $value = config($key, $default);

        return is_int($value) ? $value : (is_numeric($value) ? (int) $value : $default);
    }
}
