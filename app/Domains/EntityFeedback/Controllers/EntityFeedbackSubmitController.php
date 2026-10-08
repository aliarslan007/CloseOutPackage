<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\SubmitFeedbackToManagerAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntityFeedbackSubmitController extends Controller
{
    public function __construct(
        private readonly SubmitFeedbackToManagerAction $submitAction,
    ) {}

    public function __invoke(Request $request, EntityFeedbackRound $round): RedirectResponse
    {
        // Fail closed: the ability is checked before the action runs, so an
        // unauthorised user can never reach the round transition.
        $this->authorize('submit', $round);

        /** @var User $user */
        $user = $request->user();
        $this->submitAction->execute($round, $user);

        return redirect()->back()->with('success', 'Feedback submitted to manager successfully.');
    }
}
