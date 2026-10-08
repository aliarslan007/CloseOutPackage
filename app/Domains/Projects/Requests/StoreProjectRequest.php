<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\DTOs\CreateProjectDTO;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Rules\EngineerBelongsToRegion;
use App\Domains\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', Project::class);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $rawManagerId = $validator->getData()['manager_id'] ?? null;

            if ($rawManagerId === null || $rawManagerId === '' || $validator->errors()->has('manager_id')) {
                return;
            }

            $rawMarketId = $validator->getData()['market_id'] ?? null;
            if (! is_numeric($rawMarketId)) {
                return;
            }
            $marketId = (int) $rawMarketId;

            $manager = User::find((int) $rawManagerId);

            if ($manager === null || ! $manager->markets()->whereKey($marketId)->exists()) {
                $validator->errors()->add(
                    'manager_id',
                    'The selected manager does not have access to this market. Assign the market to the manager first.'
                );
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'market_id' => ['required', 'integer', 'exists:markets,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')
                    ->where('market_id', $this->input('market_id'))
                    ->withoutTrashed(),
            ],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_engineer_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_engineers', 'id')->whereNull('deleted_at'),
                new EngineerBelongsToRegion($this->input('customer_id'), $this->input('market_id')),
            ],
            'ps_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'project_number' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9\-]+$/'],
            // Required: final package approval falls back to the project address
            // when the package site info has none (ApprovalReadinessService).
            'address' => ['required', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z\s\-]+$/'],
            'state' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z\s\-]+$/'],
            'zip' => ['nullable', 'string', 'max:20', 'regex:/^\d{5}(-\d{4})?$/'],
            // VZW Engineer fields: optional — CreateProjectAction fills blanks
            // from the global App Settings defaults (admin can still override).
            'vzw_engineer_name' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z\s.\'-]+$/'],
            'vzw_engineer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'vzw_engineer_phone' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(\+\d{1,2}\s?)?(\(\d{3}\)|\d{3})[\s.\-]?\d{3}[\s.\-]?\d{4}$/'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            // Real-world sanity: a deadline in the past is not a deadline.
            'completed_date' => ['required', 'date', 'after_or_equal:today'],
            // Every timeline date may be actual (past) or planned (future) —
            // construction starting next week is normal at creation time. Only
            // ancient typos are blocked: a 2010 award date is not history.
            'awarded_date' => ['required', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'rec_issued_date' => ['required', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'construction_started_date' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'site_completed_date' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Optional site photo, same rules as the project detail uploader.
            'site_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:20480'],
        ];
    }

    /**
     * Plain-language guidance instead of "must be a date after today".
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'completed_date.after_or_equal' => "The customer's desired completion date can't be in the past — pick today or a future date.",
            'awarded_date.after_or_equal' => 'The award date must be within the last 5 years. Future dates are fine for projects not yet awarded.',
            'rec_issued_date.after_or_equal' => 'The REC issue date must be within the last 5 years. Future dates are fine for RECs not yet issued.',
            'construction_started_date.after_or_equal' => 'The construction start date must be within the last 5 years. Future dates are fine for planned work.',
            'site_completed_date.after_or_equal' => 'The site completion date must be within the last 5 years. Future dates are fine for planned work.',
        ];
    }

    public function toDTO(): CreateProjectDTO
    {
        $completedDate = $this->validated('completed_date');

        $marketId = $this->validated('market_id');
        $name = $this->validated('name');
        $managerId = $this->validated('manager_id');
        $customerId = $this->validated('customer_id');
        $customerEngineerId = $this->validated('customer_engineer_id');

        return new CreateProjectDTO(
            marketId: is_numeric($marketId) ? (int) $marketId : 0,
            name: is_string($name) ? $name : '',
            psNumber: $this->nullableString('ps_number'),
            projectNumber: $this->nullableString('project_number'),
            address: $this->nullableString('address'),
            city: $this->nullableString('city'),
            state: $this->nullableString('state'),
            zip: $this->nullableString('zip'),
            customerId: is_numeric($customerId) ? (int) $customerId : null,
            customerEngineerId: is_numeric($customerEngineerId) ? (int) $customerEngineerId : null,
            vzwEngineerName: $this->nullableString('vzw_engineer_name'),
            vzwEngineerEmail: $this->nullableString('vzw_engineer_email'),
            vzwEngineerPhone: $this->nullableString('vzw_engineer_phone'),
            managerId: is_numeric($managerId) ? (int) $managerId : null,
            completedDate: is_string($completedDate) ? CarbonImmutable::parse($completedDate) : null,
            awardedDate: $this->nullableDate('awarded_date'),
            recIssuedDate: $this->nullableDate('rec_issued_date'),
            constructionStartedDate: $this->nullableDate('construction_started_date'),
            siteCompletedDate: $this->nullableDate('site_completed_date'),
            notes: $this->nullableString('notes'),
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }

    private function nullableDate(string $key): ?CarbonImmutable
    {
        $value = $this->validated($key);

        return is_string($value) ? CarbonImmutable::parse($value) : null;
    }
}
