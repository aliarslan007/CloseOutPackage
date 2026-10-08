<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\DTOs\AddFeedbackAnnotationNoteDTO;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\Concerns\BuildsFeedbackNoteDto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AddFeedbackAnnotationNoteRequest extends FormRequest
{
    use BuildsFeedbackNoteDto;

    public function authorize(): bool
    {
        $round = $this->route('round');

        if ($round instanceof EntityFeedbackRound) {
            return Gate::allows('addNote', [EntityFeedbackRound::class, $round]);
        }

        // Guest decision-link path: there is no authenticated user and no round
        // yet — the controller opens one from the token. The token is therefore
        // the credential, and the controller checks it and its expiry. Denied
        // outright when neither shape is present, so an unroutable request
        // cannot fall through.
        return is_string($this->route('token')) && $this->route('token') !== '';
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', Rule::enum(FeedbackEntityType::class)],
            'entity_id' => ['required', 'integer', 'min:1'],
            ...$this->feedbackNoteRules(),
            'body' => ['required_without_all:replacement_image,annotated_image', 'nullable', 'string', 'max:'.EntityFeedbackNote::MAX_BODY_LENGTH],
        ];
    }

    public function toDTO(): AddFeedbackAnnotationNoteDTO
    {
        return $this->buildFeedbackNoteDto(
            FeedbackEntityType::from((string) $this->validated('entity_type')),
            (int) $this->validated('entity_id'),
        );
    }
}
