<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use App\Domains\CloseoutPackages\DTOs\UpdateSiteInfoDTO;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateSiteInfoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', [CloseoutPackage::class, $this->route('package')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'site_name' => ['nullable', 'string', 'max:255'],
            'ps_number' => ['nullable', 'string', 'max:20'],
            'project_number' => ['nullable', 'string', 'max:80'],
            'address' => ['nullable', 'string', 'max:500'],
            'complete_date' => ['nullable', 'date'],
            'scope_of_work' => ['nullable', 'string', 'max:2500'],
            'site_access_notes' => ['nullable', 'string', 'max:2500'],
            'vzw_engineer_name' => ['nullable', 'string', 'max:255'],
            'vzw_engineer_phone' => ['nullable', 'string', 'max:50'],
            'vzw_engineer_email' => ['nullable', 'email', 'max:255'],
            'author_company' => ['nullable', 'string', 'max:255'],
            'author_name' => ['nullable', 'string', 'max:255'],
            'author_email' => ['nullable', 'email', 'max:255'],
            'author_phone' => ['nullable', 'string', 'max:50'],
            'pm_company' => ['nullable', 'string', 'max:255'],
            'pm_name' => ['nullable', 'string', 'max:255'],
            'pm_email' => ['nullable', 'email', 'max:255'],
            'pm_phone' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function toDTO(): UpdateSiteInfoDTO
    {
        $date = $this->validated('complete_date');

        return new UpdateSiteInfoDTO(
            siteName: $this->nullableString('site_name'),
            psNumber: $this->nullableString('ps_number'),
            projectNumber: $this->nullableString('project_number'),
            address: $this->nullableString('address'),
            completeDate: is_string($date) ? $date : null,
            scopeOfWork: $this->nullableString('scope_of_work'),
            siteAccessNotes: $this->nullableString('site_access_notes'),
            vzwEngineerName: $this->nullableString('vzw_engineer_name'),
            vzwEngineerPhone: $this->nullableString('vzw_engineer_phone'),
            vzwEngineerEmail: $this->nullableString('vzw_engineer_email'),
            authorCompany: $this->nullableString('author_company'),
            authorName: $this->nullableString('author_name'),
            authorEmail: $this->nullableString('author_email'),
            authorPhone: $this->nullableString('author_phone'),
            pmCompany: $this->nullableString('pm_company'),
            pmName: $this->nullableString('pm_name'),
            pmEmail: $this->nullableString('pm_email'),
            pmPhone: $this->nullableString('pm_phone'),
        );
    }

    /**
     * A key that was not submitted leaves the stored value untouched; a key
     * submitted empty deletes it. ConvertEmptyStringsToNull makes both arrive
     * as null, so presence is what tells the two apart.
     */
    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        if (is_string($value)) {
            return $value;
        }

        return $this->has($key) ? '' : null;
    }
}
