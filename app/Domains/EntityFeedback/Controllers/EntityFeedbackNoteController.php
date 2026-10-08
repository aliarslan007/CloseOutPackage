<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\AddFeedbackAnnotationNoteAction;
use App\Domains\EntityFeedback\Actions\DeleteFeedbackNoteAction;
use App\Domains\EntityFeedback\Actions\UpdateFeedbackNoteAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\AddFeedbackAnnotationNoteRequest;
use App\Domains\EntityFeedback\Requests\UpdateFeedbackNoteRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EntityFeedbackNoteController extends Controller
{
    public function __construct(
        private readonly AddFeedbackAnnotationNoteAction $addAction,
        private readonly DeleteFeedbackNoteAction $deleteAction,
        private readonly UpdateFeedbackNoteAction $updateAction,
    ) {}

    public function store(
        AddFeedbackAnnotationNoteRequest $request,
        EntityFeedbackRound $round,
    ): RedirectResponse {
        $dto = $request->toDTO();
        $this->addAction->execute($round, $dto);

        return redirect()->back()->with('success', 'Review note saved to draft.');
    }

    public function update(
        UpdateFeedbackNoteRequest $request,
        EntityFeedbackNote $note,
    ): RedirectResponse {
        $this->updateAction->execute($note, (int) $request->user()?->id, $request->body());

        return redirect()->back()->with('success', 'Message updated.');
    }

    public function destroy(EntityFeedbackNote $note): RedirectResponse
    {
        $this->authorize('deleteNote', $note);

        $this->deleteAction->execute($note);

        return redirect()->back()->with('success', 'Review note removed.');
    }
}
