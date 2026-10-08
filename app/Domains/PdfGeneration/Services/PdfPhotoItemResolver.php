<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Domains\Photos\Models\Photo;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Intervention\Image\ImageManager;
use Throwable;

final class PdfPhotoItemResolver
{
    private const MAX_DIMENSION = 1200;

    private const JPEG_QUALITY = 80;

    /**
     * @param  Collection<int, Photo>  $photos
     * @return list<array{path: string, heading: string, category: string, stage: ?string, stageHeading: string, gps: ?string, notes: ?string, subHeading: string, categoryId: ?int, categorySortOrder: int, position: int}>
     */
    public function resolve(Collection $photos): array
    {
        if ($photos instanceof EloquentCollection) {
            $photos->loadMissing(['media', 'categorySelection.templatePictureCategory', 'stage']);
        }

        $items = array_values(array_filter(array_map(
            fn (Photo $photo): ?array => $this->resolveItem($photo),
            $photos->all(),
        )));

        usort($items, function (array $a, array $b): int {
            $stageRankA = $this->stageRank($a['stage'] ?? null);
            $stageRankB = $this->stageRank($b['stage'] ?? null);

            if ($stageRankA !== $stageRankB) {
                return $stageRankA <=> $stageRankB;
            }

            $catOrderA = $a['categorySortOrder'] ?? 0;
            $catOrderB = $b['categorySortOrder'] ?? 0;

            if ($catOrderA !== $catOrderB) {
                return $catOrderA <=> $catOrderB;
            }

            $catIdA = $a['categoryId'] ?? 0;
            $catIdB = $b['categoryId'] ?? 0;

            if ($catIdA !== $catIdB) {
                return $catIdA <=> $catIdB;
            }

            return ($a['position'] ?? 0) <=> ($b['position'] ?? 0);
        });

        return $items;
    }

    private function stageRank(?string $stage): int
    {
        return match ($stage) {
            'pre_construction' => 1,
            'construction' => 2,
            'post_completion' => 3,
            default => 99,
        };
    }

    /**
     * @return array{path: string, heading: string, category: string, stage: ?string, stageHeading: string, gps: ?string, notes: ?string, subHeading: string, categoryId: ?int, categorySortOrder: int, position: int}|null
     */
    private function resolveItem(Photo $photo): ?array
    {
        $media = $photo->getFirstMedia(Photo::COLLECTION);

        if ($media === null) {
            return null;
        }

        $tmpPath = $this->optimizeCopy($media->getPath());

        if ($tmpPath === null) {
            return null;
        }

        $selection = $photo->categorySelection;
        $category = $selection?->templatePictureCategory;
        $categoryName = $category !== null ? $category->name : '';

        $stageModel = $photo->relationLoaded('stage') ? $photo->stage : null;
        $stageRaw = $stageModel?->stage;

        if ($stageRaw instanceof \BackedEnum) {
            $stageValue = (string) $stageRaw->value;
        } else {
            $stageValue = null;
        }

        $gpsString = null;
        if ($photo->gps_latitude !== null && $photo->gps_longitude !== null) {
            $lat = number_format((float) $photo->gps_latitude, 5);
            $lng = number_format((float) $photo->gps_longitude, 5);
            $gpsString = "GPS: {$lat}, {$lng}";
        }

        $photoNotes = $photo->notes;
        $heading = $this->headingFor($selection?->user_heading, $categoryName);

        return [
            'path' => $tmpPath,
            'heading' => $heading,
            'category' => $categoryName,
            'subHeading' => $heading,
            'categoryId' => $selection?->id,
            'categorySortOrder' => $category?->sort_order ?? 0,
            'position' => $photo->position ?? 0,
            'stage' => $stageValue,
            'stageHeading' => $this->stageHeadingFor($stageValue),
            'gps' => $gpsString,
            'notes' => is_string($photoNotes) && trim($photoNotes) !== '' ? trim($photoNotes) : null,
        ];
    }

    private function optimizeCopy(string $source): ?string
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'cop_photo_');

        if ($tmpPath === false) {
            return null;
        }

        try {
            $image = ImageManager::gd()->read($source);
            $image->scaleDown(self::MAX_DIMENSION, self::MAX_DIMENSION);

            file_put_contents($tmpPath, (string) $image->toJpeg(self::JPEG_QUALITY));

            return $tmpPath;
        } catch (Throwable) {
            // Unsupported format or processing failure: fall back to the original file.
        }

        if (copy($source, $tmpPath)) {
            return $tmpPath;
        }

        @unlink($tmpPath);

        return null;
    }

    private function headingFor(?string $userHeading, string $categoryName): string
    {
        if ($userHeading !== null) {
            return $userHeading;
        }

        return $categoryName !== '' ? $categoryName : 'Photo';
    }

    private function stageHeadingFor(?string $stage): string
    {
        return match ($stage) {
            'pre_construction' => 'Pre-Construction Pictures',
            'construction' => 'Installation Pictures',
            'post_completion' => 'Post Completion Pictures',
            default => 'Installation Pictures',
        };
    }
}
