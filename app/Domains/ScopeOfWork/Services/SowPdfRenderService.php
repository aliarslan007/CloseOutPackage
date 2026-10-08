<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowImageGroup;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Shared\Services\BrandLogoService;
use Illuminate\Database\Eloquent\Collection;

final class SowPdfRenderService
{
    private const BASE_WIDTH = 1200;

    /** Photo cards per page. */
    private const PHOTOS_PER_PAGE = 2;

    public function __construct(
        private readonly SowSvgOverlayRenderer $svgRenderer = new SowSvgOverlayRenderer,
    ) {}

    /**
     * @return array{html: string, totalPages: int}
     */
    public function render(SowReport $report): array
    {
        $report->loadMissing([
            'project.manager',
            'market',
            'creator',
            'checklistItems.response',
            'annotatedImages.mediaAsset.media',
            'imageGroups',
            'coverPhoto.media',
        ]);

        $includedImages = $report->annotatedImages
            ->filter(fn (SowAnnotatedImage $img): bool => (bool) $img->include_in_pdf)
            ->sortBy('sort_order')
            ->values();

        $photoPages = $this->buildPhotoPages($report, $includedImages);

        // Pagination: Page 1 (Cover), Page 2 (Summary Data), Page 3 (Checklist), Pages 4+ (Photos)
        $totalPages = 3 + count($photoPages);

        $revisionLabel = 'R'.max(1, (int) $report->revision).'.0';
        // The footer date reflects when this PDF is generated, not when the
        // report revision was created — a report drafted earlier must still
        // carry today's date when its PDF is produced.
        $revisionDate = now()->format('m/d/Y');

        $html = view('pdf.sow', [
            'report' => $report,
            'project' => $report->project,
            'checklist' => $report->checklist(),
            'photoPages' => $photoPages,
            'totalPages' => $totalPages,
            'revisionLabel' => $revisionLabel,
            'revisionDate' => $revisionDate,
            'logoDataUri' => app(BrandLogoService::class)->dataUri(),
            'verizonLogoDataUri' => $this->fileToDataUri(public_path((string) config('cop.verizon_logo_path', 'assets/verizon-logo.png'))),
            'sowLogoDataUri' => $this->fileToDataUri(public_path((string) config('cop.sow_logo_path', 'assets/sow-logo.png'))),
            'coverPhotoDataUri' => $this->resolveCoverPhotoUri($report),
        ])->render();

        return [
            'html' => $html,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Photos are rendered group by group: each group contributes a headed
     * section, and pages within a section carry `isContinued` so a heading can
     * repeat on every page of a long group.
     *
     * A report with no groups yields a single headless section, so its output is
     * unchanged from before grouping existed.
     *
     * @param  Collection<int, SowAnnotatedImage>  $includedImages
     * @return list<array{heading: ?string, isContinued: bool, photos: list<array<string, mixed>>}>
     */
    private function buildPhotoPages(SowReport $report, Collection $includedImages): array
    {
        $groups = $report->imageGroups;

        /** @var list<array{heading: ?string, photos: list<array<string, mixed>>}> $sections */
        $sections = [];

        foreach ($groups as $group) {
            $photos = $includedImages
                ->where('sow_image_group_id', $group->id)
                ->map(fn (SowAnnotatedImage $img): array => $this->buildPhotoCard($img))
                ->values()
                ->all();

            // A group whose photos are all excluded from the PDF must not leave
            // behind an orphan heading or a blank page.
            if ($photos === []) {
                continue;
            }

            $sections[] = ['heading' => $group->name, 'photos' => $photos];
        }

        $ungrouped = $includedImages
            ->whereNull('sow_image_group_id')
            ->map(fn (SowAnnotatedImage $img): array => $this->buildPhotoCard($img))
            ->values()
            ->all();

        if ($ungrouped !== []) {
            // Only named once the report actually uses groups — otherwise a
            // group-less report would suddenly grow a heading it never had.
            $sections[] = [
                'heading' => $groups->isEmpty() ? null : SowImageGroup::UNGROUPED_HEADING,
                'photos' => $ungrouped,
            ];
        }

        $pages = [];
        foreach ($sections as $section) {
            foreach (array_chunk($section['photos'], self::PHOTOS_PER_PAGE) as $index => $chunk) {
                $pages[] = [
                    'heading' => $section['heading'],
                    'isContinued' => $index > 0,
                    'photos' => $chunk,
                ];
            }
        }

        return $pages;
    }

    /**
     * @return array{imageDataUri: ?string, svgOverlay: string, baseWidth: int, logicalHeight: int}
     */
    private function buildPhotoCard(SowAnnotatedImage $image): array
    {
        $imageDataUri = $this->resolveMediaAssetUri($image->mediaAsset);
        $layer = is_array($image->annotation_layer) ? $image->annotation_layer : [];
        $nodes = is_array($layer['nodes'] ?? null) ? $layer['nodes'] : [];

        $logicalHeight = (int) round(self::BASE_WIDTH * (3 / 4));
        $aspectRatio = 4.0 / 3.0;

        if ($image->mediaAsset !== null) {
            $media = $image->mediaAsset->getFirstMedia(MediaAsset::COLLECTION);
            $path = $media?->getPath();
            if ($path !== null && is_file($path)) {
                $size = @getimagesize($path);
                if (is_array($size) && ($size[0] ?? 0) > 0 && ($size[1] ?? 0) > 0) {
                    $logicalHeight = (int) round(self::BASE_WIDTH * ($size[1] / $size[0]));
                    $aspectRatio = (float) $size[0] / (float) $size[1];
                }
            }
        }

        $cardWidthMm = 180.0;
        $cardMaxHeightMm = 123.0;
        $cardAspect = $cardWidthMm / $cardMaxHeightMm;
        $scaleRatio = $aspectRatio < $cardAspect ? ($cardMaxHeightMm * $aspectRatio) / $cardWidthMm : 1.0;
        $portraitScale = 1.0 / max(0.35, $scaleRatio);

        $svgOverlay = $this->svgRenderer->render($nodes, self::BASE_WIDTH, $logicalHeight, $portraitScale);

        return [
            'imageDataUri' => $imageDataUri,
            'svgOverlay' => $svgOverlay,
            'baseWidth' => self::BASE_WIDTH,
            'logicalHeight' => $logicalHeight,
        ];
    }

    private function resolveCoverPhotoUri(SowReport $report): ?string
    {
        $coverAsset = $report->coverPhoto
            ?? $report->annotatedImages->firstWhere('is_cover', true)?->mediaAsset;

        $uri = $coverAsset !== null ? $this->resolveMediaAssetUri($coverAsset) : null;
        if ($uri !== null) {
            return $uri;
        }

        $projectMediaPath = $report->project?->getFirstMedia('site_photo')?->getPath();

        return $projectMediaPath !== null && is_file($projectMediaPath)
            ? $this->fileToDataUri($projectMediaPath)
            : null;
    }

    private function resolveMediaAssetUri(?MediaAsset $asset): ?string
    {
        $media = $asset?->getFirstMedia(MediaAsset::COLLECTION);
        if ($media === null) {
            return null;
        }

        // 1. Prefer the high-res 2000px 'medium' conversion for print PDF
        if ($media->hasGeneratedConversion('medium')) {
            $path = $media->getPath('medium');
            if (is_file($path)) {
                return $this->fileToDataUri($path);
            }
        }

        // 2. Fall back to 'thumb' (1000px) if medium is pending
        if ($media->hasGeneratedConversion('thumb')) {
            $path = $media->getPath('thumb');
            if (is_file($path)) {
                return $this->fileToDataUri($path);
            }
        }

        // 3. Fall back to original file only if no conversions exist
        $originalPath = $media->getPath();

        return is_file($originalPath) ? $this->fileToDataUri($originalPath) : null;
    }

    private function fileToDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $mime = mime_content_type($path) ?: 'image/jpeg';
        $contents = file_get_contents($path);
        if ($contents === false) {
            return null;
        }

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
