<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateFeedbackNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EntityFeedbackNote $note */
        $note = $this->route('note');

        return Gate::allows('updateNote', [EntityFeedbackNote::class, $note]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.EntityFeedbackNote::MAX_BODY_LENGTH],
        ];
    }

    public function body(): string
    {
        return trim($this->string('body')->toString());
    }
}
