<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\DTOs\PdfChunkDTO;
use Carbon\CarbonImmutable;

/**
 * Splits the document into bounded page-range chunks for the chunked
 * rendering pipeline. Pure planning math only - never reflows, renumbers,
 * or re-lays-out any content. Page numbering is identical to the legacy
 * path (sections are pre-numbered with their global page numbers).
 */
final class PdfChunkPlanner
{
    private const SECTION_ORDER = [
        'cover', 'scope', 'siteDetails', 'checklist',
        'additionalNotes', 'pictures', 'photoPages',
    ];

    public function __construct(
        private readonly PdfRenderService $renderService,
        private readonly PdfPhotoPageRenderer $photoPageRenderer = new PdfPhotoPageRenderer,
    ) {}

    /**
     * @param  list<array{path: ?string, heading: string, category: string}>  $photoItems
     * @return array{chunks: list<PdfChunkDTO>, totalPages: int, photoStartPage: int, logoPath: ?string, partnerLogoPath: ?string}
     */
    public function plan(
        CloseoutPackage $package,
        array $photoItems,
        string $revisionLabel,
        string $revisionDate,
    ): array {
        $result = $this->renderService->renderSections($package, $photoItems, $revisionLabel, $revisionDate);

        /** @var int $chunkPages */
        $chunkPages = max(1, (int) config('cop.pdf.chunk_pages', 8));
        $photoPageCount = (int) ($result['sections']['photoPages']['pages'] ?? 0);

        $chunks = $this->groupIntoChunks(
            $package,
            $result['sections'],
            $result['totalPages'],
            $chunkPages,
            $photoItems,
            $revisionLabel,
            $revisionDate,
            $result['logoPath'],
            $result['partnerLogoPath'],
        );

        return [
            'chunks' => $chunks,
            'totalPages' => $result['totalPages'],
            'photoStartPage' => $result['totalPages'] - $photoPageCount + 1,
            'logoPath' => $result['logoPath'],
            'partnerLogoPath' => $result['partnerLogoPath'],
        ];
    }

    /**
     * Groups ordered section fragments into chunks. Bounded front sections
     * are never split mid-section (their internal page numbering is
     * continuous); the unbounded photo tail is windowed across as many
     * chunks as needed so no chunk exceeds the page budget.
     *
     * @param  array<string, array{html: string, pages: int}>  $sections
     * @param  list<array{path: ?string, heading: string, category: string}>  $photoItems
     * @return list<PdfChunkDTO>
     */
    private function groupIntoChunks(
        CloseoutPackage $package,
        array $sections,
        int $totalPages,
        int $chunkPages,
        array $photoItems,
        string $revisionLabel,
        string $revisionDate,
        ?string $logoPath,
        ?string $partnerLogoPath,
    ): array {
        $chunks = [];
        $cursor = 1;

        foreach (self::SECTION_ORDER as $name) {
            if ($name === 'photoPages') {
                continue; // handled below as a windowed tail
            }

            $fragment = $sections[$name] ?? null;

            if ($fragment === null || ($fragment['pages'] === 0 && $fragment['html'] === '')) {
                continue;
            }

            $pages = max(1, $fragment['pages']);
            $firstPage = $cursor;
            $lastPage = min($totalPages, $firstPage + $pages - 1);

            $chunks[] = new PdfChunkDTO(
                id: $name,
                firstPage: $firstPage,
                lastPage: $lastPage,
                sectionHtmls: [$fragment['html']],
            );

            $cursor = $lastPage + 1;
        }

        $photoPageCount = (int) ($sections['photoPages']['pages'] ?? 0);

        return array_merge($chunks, $this->splitPhotoPages(
            $package,
            $photoPageCount,
            $chunkPages,
            $totalPages,
            $photoItems,
            $revisionLabel,
            $revisionDate,
            $logoPath,
            $partnerLogoPath,
        ));
    }

    /**
     * Windows the photo tail across as many chunks as needed so no chunk
     * exceeds the page budget. Each window is rendered through the same
     * photo renderer the legacy path uses, so content and page numbering
     * are identical - only the slice differs.
     *
     * @param  list<array{path: ?string, heading: string, category: string}>  $photoItems
     * @return list<PdfChunkDTO>
     */
    private function splitPhotoPages(
        CloseoutPackage $package,
        int $photoPageCount,
        int $chunkPages,
        int $totalPages,
        array $photoItems,
        string $revisionLabel,
        string $revisionDate,
        ?string $logoPath,
        ?string $partnerLogoPath,
    ): array {
        if ($photoPageCount <= 0) {
            return [];
        }

        $photoStartPage = $totalPages - $photoPageCount + 1;
        $revisionDateCarbon = CarbonImmutable::parse($revisionDate);
        $chunks = [];

        for ($offset = 0; $offset < $photoPageCount; $offset += $chunkPages) {
            $windowPages = min($chunkPages, $photoPageCount - $offset);

            $chunks[] = new PdfChunkDTO(
                id: 'photoPages'.($offset > 0 ? '_'.((int) ($offset / $chunkPages)) : ''),
                firstPage: $photoStartPage + $offset,
                lastPage: $photoStartPage + $offset + $windowPages - 1,
                sectionHtmls: [$this->photoPageRenderer->renderWindow(
                    $package,
                    $photoItems,
                    $totalPages,
                    $revisionLabel,
                    $revisionDateCarbon,
                    $logoPath,
                    $partnerLogoPath,
                    $photoStartPage,
                    $photoStartPage + $offset,
                    $windowPages,
                )],
            );
        }

        return $chunks;
    }
}
