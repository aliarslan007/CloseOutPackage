<?php

declare(strict_types=1);

namespace App\Domains\Projects\DTOs;

use App\Domains\Projects\Enums\ProjectStatus;
use Carbon\CarbonImmutable;

final readonly class UpdateProjectDTO
{
    public function __construct(
        public ?int $marketId = null,
        public ?string $name = null,
        public ?string $psNumber = null,
        public ?string $projectNumber = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $state = null,
        public ?string $zip = null,
        public ?int $customerId = null,
        /** True when the form submitted the customer field (so null means "unlink"). */
        public bool $customerProvided = false,
        public ?int $customerEngineerId = null,
        /** True when the form submitted the engineer field (so null means "clear"). */
        public bool $customerEngineerProvided = false,
        public ?string $vzwEngineerName = null,
        public ?string $vzwEngineerEmail = null,
        public ?string $vzwEngineerPhone = null,
        public ?int $managerId = null,
        public ?CarbonImmutable $completedDate = null,
        public ?CarbonImmutable $awardedDate = null,
        public ?CarbonImmutable $recIssuedDate = null,
        public ?CarbonImmutable $constructionStartedDate = null,
        public ?CarbonImmutable $siteCompletedDate = null,
        public ?ProjectStatus $status = null,
        public ?string $notes = null,
    ) {}
}
