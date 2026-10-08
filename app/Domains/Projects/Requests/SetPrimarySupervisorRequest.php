<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\Project;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SetPrimarySupervisorRequest extends FormRequest
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
            // Null clears the primary technician; otherwise it must already be
            // assigned to this project.
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('project_field_supervisors', 'user_id')->where('project_id', $project->id),
            ],
        ];
    }
}
