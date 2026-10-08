<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\DTOs;

use Carbon\CarbonImmutable;

final readonly class CreateSowReportDTO
{
    public function __construct(
        public int $marketId,
        public string $title,
        public ?int $projectId = null,
        public ?int $coverMediaAssetId = null,
        public ?bool $includeProjectSitePhoto = null,
        public ?string $siteName = null,
        public ?CarbonImmutable $reportDate = null,
        public ?string $fiberProvider = null,
        public ?string $siteAddress = null,
        public ?string $siteCity = null,
        public ?string $siteCounty = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $projectDescription = null,
        public ?CarbonImmutable $fieldSurveyDate = null,
        public ?string $providerContactName = null,
        public ?string $providerContactPhone = null,
        public ?string $providerContactEmail = null,
        public ?string $demarcation = null,
        public ?string $psNumber = null,
        public ?string $surveyContractorName = null,
        public ?string $surveyContactName = null,
        public ?string $surveyContactPhone = null,
        public ?string $surveyContactEmail = null,
        public ?string $scopeOfWorkDescription = null,
        public ?string $siteAccessNotes = null,
    ) {}
}
