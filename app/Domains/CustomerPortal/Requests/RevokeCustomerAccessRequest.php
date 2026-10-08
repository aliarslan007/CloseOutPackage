<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Requests;

use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RevokeCustomerAccessRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN->value)) {
            return true;
        }

        if (! $user->hasRole(Role::MANAGER->value)) {
            return false;
        }

        /** @var Project|string|null $projectParam */
        $projectParam = $this->route('project');
        $project = $projectParam instanceof Project
            ? $projectParam
            : Project::withTrashed()->find($projectParam);

        if ($project === null) {
            return false;
        }

        if ($project->manager_id === $user->id) {
            return true;
        }

        return $project->fieldSupervisors()->whereKey($user->id)->exists();
    }

    protected function prepareForValidation(): void
    {
        /** @var User|string|null $userParam */
        $userParam = $this->route('user');
        /** @var Project|string|null $projectParam */
        $projectParam = $this->route('project');

        $userId = null;
        if ($userParam instanceof User) {
            $userId = $userParam->id;
        } elseif (is_numeric($userParam)) {
            $userId = (int) $userParam;
        }

        $projectId = null;
        if ($projectParam instanceof Project) {
            $projectId = $projectParam->id;
        } elseif (is_numeric($projectParam)) {
            $projectId = (int) $projectParam;
        }

        $this->merge([
            'user_id' => $this->input('user_id', $userId),
            'project_id' => $this->input('project_id', $projectId),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->user();
        $isManager = $user !== null && ! $user->hasRole(Role::ADMIN->value);

        $projectRule = Rule::exists('projects', 'id');
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'project_id' => ['required', 'integer', $projectRule],
        ];
    }
}
