<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\DTOs\PdfPageDTO;
use Carbon\CarbonImmutable;

final class PdfPhotoPageRenderer
{
    public const PHOTOS_PER_PAGE = 4;

    /**
     * @param  list<array{path: ?string, heading?: string, category?: string, stage?: ?string, stageHeading?: ?string, subHeading?: string, categoryId?: ?int}>  $photoItems
     */
    public function pageCount(array $photoItems): int
    {
        if (empty($photoItems)) {
            return 0;
        }

        $grouped = $this->groupPhotos($photoItems);
        $pages = 0;

        foreach ($grouped as $categories) {
            foreach ($categories as $catData) {
                $pages += (int) ceil(count($catData['photos']) / self::PHOTOS_PER_PAGE);
            }
        }

        return $pages;
    }

    /**
     * @param  list<array{path: ?string, heading?: string, category?: string, stage?: ?string, stageHeading?: ?string, subHeading?: string, categoryId?: ?int}>  $photoItems
     */
    public function render(
        CloseoutPackage $package,
        array $photoItems,
        int $totalPages,
        string $revisionLabel,
        CarbonImmutable $revisionDateCarbon,
        ?string $logoPath,
        ?string $partnerLogoPath,
        int $startPage,
    ): string {
        $html = '';
        $pageIndex = 0;
        $grouped = $this->groupPhotos($photoItems);

        foreach ($grouped as $stageHeading => $categories) {
            foreach ($categories as $catData) {
                $subHeading = $catData['subHeading'];
                $items = $catData['photos'];

                $numberedItems = [];
                foreach ($items as $idx => $item) {
                    $item['photoNumber'] = $idx + 1;
                    $numberedItems[] = $item;
                }

                $chunks = array_chunk($numberedItems, self::PHOTOS_PER_PAGE);

                foreach ($chunks as $chunkIndex => $chunk) {
                    $stageHeadingToRender = $chunkIndex === 0 ? $stageHeading : null;
                    $subHeadingToRender = $chunkIndex === 0 ? $subHeading : null;

                    $html .= view('pdf.sections.photo-page', [
                        'photos' => $chunk,
                        'stageHeading' => $stageHeadingToRender,
                        'subHeading' => $subHeadingToRender,
                        'siteInfo' => $package->siteInfo,
                        'project' => $package->project,
                        'package' => $package,
                        'page' => new PdfPageDTO(
                            pageNumber: $startPage + $pageIndex,
                            totalPages: $totalPages,
                            revisionLabel: $revisionLabel,
                            revisionDate: $revisionDateCarbon,
                        ),
                        'logoPath' => $logoPath,
                        'partnerLogoPath' => $partnerLogoPath,
                    ])->render();

                    $pageIndex++;
                }
            }
        }

        return $html;
    }

    /**
     * @param  list<array{path: ?string, heading?: string, category?: string, stage?: ?string, stageHeading?: ?string, subHeading?: string, categoryId?: ?int}>  $photoItems
     * @return array<string, array<string, array{subHeading: string, photos: list<array{path: ?string, heading?: string, category?: string, stage?: ?string, stageHeading?: ?string, subHeading?: string, categoryId?: ?int}>}>>
     */
    public function groupPhotos(array $photoItems): array
    {
        $grouped = [];

        foreach ($photoItems as $item) {
            $stageHeading = $item['stageHeading'] ?? 'Installation Pictures';
            $subHeading = ! empty($item['subHeading'])
                ? (string) $item['subHeading']
                : (! empty($item['heading']) ? (string) $item['heading'] : (! empty($item['category']) ? (string) $item['category'] : 'Photos'));

            $catKey = ! empty($item['categoryId']) ? (string) $item['categoryId'] : $subHeading;

            if (! isset($grouped[$stageHeading][$catKey])) {
                $grouped[$stageHeading][$catKey] = [
                    'subHeading' => $subHeading,
                    'photos' => [],
                ];
            }

            $grouped[$stageHeading][$catKey]['photos'][] = $item;
        }

        return $grouped;
    }
}
