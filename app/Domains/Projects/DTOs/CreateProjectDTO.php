<?php

declare(strict_types=1);

namespace App\Domains\Projects\DTOs;

use Carbon\CarbonImmutable;

final readonly class CreateProjectDTO
{
    public function __construct(
        public int $marketId,
        public string $name,
        public ?string $psNumber = null,
        public ?string $projectNumber = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zip = null,
        public ?int $customerId = null,
        public ?int $customerEngineerId = null,
        public ?string $vzwEngineerName = null,
        public ?string $vzwEngineerEmail = null,
        public ?string $vzwEngineerPhone = null,
        public ?int $managerId = null,
        public ?CarbonImmutable $completedDate = null,
        public ?CarbonImmutable $awardedDate = null,
        public ?CarbonImmutable $recIssuedDate = null,
        public ?CarbonImmutable $constructionStartedDate = null,
        public ?CarbonImmutable $siteCompletedDate = null,
        public ?string $notes = null,
    ) {}
}
