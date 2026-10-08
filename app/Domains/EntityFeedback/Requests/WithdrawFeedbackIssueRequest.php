<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class WithdrawFeedbackIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EntityFeedbackRound $round */
        $round = $this->route('round');

        return Gate::allows('withdrawIssue', [EntityFeedbackRound::class, $round]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // The messages that make up the one conversation being withdrawn.
            // Generous on purpose: threads are unlimited, and a customer who has
            // argued a point 120 times must still be able to drop it.
            'note_ids' => ['required', 'array', 'min:1', 'max:1000'],
            'note_ids.*' => ['integer'],
        ];
    }

    /**
     * @return list<int>
     */
    public function noteIds(): array
    {
        $ids = [];

        foreach ((array) $this->validated('note_ids') as $id) {
            $ids[] = (int) $id;
        }

        return $ids;
    }
}
