<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\DTOs\ForwardFeedbackToTechnicianDTO;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ForwardFeedbackToTechnicianRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EntityFeedbackRound $round */
        $round = $this->route('round');

        return Gate::allows('forwardTechnician', [EntityFeedbackRound::class, $round]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function toDTO(): ForwardFeedbackToTechnicianDTO
    {
        return new ForwardFeedbackToTechnicianDTO(
            assignedBy: (int) $this->user()?->id,
            assignedToUserId: $this->validated('assigned_to_user_id') !== null
                ? (int) $this->validated('assigned_to_user_id')
                : null,
            instructions: $this->validated('instructions'),
        );
    }
}
