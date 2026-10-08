<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Requests;

use App\Domains\CustomerPortal\DTOs\GrantCustomerAccessDTO;
use App\Shared\Enums\Role;
use App\Shared\Rules\HasRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GrantCustomerAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasAnyRole([Role::ADMIN->value, Role::MANAGER->value]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isManager = $user !== null && ! $user->hasRole(Role::ADMIN->value);

        $projectRule = Rule::exists('projects', 'id')->whereNull('deleted_at');
        if ($isManager && $user !== null) {
            $projectRule->where(function ($query) use ($user): void {
                $query->where(function ($sub) use ($user): void {
                    $sub->where('manager_id', $user->id)
                        ->orWhereExists(function ($fs) use ($user): void {
                            $fs->selectRaw(1)
                                ->from('project_field_supervisors')
                                ->whereColumn('project_field_supervisors.project_id', 'projects.id')
                                ->where('project_field_supervisors.user_id', $user->id);
                        });
                });
            });
        }

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                new HasRole(Role::CUSTOMER->value),
            ],
            'project_id' => ['required_without:project_ids', 'nullable', 'integer', $projectRule],
            'project_ids' => ['required_without:project_id', 'nullable', 'array', 'min:1'],
            'project_ids.*' => ['integer', $projectRule],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function toDTO(): GrantCustomerAccessDTO
    {
        /** @var int $userId */
        $userId = $this->validated('user_id');
        /** @var list<int> $projectIds */
        $projectIds = [];
        foreach ((array) ($this->validated('project_ids') ?? [$this->validated('project_id')]) as $rawId) {
            if (is_numeric($rawId)) {
                $projectIds[] = (int) $rawId;
            }
        }
        $expiresAt = $this->validated('expires_at');

        return new GrantCustomerAccessDTO(
            userId: $userId,
            projectIds: $projectIds,
            grantedBy: $this->user()?->id,
            expiresAt: is_string($expiresAt) ? CarbonImmutable::parse($expiresAt) : null,
        );
    }
}
