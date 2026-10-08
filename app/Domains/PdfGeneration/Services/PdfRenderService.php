<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Services;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\PdfGeneration\DTOs\PdfPageDTO;
use App\Domains\Templates\Models\Template;
use App\Shared\Exceptions\LogoFileMissingException;
use App\Shared\Services\BrandLogoService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class PdfRenderService
{
    /**
     * Total vertical space in pixels available for content on an A4 page
     * between the header/page-title and the fixed footer.
     */
    private const CHECKLIST_PAGE_MAX_HEIGHT = 880;

    /**
     * Height in pixels of the checklist table header banner.
     */
    private const CHECKLIST_BANNER_HEIGHT = 35;

    /**
     * Maximum character count allowed for an individual checklist response note.
     */
    public const CHECKLIST_NOTE_MAX_LENGTH = 1000;

    /**
     * Maximum visible lines of text on the dedicated Additional Notes and Comments page
     * before truncating to preserve layout and footer spacing.
     */
    public const ADDITIONAL_NOTES_LINE_LIMIT = 28;

    public const ADDITIONAL_NOTES_CHARS_PER_LINE = 80;

    private const SITE_DETAILS_PER_PAGE = 12;

    private const PICTURE_CATEGORIES_PER_PAGE = 30;

    public function __construct(
        private readonly PdfPhotoPageRenderer $photoPageRenderer = new PdfPhotoPageRenderer,
    ) {}

    /**
     * @param  list<array{path: ?string, heading: string, category: string}>  $photoItems
     * @return array{html: string, totalPages: int}
     */
    public function render(
        CloseoutPackage $package,
        array $photoItems,
        string $revisionLabel,
        string $revisionDate,
    ): array {
        $package->load(['siteInfo.authorUser', 'siteInfo.pmUser', 'project.manager', 'project.customer', 'checklistResponses.checklistItem', 'checklistResponses.media', 'template.sections.categories', 'template.media', 'siteDetails']);

        /** @var Template $template */
        $template = $package->template;
        $revisionDateCarbon = CarbonImmutable::parse($revisionDate);

        $siteDetailsPageCount = $this->siteDetailsPageCount($package);
        $scopePageCount = $this->scopePageCount($package);
        $checklistPageCount = $this->checklistPageCount($package);
        $additionalNotesPageCount = $this->additionalNotesPageCount($package);
        $picturePageCount = $this->picturePageCount($template);
        $photoPageCount = $this->photoPageRenderer->pageCount($photoItems);

        $totalPages = 1 + $scopePageCount + $siteDetailsPageCount + $checklistPageCount + $additionalNotesPageCount + $picturePageCount + $photoPageCount;
        $logoPath = $this->brandLogoSrc();
        $partnerLogoPath = $this->partnerLogoSrc();

        $cover = view('pdf.sections.cover', [
            'siteInfo' => $package->siteInfo,
            'project' => $package->project,
            'package' => $package,
            'sitePhotoPath' => $this->sitePhotoPath($package),
            'page' => new PdfPageDTO(
                pageNumber: 1,
                totalPages: $totalPages,
                revisionLabel: $revisionLabel,
                revisionDate: $revisionDateCarbon,
            ),
            'logoPath' => $logoPath,
            'partnerLogoPath' => $partnerLogoPath,
        ])->render();

        $siteDetailsStartPage = 2 + $scopePageCount;
        $scope = $this->renderScopePage($package, $totalPages, $revisionLabel, $revisionDateCarbon, $logoPath, $partnerLogoPath);
        $siteDetails = $this->renderSiteDetailsPages($package, $totalPages, $revisionLabel, $revisionDateCarbon, $logoPath, $partnerLogoPath, $siteDetailsStartPage);
        $checklistStartPage = $siteDetailsStartPage + $siteDetailsPageCount;
        $checklist = $this->renderChecklistPages($package, $totalPages, $revisionLabel, $revisionDateCarbon, $logoPath, $partnerLogoPath, $checklistStartPage);
        $additionalNotesStartPage = $checklistStartPage + $checklistPageCount;
        $additionalNotes = $this->renderAdditionalNotesPages($package, $totalPages, $revisionLabel, $revisionDateCarbon, $logoPath, $partnerLogoPath, $additionalNotesStartPage);
        $pictureStartPage = $additionalNotesStartPage + $additionalNotesPageCount;
        $pictures = $this->renderPicturePages($package, $template, $totalPages, $revisionLabel, $revisionDateCarbon, $logoPath, $partnerLogoPath, $pictureStartPage);
        $photoStartPage = $pictureStartPage + $picturePageCount;
        $photoPages = $this->photoPageRenderer->render($package, $photoItems, $totalPages, $revisionLabel, $revisionDateCarbon, $logoPath, $partnerLogoPath, $photoStartPage);

        $html = view('pdf.package', [
            'cover' => $cover,
            'scope' => $scope,
            'siteDetails' => $siteDetails,
            'checklist' => $checklist,
            'additionalNotes' => $additionalNotes,
            'pictures' => $pictures,
            'photoPages' => $photoPages,
        ])->render();

        return ['html' => $html, 'totalPages' => $totalPages];
    }

    private function renderChecklistPages(
        CloseoutPackage $package,
        int $totalPages,
        string $revisionLabel,
        CarbonImmutable $revisionDateCarbon,
        ?string $logoPath,
        ?string $partnerLogoPath,
        int $startPage = 2,
    ): string {
        $responses = $package->checklistResponses;
        $hasNaOption = $this->hasNaChecklistOption($package);

        if ($responses->isEmpty()) {
            return view('pdf.sections.checklist', [
                'siteInfo' => $package->siteInfo,
                'project' => $package->project,
                'package' => $package,
                'responses' => [],
                'hasNaOption' => $hasNaOption,
                'page' => new PdfPageDTO(
                    pageNumber: $startPage,
                    totalPages: $totalPages,
                    revisionLabel: $revisionLabel,
                    revisionDate: $revisionDateCarbon,
                ),
                'logoPath' => $logoPath,
                'partnerLogoPath' => $partnerLogoPath,
            ])->render();
        }

        $chunks = $this->packChecklistPages($package, $responses);
        $html = '';

        foreach ($chunks as $index => $chunk) {
            $html .= view('pdf.sections.checklist', [
                'siteInfo' => $package->siteInfo,
                'project' => $package->project,
                'package' => $package,
                'responses' => $chunk,
                'hasNaOption' => $hasNaOption,
                'page' => new PdfPageDTO(
                    pageNumber: $startPage + $index,
                    totalPages: $totalPages,
                    revisionLabel: $revisionLabel,
                    revisionDate: $revisionDateCarbon,
                ),
                'logoPath' => $logoPath,
                'partnerLogoPath' => $partnerLogoPath,
            ])->render();
        }

        return $html;
    }

    private function hasNaChecklistOption(CloseoutPackage $package): bool
    {
        return $package->checklistResponses->contains(
            fn ($response): bool => (bool) ($response->is_na ?? false) || (bool) ($response->checklistItem->allows_na ?? false)
        ) || ($package->template?->checklistItems()->where('allows_na', true)->exists() ?? false);
    }

    private function renderScopePage(
        CloseoutPackage $package,
        int $totalPages,
        string $revisionLabel,
        CarbonImmutable $revisionDateCarbon,
        ?string $logoPath,
        ?string $partnerLogoPath,
    ): string {
        if ($this->scopePageCount($package) === 0) {
            return '';
        }

        return view('pdf.sections.scope', [
            'siteInfo' => $package->siteInfo,
            'project' => $package->project,
            'package' => $package,
            'page' => new PdfPageDTO(
                pageNumber: 2,
                totalPages: $totalPages,
                revisionLabel: $revisionLabel,
                revisionDate: $revisionDateCarbon,
            ),
            'logoPath' => $logoPath,
            'partnerLogoPath' => $partnerLogoPath,
        ])->render();
    }

    private function renderSiteDetailsPages(
        CloseoutPackage $package,
        int $totalPages,
        string $revisionLabel,
        CarbonImmutable $revisionDateCarbon,
        ?string $logoPath,
        ?string $partnerLogoPath,
        int $startPage = 2,
    ): string {
        $details = $package->siteDetails;

        if ($details->isEmpty()) {
            return '';
        }

        $chunks = $details->chunk(self::SITE_DETAILS_PER_PAGE);
        $html = '';

        foreach ($chunks as $index => $chunk) {
            $html .= view('pdf.sections.site-details', [
                'siteInfo' => $package->siteInfo,
                'project' => $package->project,
                'package' => $package,
                'siteDetails' => $chunk,
                'page' => new PdfPageDTO(
                    pageNumber: $startPage + $index,
                    totalPages: $totalPages,
                    revisionLabel: $revisionLabel,
                    revisionDate: $revisionDateCarbon,
                ),
                'logoPath' => $logoPath,
                'partnerLogoPath' => $partnerLogoPath,
            ])->render();
        }

        return $html;
    }

    private function renderPicturePages(
        CloseoutPackage $package,
        Template $template,
        int $totalPages,
        string $revisionLabel,
        CarbonImmutable $revisionDateCarbon,
        ?string $logoPath,
        ?string $partnerLogoPath,
        int $startPage,
    ): string {
        $sections = $template->sections;
        if ($sections->isEmpty()) {
            return view('pdf.sections.pictures', [
                'siteInfo' => $package->siteInfo,
                'project' => $package->project,
                'package' => $package,
                'sections' => [],
                'startCounter' => 1,
                'page' => new PdfPageDTO(
                    pageNumber: $startPage,
                    totalPages: $totalPages,
                    revisionLabel: $revisionLabel,
                    revisionDate: $revisionDateCarbon,
                ),
                'logoPath' => $logoPath,
                'partnerLogoPath' => $partnerLogoPath,
            ])->render();
        }

        // Chunk categories across pages while preserving stage headings
        $pagesData = [];
        $currentCategories = 0;
        $currentPageSections = [];
        $globalCounter = 1;
        $startCounters = [1];

        foreach ($sections as $section) {
            $sectionCategories = $section->categories;
            if ($sectionCategories->isEmpty()) {
                continue;
            }

            $currentSectionData = [
                'heading' => $section->heading,
                'categories' => [],
            ];

            foreach ($sectionCategories as $category) {
                if ($currentCategories >= self::PICTURE_CATEGORIES_PER_PAGE) {
                    // Push current page
                    $pagesData[] = $currentPageSections;
                    $currentPageSections = [];
                    $currentCategories = 0;
                    $startCounters[] = $globalCounter;

                    // Start new section entry on new page
                    $currentSectionData = [
                        'heading' => $section->heading,
                        'categories' => [],
                    ];
                }

                $currentSectionData['categories'][] = $category;
                $currentCategories++;
                $globalCounter++;
            }

            if (! empty($currentSectionData['categories'])) {
                $currentPageSections[] = $currentSectionData;
            }
        }

        if (! empty($currentPageSections)) {
            $pagesData[] = $currentPageSections;
        }

        $html = '';
        foreach ($pagesData as $index => $pageSections) {
            $html .= view('pdf.sections.pictures', [
                'siteInfo' => $package->siteInfo,
                'project' => $package->project,
                'package' => $package,
                'sections' => $pageSections,
                'startCounter' => $startCounters[$index] ?? 1,
                'page' => new PdfPageDTO(
                    pageNumber: $startPage + $index,
                    totalPages: $totalPages,
                    revisionLabel: $revisionLabel,
                    revisionDate: $revisionDateCarbon,
                ),
                'logoPath' => $logoPath,
                'partnerLogoPath' => $partnerLogoPath,
            ])->render();
        }

        return $html;
    }

    private function checklistPageCount(CloseoutPackage $package): int
    {
        return max(1, count($this->packChecklistPages($package)));
    }

    /**
     * Approximate rendered height in pixels for a single checklist item in the PDF.
     */
    private function calculateChecklistItemHeight(PackageChecklistResponse $response): int
    {
        $label = (string) ($response->checklistItem?->label ?? '');
        $labelLength = mb_strlen($label);

        // Base question row height (padding 12px + font/borders)
        if ($labelLength > 160) {
            $height = 66;
        } elseif ($labelLength > 80) {
            $height = 48;
        } else {
            $height = 32;
        }

        $isNa = (bool) ($response->is_na ?? false);
        if ($isNa) {
            return $height;
        }

        $note = trim((string) ($response->explain ?? ''));
        $hasNote = $note !== '';
        $hasQuantity = $response->quantity !== null;

        if ($hasNote || $hasQuantity) {
            // Notes row container padding (10px) and borders (1px)
            $noteHeight = 11;

            if ($hasNote) {
                $noteLength = min(mb_strlen($note), self::CHECKLIST_NOTE_MAX_LENGTH);
                // At 11.5px font across table cell, ~80 characters fit per line
                $lines = max(1, (int) ceil($noteLength / 80));
                $noteHeight += $lines * 15;
            }

            if ($hasQuantity) {
                $noteHeight += $hasNote ? 18 : 16;
            }

            $height += $noteHeight;
        }

        return $height;
    }

    /**
     * Pack checklist responses across pages dynamically based on vertical height budget.
     * Accurately accounts for question row height, note text length (up to 1000 chars),
     * and quantity lines.
     *
     * @return list<Collection<int, PackageChecklistResponse>>
     */
    private function packChecklistPages(CloseoutPackage $package, ?iterable $responses = null): array
    {
        $items = collect($responses ?? $package->checklistResponses)->values();

        if ($items->isEmpty()) {
            return [collect([])];
        }

        $tableBudget = self::CHECKLIST_PAGE_MAX_HEIGHT - self::CHECKLIST_BANNER_HEIGHT;

        $pages = [];
        $current = [];
        $currentHeight = 0;

        foreach ($items as $item) {
            $itemHeight = $this->calculateChecklistItemHeight($item);

            if ($current !== [] && ($currentHeight + $itemHeight) > $tableBudget) {
                $pages[] = collect($current);
                $current = [$item];
                $currentHeight = $itemHeight;
            } else {
                $current[] = $item;
                $currentHeight += $itemHeight;
            }
        }

        if ($current !== []) {
            $pages[] = collect($current);
        }

        return $pages;
    }

    private function additionalNotesPageCount(CloseoutPackage $package): int
    {
        $templateImages = $package->template
            ?->getMedia(Template::ADDITIONAL_NOTES_COLLECTION)
            ->sortBy('order_column')
            ->take(Template::ADDITIONAL_NOTES_IMAGE_LIMIT)
            ->values() ?? collect();

        $packageImages = $package
            ->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)
            ->sortBy('order_column')
            ->take(CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT)
            ->values();

        $allImages = $templateImages->concat($packageImages)->take(Template::ADDITIONAL_NOTES_IMAGE_LIMIT);
        $imageCount = $allImages->count();

        $imagePages = $imageCount > 0 ? (int) ceil($imageCount / 4) : 0;

        return 1 + $imagePages;
    }

    private function renderAdditionalNotesPages(
        CloseoutPackage $package,
        int $totalPages,
        string $revisionLabel,
        CarbonImmutable $revisionDateCarbon,
        ?string $logoPath,
        ?string $partnerLogoPath,
        int $startPage,
    ): string {
        $templateNotes = $package->template?->additional_site_notes;
        $notesText = trim((string) ($package->notes ?: ($templateNotes ?: ($package->project?->notes ?? ''))));
        $hasNotesText = $notesText !== '' && $notesText !== '&nbsp;';

        $templateImages = $package->template
            ?->getMedia(Template::ADDITIONAL_NOTES_COLLECTION)
            ->sortBy('order_column')
            ->take(Template::ADDITIONAL_NOTES_IMAGE_LIMIT)
            ->values() ?? collect();

        $packageImages = $package
            ->getMedia(CloseoutPackage::PACKAGE_SITE_NOTES_COLLECTION)
            ->sortBy('order_column')
            ->take(CloseoutPackage::PACKAGE_SITE_NOTES_IMAGE_LIMIT)
            ->values();

        $allImages = $templateImages->concat($packageImages)->take(Template::ADDITIONAL_NOTES_IMAGE_LIMIT)->values();

        $html = '';
        $currentPage = $startPage;

        // 1. Text Page
        $notesLineLimit = self::ADDITIONAL_NOTES_LINE_LIMIT;
        $notesCharsPerLine = self::ADDITIONAL_NOTES_CHARS_PER_LINE;
        $notesIsTruncated = collect(explode("\n", $notesText))
            ->sum(fn (string $line): int => max(1, (int) ceil(mb_strlen($line) / $notesCharsPerLine))) > $notesLineLimit;

        $html .= view('pdf.sections.additional-notes', [
            'siteInfo' => $package->siteInfo,
            'project' => $package->project,
            'package' => $package,
            'notesText' => $notesText,
            'hasNotesText' => $hasNotesText,
            'notesIsTruncated' => $notesIsTruncated,
            'page' => new PdfPageDTO(
                pageNumber: $currentPage,
                totalPages: $totalPages,
                revisionLabel: $revisionLabel,
                revisionDate: $revisionDateCarbon,
            ),
            'logoPath' => $logoPath,
            'partnerLogoPath' => $partnerLogoPath,
        ])->render();

        $currentPage++;

        // 2. Image Pages (4 images per page in 2x2 grid)
        if ($allImages->isNotEmpty()) {
            $imageChunks = $allImages->chunk(4);

            foreach ($imageChunks as $chunkIdx => $chunk) {
                $isContinuation = $chunkIdx > 0;
                $startImageNumber = ($chunkIdx * 4) + 1;

                $html .= view('pdf.sections.additional-notes-images', [
                    'siteInfo' => $package->siteInfo,
                    'project' => $package->project,
                    'package' => $package,
                    'images' => $chunk,
                    'isContinuation' => $isContinuation,
                    'startImageNumber' => $startImageNumber,
                    'page' => new PdfPageDTO(
                        pageNumber: $currentPage,
                        totalPages: $totalPages,
                        revisionLabel: $revisionLabel,
                        revisionDate: $revisionDateCarbon,
                    ),
                    'logoPath' => $logoPath,
                    'partnerLogoPath' => $partnerLogoPath,
                ])->render();

                $currentPage++;
            }
        }

        return $html;
    }

    private function siteDetailsPageCount(CloseoutPackage $package): int
    {
        $count = $package->siteDetails->count();

        if ($count === 0) {
            return 0;
        }

        return (int) ceil($count / self::SITE_DETAILS_PER_PAGE);
    }

    private function scopePageCount(CloseoutPackage $package): int
    {
        $siteInfo = $package->siteInfo;

        if ($siteInfo === null) {
            return 0;
        }

        $hasScope = trim((string) ($siteInfo->scope_of_work ?? '')) !== '';
        $hasAccessNotes = trim((string) ($siteInfo->site_access_notes ?? '')) !== '';

        return ($hasScope || $hasAccessNotes) ? 1 : 0;
    }

    private function picturePageCount(Template $template): int
    {
        $totalCategories = 0;
        foreach ($template->sections as $section) {
            $totalCategories += $section->categories->count();
        }

        return max(1, (int) ceil($totalCategories / self::PICTURE_CATEGORIES_PER_PAGE));
    }

    private function logoPath(string $configKey): ?string
    {
        /** @var string $path */
        $path = config("cop.{$configKey}");

        if ($path === '' || ! is_file(public_path($path))) {
            try {
                throw new LogoFileMissingException("COP {$configKey} file is missing.");
            } catch (LogoFileMissingException $exception) {
                Log::warning('PDF logo missing', [
                    'path' => $path,
                    'error' => $exception->getMessage(),
                ]);
            }

            return null;
        }

        return public_path($path);
    }

    /**
     * Our own mark: the configured remote logo, inlined by BrandLogoService.
     *
     * Returns a data URI rather than a filesystem path on purpose. The renderer
     * hands this HTML to a headless browser, so a remote <img src> would mean a
     * network fetch on every render and a silently logo-less PDF if the host
     * were down. BrandLogoService caches the bytes and falls back to the
     * bundled asset, keeping output deterministic.
     */
    private function brandLogoSrc(): ?string
    {
        return app(BrandLogoService::class)->dataUri();
    }

    /**
     * The partner's (client's) mark — a local file on disk, not configuration.
     */
    private function partnerLogoSrc(): ?string
    {
        return $this->logoPath('verizon_logo_path');
    }

    private function sitePhotoPath(CloseoutPackage $package): ?string
    {
        $media = $package->getFirstMedia('site_photo')
            ?? $package->project?->getFirstMedia('site_photo');

        if ($media === null || ! is_file($media->getPath())) {
            return null;
        }

        return $media->getPath();
    }
}
