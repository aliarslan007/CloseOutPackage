<?php

declare(strict_types=1);

namespace App\Domains\Tracking\Requests;

use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Enums\TrackingStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LocationPingRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Project $project */
        $project = $this->route('project');

        // Any authenticated user assigned to the project may share their location.
        return $project->fieldSupervisors()->whereKey($this->user()?->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:2000'],
            'status' => ['nullable', Rule::enum(TrackingStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.between' => 'Latitude must be between -90 and 90.',
            'longitude.between' => 'Longitude must be between -180 and 180.',
        ];
    }

    public function status(): TrackingStatus
    {
        $status = $this->validated('status');

        return is_string($status) ? TrackingStatus::from($status) : TrackingStatus::ACTIVE;
    }
}
