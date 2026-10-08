<?php

declare(strict_types=1);

namespace App\Domains\Crew\Requests;

use App\Domains\Crew\Models\CrewAssignment;
use App\Domains\Templates\Enums\StageType;
use App\Shared\Enums\Role;
use App\Shared\Rules\HasRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AssignCrewMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manage', [CrewAssignment::class, $this->route('project')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                new HasRole(Role::FIELD_SUPERVISOR->value),
            ],
            'stage' => ['nullable', Rule::enum(StageType::class)],
            'role_description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
