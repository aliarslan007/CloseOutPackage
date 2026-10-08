<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Controllers;

use App\Domains\ScopeOfWork\Actions\CreateAnnotatedImageAction;
use App\Domains\ScopeOfWork\Actions\DeleteAnnotatedImageAction;
use App\Domains\ScopeOfWork\Actions\ImportMediaAssetsToSowAction;
use App\Domains\ScopeOfWork\Actions\ReorderAnnotatedImagesAction;
use App\Domains\ScopeOfWork\Actions\UpdateAnnotatedImageAction;
use App\Domains\ScopeOfWork\DTOs\AnnotationLayerDTO;
use App\Domains\ScopeOfWork\DTOs\CreateAnnotatedImageDTO;
use App\Domains\ScopeOfWork\DTOs\ImportMediaAssetsDTO;
use App\Domains\ScopeOfWork\Models\SowAnnotatedImage;
use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\ScopeOfWork\Requests\ImportMediaAssetsRequest;
use App\Domains\ScopeOfWork\Requests\ReorderAnnotatedImagesRequest;
use App\Domains\ScopeOfWork\Requests\StoreAnnotatedImageRequest;
use App\Domains\ScopeOfWork\Requests\UpdateAnnotatedImageRequest;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SowAnnotatedImageController extends Controller
{
    public function __construct(
        private readonly CreateAnnotatedImageAction $createAction,
        private readonly UpdateAnnotatedImageAction $updateAction,
        private readonly DeleteAnnotatedImageAction $deleteAction,
        private readonly ReorderAnnotatedImagesAction $reorderAction,
        private readonly ImportMediaAssetsToSowAction $importAction,
    ) {}

    public function store(StoreAnnotatedImageRequest $request, SowReport $report): JsonResponse
    {
        $rawAssetId = $request->validated('media_asset_id');
        $mediaAssetId = is_numeric($rawAssetId) ? (int) $rawAssetId : 0;

        $image = $this->createAction->execute(new CreateAnnotatedImageDTO(
            sowReportId: $report->id,
            mediaAssetId: $mediaAssetId,
        ));

        return response()->json([
            'success' => true,
            'annotated_image' => $image->load('mediaAsset'),
        ]);
    }

    public function importMedia(ImportMediaAssetsRequest $request, SowReport $report): JsonResponse
    {
        /** @var list<int|string> $rawIds */
        $rawIds = $request->validated('media_asset_ids') ?? [];
        $assetIds = array_map(fn (int|string $id): int => (int) $id, $rawIds);

        $imported = $this->importAction->execute($report, new ImportMediaAssetsDTO(
            sowReportId: $report->id,
            mediaAssetIds: $assetIds,
        ));

        return response()->json([
            'success' => true,
            'imported' => $imported,
        ]);
    }

    public function update(UpdateAnnotatedImageRequest $request, SowReport $report, SowAnnotatedImage $image): JsonResponse
    {
        $data = $request->validated();

        /** @var array{baseWidth?: int, nodes?: list<array<string, mixed>>}|null $layer */
        $layer = is_array($data['layer'] ?? null) ? $data['layer'] : null;
        $pageTitle = isset($data['page_title']) && is_string($data['page_title']) ? $data['page_title'] : null;
        $updatedAt = ! empty($data['annotations_updated_at']) && is_string($data['annotations_updated_at'])
            ? CarbonImmutable::parse($data['annotations_updated_at'])
            : null;

        $image = $this->updateAction->execute($image, new AnnotationLayerDTO(
            layer: $layer,
            pageTitle: $pageTitle,
            isCover: array_key_exists('is_cover', $data) ? (bool) $data['is_cover'] : null,
            includeInPdf: array_key_exists('include_in_pdf', $data) ? (bool) $data['include_in_pdf'] : null,
            annotationsUpdatedAt: $updatedAt,
        ));

        return response()->json([
            'success' => true,
            'annotated_image' => $image->load('mediaAsset'),
            // ms precision round-trip — toIso8601String() drops fractional seconds and
            // would make the §9.3 optimistic lock 409 on every second save.
            'annotations_updated_at' => $image->annotations_updated_at?->format('Y-m-d H:i:s.v'),
        ]);
    }

    public function destroy(SowReport $report, SowAnnotatedImage $image): JsonResponse
    {
        $this->authorize('deleteImage', [$report, $image]);

        $this->deleteAction->execute($image);

        return response()->json(['success' => true]);
    }

    public function reorder(ReorderAnnotatedImagesRequest $request, SowReport $report): JsonResponse
    {
        /** @var list<array{id: int, sort_order: int}> $order */
        $order = $request->validated('order');

        $this->reorderAction->execute($report, $order);

        return response()->json(['success' => true]);
    }

    public function compositeStatuses(SowReport $report): JsonResponse
    {
        $this->authorize('view', $report);

        $statuses = $report->annotatedImages()
            ->get(['id', 'composite_status'])
            ->map(fn (SowAnnotatedImage $image): array => [
                'id' => $image->id,
                'composite_status' => $image->composite_status->value,
            ]);

        return response()->json(['statuses' => $statuses]);
    }
}
