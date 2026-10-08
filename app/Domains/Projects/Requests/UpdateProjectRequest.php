<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\DTOs\UpdateProjectDTO;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Rules\EngineerBelongsToRegion;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Services\IdObfuscatorService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('project'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $project = $this->resolveProject();

            if ($project === null) {
                return;
            }

            $rawManagerId = $validator->getData()['manager_id'] ?? null;
            $user = $this->user();

            // A project's manager is fixed for its owning manager — only admins
            // may reassign it (the UI renders the manager read-only for managers).
            if (
                $rawManagerId !== null
                && $rawManagerId !== ''
                && is_numeric($rawManagerId)
                && (int) $rawManagerId !== (int) $project->manager_id
                && ! ($user instanceof User && $user->hasRole(Role::ADMIN->value))
            ) {
                $validator->errors()->add('manager_id', 'Only an administrator can change the project manager.');

                return;
            }

            if ($rawManagerId === null || $rawManagerId === '' || $validator->errors()->has('manager_id')) {
                return;
            }

            $rawMarketId = $validator->getData()['market_id'] ?? null;
            $marketId = is_numeric($rawMarketId) ? (int) $rawMarketId : (int) $project->market_id;

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
        $project = $this->resolveProject();
        $projectId = $project?->id;

        /** @var int|null $marketId */
        $marketId = $this->input('market_id') ?? $project?->market_id;

        return [
            'market_id' => ['nullable', 'integer', 'exists:markets,id'],
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('projects', 'name')
                    ->where('market_id', $marketId)
                    ->withoutTrashed()
                    ->ignore($projectId),
            ],
            'ps_number' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9\-]+$/'],
            'project_number' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9\-]+$/'],
            'address' => ['sometimes', 'required', 'string', 'max:500'],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->whereNull('deleted_at')],
            'customer_engineer_id' => [
                'nullable',
                'integer',
                Rule::exists('customer_engineers', 'id')->whereNull('deleted_at'),
                new EngineerBelongsToRegion(
                    $this->input('customer_id', $project->customer_id),
                    $this->input('market_id', $project->market_id),
                ),
            ],
            'city' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z\s\-]+$/'],
            'state' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z\s\-]+$/'],
            'zip' => ['nullable', 'string', 'max:20', 'regex:/^\d{5}(-\d{4})?$/'],
            // VZW Engineer fields: per-project override; blank keeps existing value
            // (UpdateProjectAction only writes non-null DTO values).
            'vzw_engineer_name' => ['sometimes', 'nullable', 'string', 'max:255', 'regex:/^[A-Za-z\s.\'-]+$/'],
            'vzw_engineer_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'vzw_engineer_phone' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^(\+\d{1,2}\s?)?(\(\d{3}\)|\d{3})[\s.\-]?\d{3}[\s.\-]?\d{4}$/'],
            'manager_id' => ['nullable', 'integer', 'exists:users,id'],
            'completed_date' => ['sometimes', 'required', 'date'],
            // Editable but never hard-required on update: pre-existing projects
            // may not have these yet, and a partial edit must still save. No
            // after-today check on completed_date either — legacy projects
            // legitimately have elapsed deadlines. Like create, every date may
            // be actual or planned; only ancient typos are blocked.
            'awarded_date' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'rec_issued_date' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'construction_started_date' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'site_completed_date' => ['nullable', 'date', 'after_or_equal:'.now()->subYears(5)->toDateString()],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::enum(ProjectStatus::class)],
            // Optional site photo; uploading one replaces the existing photo
            // (the collection is singleFile).
            'site_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,heic', 'max:20480'],
        ];
    }

    /**
     * Plain-language guidance matching the create form's education.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'awarded_date.after_or_equal' => 'The award date must be within the last 5 years. Future dates are fine for projects not yet awarded.',
            'rec_issued_date.after_or_equal' => 'The REC issue date must be within the last 5 years. Future dates are fine for RECs not yet issued.',
            'construction_started_date.after_or_equal' => 'The construction start date must be within the last 5 years. Future dates are fine for planned work.',
            'site_completed_date.after_or_equal' => 'The site completion date must be within the last 5 years. Future dates are fine for planned work.',
        ];
    }

    public function toDTO(): UpdateProjectDTO
    {
        $completedDate = $this->validated('completed_date');
        $status = $this->validated('status');

        $marketId = $this->validated('market_id');
        $managerId = $this->validated('manager_id');
        $customerId = $this->validated('customer_id');
        $customerEngineerId = $this->validated('customer_engineer_id');

        return new UpdateProjectDTO(
            marketId: is_numeric($marketId) ? (int) $marketId : null,
            name: $this->nullableString('name'),
            psNumber: $this->nullableString('ps_number'),
            projectNumber: $this->nullableString('project_number'),
            address: $this->nullableString('address'),
            city: $this->nullableString('city'),
            state: $this->nullableString('state'),
            zip: $this->nullableString('zip'),
            customerId: is_numeric($customerId) ? (int) $customerId : null,
            customerProvided: $this->has('customer_id'),
            customerEngineerId: is_numeric($customerEngineerId) ? (int) $customerEngineerId : null,
            customerEngineerProvided: $this->has('customer_engineer_id'),
            vzwEngineerName: $this->nullableString('vzw_engineer_name'),
            vzwEngineerEmail: $this->nullableString('vzw_engineer_email'),
            vzwEngineerPhone: $this->nullableString('vzw_engineer_phone'),
            managerId: is_numeric($managerId) ? (int) $managerId : null,
            completedDate: is_string($completedDate) ? CarbonImmutable::parse($completedDate) : null,
            awardedDate: $this->nullableDate('awarded_date'),
            recIssuedDate: $this->nullableDate('rec_issued_date'),
            constructionStartedDate: $this->nullableDate('construction_started_date'),
            siteCompletedDate: $this->nullableDate('site_completed_date'),
            status: is_string($status) ? ProjectStatus::from($status) : null,
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

    private function resolveProject(): ?Project
    {
        /** @var Project|string|int|null $project */
        $project = $this->route('project');

        if ($project instanceof Project) {
            return $project;
        }

        if (is_string($project) || is_numeric($project)) {
            return Project::query()->find(IdObfuscatorService::decode((string) $project))
                ?? Project::query()->find($project);
        }

        return null;
    }
}
