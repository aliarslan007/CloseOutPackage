<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

final readonly class CreatePackageDTO
{
    /**
     * @param  array<string, mixed>  $siteInfo
     */
    public function __construct(
        public int $projectId,
        public int $templateId,
        public array $siteInfo = [],
        public ?int $favoriteId = null,
    ) {}
}
