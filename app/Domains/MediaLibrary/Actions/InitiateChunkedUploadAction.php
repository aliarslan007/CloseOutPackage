<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Actions;

use App\Domains\MediaLibrary\Enums\MediaAssetStatus;
use App\Domains\MediaLibrary\Models\MediaAsset;
use App\Domains\MediaLibrary\Requests\InitiateUploadRequest;
use App\Domains\MediaLibrary\Services\ChunkStorageService;
use App\Domains\MediaLibrary\Services\MediaQuotaService;
use App\Domains\MediaLibrary\Services\UploadTokenService;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

final class InitiateChunkedUploadAction
{
    /**
     * @return array{asset_id: int, upload_token: string}
     */
    public function execute(InitiateUploadRequest $request, User $uploader, ?Project $project = null): array
    {
        // 1. Optimistic quota pre-check against the declared size.
        app(MediaQuotaService::class)->assertQuota($uploader, $request->integer('total_size'));

        // 2. Market is inferred from the project the controller already loaded
        //    (no second query); a project-less upload carries it explicitly.
        $marketId = $project !== null
            ? $project->market_id
            : ($request->filled('market_id') ? $request->integer('market_id') : null);

        // 3. Create the asset row (processing until the job completes it).
        $asset = MediaAsset::create([
            'user_id' => $uploader->id,
            'market_id' => $marketId,
            'project_id' => $project?->id,
            'original_filename' => Str::limit(basename($request->string('filename')->toString()), 490, ''),
            'mime_type' => $request->string('mime_type')->toString(),
            'file_size_bytes' => $request->integer('total_size'),
            'total_chunks' => $request->integer('total_chunks'),
            'captured_at' => $request->filled('captured_at')
                ? CarbonImmutable::parse($request->string('captured_at')->toString())
                : null,
            'status' => MediaAssetStatus::PROCESSING,
        ]);

        // 4. Issue the stateless upload token bound to this asset + user.
        $token = app(UploadTokenService::class)->issue($asset->id, $uploader->id);

        // 5. Clean up any leftover chunk directory for this asset ID.
        app(ChunkStorageService::class)->cleanup($asset->id);

        return ['asset_id' => $asset->id, 'upload_token' => $token];
    }
}
