<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Requests;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreSowReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', SowReport::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', Rule::exists('markets', 'id')],
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')],
            'title' => ['required', 'string', 'max:120'],
            // Cover photo: a library pick replaces the auto-pulled site photo.
            'cover_media_asset_id' => ['nullable', 'integer', Rule::exists('media_assets', 'id')],
            'include_project_site_photo' => ['nullable', 'boolean'],
            // Page 1 — Cover
            'site_name' => ['nullable', 'string', 'max:120'],
            'report_date' => ['nullable', 'date'],
            'fiber_provider' => ['nullable', 'string', 'max:80'],
            // Page 2 — Table 1: Project Data
            'site_address' => ['nullable', 'string', 'max:250'],
            'site_city' => ['nullable', 'string', 'max:120'],
            'site_county' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'project_description' => ['nullable', 'string', 'max:200'],
            // Page 2 — Table 2: Survey Data
            'field_survey_date' => ['nullable', 'date'],
            'provider_contact_name' => ['nullable', 'string', 'max:255'],
            'provider_contact_phone' => ['nullable', 'string', 'max:50'],
            'provider_contact_email' => ['nullable', 'email', 'max:255'],
            'demarcation' => ['nullable', 'string', 'max:100'],
            'ps_number' => ['nullable', 'string', 'max:20'],
            'survey_contractor_name' => ['nullable', 'string', 'max:255'],
            'survey_contact_name' => ['nullable', 'string', 'max:255'],
            'survey_contact_phone' => ['nullable', 'string', 'max:50'],
            'survey_contact_email' => ['nullable', 'email', 'max:255'],
            'scope_of_work_description' => ['nullable', 'string', 'max:350'],
            'site_access_notes' => ['nullable', 'string', 'max:2500'],
        ];
    }
}
