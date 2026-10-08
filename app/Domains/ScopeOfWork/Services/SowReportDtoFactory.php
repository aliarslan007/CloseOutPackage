<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Services;

use App\Domains\ScopeOfWork\DTOs\CreateSowReportDTO;
use Carbon\CarbonImmutable;

/**
 * Maps validated SOW create input into a CreateSowReportDTO, coercing the loose
 * request values into their typed equivalents. Extracted from
 * SowReportController so the controller stays a thin HTTP layer.
 */
final class SowReportDtoFactory
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): CreateSowReportDTO
    {
        $title = is_string($data['title'] ?? null) ? $data['title'] : '';
        $projectId = isset($data['project_id']) && is_numeric($data['project_id']) ? (int) $data['project_id'] : null;
        $marketId = is_numeric($data['market_id'] ?? null) ? (int) $data['market_id'] : 0;

        return new CreateSowReportDTO(
            marketId: $marketId,
            title: $title,
            projectId: $projectId,
            coverMediaAssetId: isset($data['cover_media_asset_id']) && is_numeric($data['cover_media_asset_id'])
                ? (int) $data['cover_media_asset_id']
                : null,
            includeProjectSitePhoto: array_key_exists('include_project_site_photo', $data)
                ? (bool) $data['include_project_site_photo']
                : null,
            siteName: $this->str($data['site_name'] ?? null),
            reportDate: $this->date($data['report_date'] ?? null),
            fiberProvider: $this->str($data['fiber_provider'] ?? null),
            siteAddress: $this->str($data['site_address'] ?? null),
            siteCity: $this->str($data['site_city'] ?? null),
            siteCounty: $this->str($data['site_county'] ?? null),
            latitude: $this->float($data['latitude'] ?? null),
            longitude: $this->float($data['longitude'] ?? null),
            projectDescription: $this->str($data['project_description'] ?? null),
            fieldSurveyDate: $this->date($data['field_survey_date'] ?? null),
            providerContactName: $this->str($data['provider_contact_name'] ?? null),
            providerContactPhone: $this->str($data['provider_contact_phone'] ?? null),
            providerContactEmail: $this->str($data['provider_contact_email'] ?? null),
            demarcation: $this->str($data['demarcation'] ?? null),
            psNumber: $this->str($data['ps_number'] ?? null),
            surveyContractorName: $this->str($data['survey_contractor_name'] ?? null),
            surveyContactName: $this->str($data['survey_contact_name'] ?? null),
            surveyContactPhone: $this->str($data['survey_contact_phone'] ?? null),
            surveyContactEmail: $this->str($data['survey_contact_email'] ?? null),
            scopeOfWorkDescription: $this->str($data['scope_of_work_description'] ?? null),
            siteAccessNotes: $this->str($data['site_access_notes'] ?? null),
        );
    }

    private function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        return is_string($value) && $value !== '' ? CarbonImmutable::parse($value) : null;
    }

    private function float(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
