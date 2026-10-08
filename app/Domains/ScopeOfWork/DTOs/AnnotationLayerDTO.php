<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\DTOs;

use Carbon\CarbonImmutable;

/**
 * @param  array{baseWidth?: int, nodes?: list<array<string, mixed>>}|null  $layer
 */
final readonly class AnnotationLayerDTO
{
    /**
     * @param  array{baseWidth?: int, nodes?: list<array<string, mixed>>}|null  $layer
     */
    public function __construct(
        public ?array $layer = null,
        public ?string $pageTitle = null,
        public ?bool $isCover = null,
        public ?bool $includeInPdf = null,
        public ?CarbonImmutable $annotationsUpdatedAt = null,
    ) {}
}
