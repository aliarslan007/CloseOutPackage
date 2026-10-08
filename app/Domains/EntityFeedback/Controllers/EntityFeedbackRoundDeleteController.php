<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\DeleteFeedbackRoundAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class EntityFeedbackRoundDeleteController extends Controller
{
    public function __construct(
        private readonly DeleteFeedbackRoundAction $deleteAction,
    ) {}

    public function __invoke(EntityFeedbackRound $round): RedirectResponse
    {
        $this->authorize('deleteRound', $round);

        $this->deleteAction->execute($round);

        return redirect()->back()->with('success', 'Review deleted.');
    }
}
