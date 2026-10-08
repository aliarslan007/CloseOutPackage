<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\WithdrawFeedbackIssueAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\WithdrawFeedbackIssueRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EntityFeedbackWithdrawIssueController extends Controller
{
    public function __construct(
        private readonly WithdrawFeedbackIssueAction $withdrawAction,
    ) {}

    public function __invoke(
        WithdrawFeedbackIssueRequest $request,
        EntityFeedbackRound $round,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        $this->withdrawAction->execute($round, $user, $request->noteIds());

        return redirect()->back()->with('success', 'Issue withdrawn from this review.');
    }
}
