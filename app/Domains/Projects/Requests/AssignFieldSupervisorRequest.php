<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use App\Shared\Enums\Role;
use App\Shared\Rules\HasRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignFieldSupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('assignUsers', $this->route('project'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Project $project */
        $project = $this->route('project');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                Rule::unique('project_field_supervisors', 'user_id')->where('project_id', $project->id),
                new HasRole(Role::FIELD_SUPERVISOR->value),
            ],
        ];
    }
}
