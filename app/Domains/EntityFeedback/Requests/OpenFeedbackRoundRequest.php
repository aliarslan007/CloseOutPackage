<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\DTOs\AddFeedbackAnnotationNoteDTO;
use App\Domains\EntityFeedback\DTOs\OpenFeedbackRoundDTO;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\Concerns\BuildsFeedbackNoteDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpenFeedbackRoundRequest extends FormRequest
{
    use BuildsFeedbackNoteDto;

    public function authorize(): bool
    {
        // The class hint is required: an ability called with no arguments
        // cannot resolve a policy and the gate denies, failing closed.
        return Gate::allows('open', [EntityFeedbackRound::class]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::enum(FeedbackEntityType::class)],
            'entity_id' => ['required', 'integer', 'min:1'],
            'manager_instructions' => ['nullable', 'string', 'max:2000'],
            // The customer's very first note arrives here, because there is no
            // round to post it to yet. Accepting it is what stops that first
            // note from being silently dropped.
            ...$this->feedbackNoteRules(),
        ];
    }

    /**
     * The note carried alongside the round, or null for a bare "open a round".
     */
    public function toNoteDTO(): ?AddFeedbackAnnotationNoteDTO
    {
        return $this->buildFeedbackNoteDto(
            FeedbackEntityType::from((string) $this->validated('entity_type')),
            (int) $this->validated('entity_id'),
        );
    }

    public function toDTO(): OpenFeedbackRoundDTO
    {
        $entityTypeString = (string) $this->validated('entity_type');

        return new OpenFeedbackRoundDTO(
            entityType: FeedbackEntityType::from($entityTypeString),
            entityId: (int) $this->validated('entity_id'),
            openedBy: (int) $this->user()?->id,
            managerInstructions: $this->validated('manager_instructions'),
        );
    }
}
