<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\AddFeedbackAnnotationNoteAction;
use App\Domains\EntityFeedback\Actions\OpenFeedbackRoundAction;
use App\Domains\EntityFeedback\Requests\OpenFeedbackRoundRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class EntityFeedbackRoundController extends Controller
{
    public function __construct(
        private readonly OpenFeedbackRoundAction $openAction,
        private readonly AddFeedbackAnnotationNoteAction $addAction,
    ) {}

    public function open(OpenFeedbackRoundRequest $request): RedirectResponse
    {
        $dto = $request->toDTO();
        $noteDto = $request->toNoteDTO();

        // One transaction: a round and its opening note are one fact. Splitting
        // them could leave an empty round behind if the note failed.
        DB::transaction(function () use ($dto, $noteDto): void {
            $round = $this->openAction->execute($dto);

            // A customer's first feedback arrives on this endpoint, because there
            // is no round yet to post it to. Creating only the round silently
            // discarded that note, so the customer annotated, saved, and nothing
            // was stored.
            if ($noteDto !== null) {
                $this->addAction->execute($round, $noteDto);
            }
        });

        return redirect()->back()->with(
            'success',
            $noteDto !== null
                ? 'Review note saved to draft.'
                : 'Feedback review round opened.',
        );
    }
}
