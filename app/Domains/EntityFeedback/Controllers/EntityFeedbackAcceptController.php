<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\AcceptFeedbackRoundAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EntityFeedbackAcceptController extends Controller
{
    public function __construct(
        private readonly AcceptFeedbackRoundAction $acceptAction,
    ) {}

    public function __invoke(Request $request, EntityFeedbackRound $round): RedirectResponse
    {
        // Fail closed: the ability is checked before the action runs, so an
        // unauthorised user can never reach the round transition.
        $this->authorize('accept', $round);

        /** @var User $user */
        $user = $request->user();
        $this->acceptAction->execute($round, $user);

        return redirect()->back()->with('success', 'Feedback revisions accepted.');
    }
}
