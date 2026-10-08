<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\DTOs;

/**
 * @param  list<int>  $mediaAssetIds
 */
final readonly class ImportMediaAssetsDTO
{
    /**
     * @param  list<int>  $mediaAssetIds
     */
    public function __construct(
        public int $sowReportId,
        public array $mediaAssetIds,
    ) {}
}
