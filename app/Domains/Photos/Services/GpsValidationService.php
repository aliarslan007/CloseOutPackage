<?php

declare(strict_types=1);

namespace App\Domains\Photos\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

final class GpsValidationService
{
    /**
     * @return array{latitude: ?float, longitude: ?float, accuracy: ?float}
     */
    public function normalize(?float $latitude, ?float $longitude, ?float $accuracy): array
    {
        if ($latitude === null || $longitude === null) {
            return ['latitude' => null, 'longitude' => null, 'accuracy' => null];
        }

        $valid = $latitude >= -90.0 && $latitude <= 90.0 && $longitude >= -180.0 && $longitude <= 180.0;

        if (! $valid) {
            Log::warning('GPS coordinates rejected', [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]);

            return ['latitude' => null, 'longitude' => null, 'accuracy' => null];
        }

        return [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $accuracy,
        ];
    }

    /**
     * Fallback for when the browser could not provide a geolocation fix:
     * camera JPEGs usually embed GPS in EXIF metadata.
     *
     * @return array{latitude: ?float, longitude: ?float, accuracy: ?float}
     */
    public function fromFile(UploadedFile $file): array
    {
        try {
            $exif = exif_read_data($file->getRealPath(), 'GPS');
        } catch (Throwable $exception) {
            Log::debug('EXIF GPS read failed', ['exception' => $exception->getMessage()]);

            return ['latitude' => null, 'longitude' => null, 'accuracy' => null];
        }

        return $this->normalize(
            $this->coordinate($exif['GPSLatitude'] ?? null, $exif['GPSLatitudeRef'] ?? null),
            $this->coordinate($exif['GPSLongitude'] ?? null, $exif['GPSLongitudeRef'] ?? null),
            $this->rationalToFloat($exif['GPSHPositioningError'] ?? $exif['UndefinedTag:0x001F'] ?? null),
        );
    }

    /**
     * EXIF latitudes/longitudes arrive as triples of rationals plus a hemisphere ref.
     */
    private function coordinate(mixed $parts, mixed $ref): ?float
    {
        if (! is_array($parts) || count($parts) !== 3) {
            return null;
        }

        $degrees = $this->rationalToFloat($parts[0]);
        $minutes = $this->rationalToFloat($parts[1]);
        $seconds = $this->rationalToFloat($parts[2]);

        if ($degrees === null || $minutes === null || $seconds === null) {
            return null;
        }

        $value = $degrees + $minutes / 60 + $seconds / 3600;

        $negative = is_string($ref) && in_array(strtoupper($ref), ['S', 'W'], true);

        return $negative ? -$value : $value;
    }

    /**
     * Rationals arrive as "40/1" (or "40" when exif ext returns integers).
     */
    private function rationalToFloat(mixed $rational): ?float
    {
        if ($rational === null) {
            return null;
        }

        if (is_numeric($rational)) {
            return (float) $rational;
        }

        if (! is_string($rational) || ! str_contains($rational, '/')) {
            return null;
        }

        [$numerator, $denominator] = array_map('floatval', explode('/', $rational));

        return $denominator === 0.0 ? null : $numerator / $denominator;
    }
}
