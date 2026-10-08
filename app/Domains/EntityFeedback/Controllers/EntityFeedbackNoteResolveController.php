<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\ResolveFeedbackNoteAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Requests\ResolveFeedbackNoteRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EntityFeedbackNoteResolveController extends Controller
{
    public function __construct(
        private readonly ResolveFeedbackNoteAction $resolveAction,
    ) {}

    public function __invoke(
        ResolveFeedbackNoteRequest $request,
        EntityFeedbackNote $note,
    ): RedirectResponse {
        $dto = $request->toDTO();
        $this->resolveAction->execute($note, $dto);

        return redirect()->back()->with('success', 'Review note response saved.');
    }
}
