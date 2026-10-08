<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ResolveAllFeedbackNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EntityFeedbackRound $round */
        $round = $this->route('round');

        return Gate::allows('resolveAll', [EntityFeedbackRound::class, $round]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'general_response' => ['nullable', 'string', 'max:'.EntityFeedbackNote::MAX_BODY_LENGTH],
        ];
    }
}
