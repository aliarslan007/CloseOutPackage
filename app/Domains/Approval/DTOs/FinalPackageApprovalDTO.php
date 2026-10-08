<?php

declare(strict_types=1);

namespace App\Domains\Approval\DTOs;

final readonly class FinalPackageApprovalDTO
{
    /**
     * @param  list<array{source: string, site_type: string, name: string, auth_site_id?: int|null, region?: string|null, state?: string|null}>  $siteDetails
     */
    public function __construct(
        public int $closeoutPackageId,
        public array $siteDetails = [],
    ) {}
}
