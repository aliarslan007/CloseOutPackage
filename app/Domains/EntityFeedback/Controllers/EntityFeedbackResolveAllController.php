<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\ResolveAllFeedbackNotesAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\ResolveAllFeedbackNotesRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class EntityFeedbackResolveAllController extends Controller
{
    public function __construct(
        private readonly ResolveAllFeedbackNotesAction $resolveAllAction,
    ) {}

    public function __invoke(
        ResolveAllFeedbackNotesRequest $request,
        EntityFeedbackRound $round,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();
        $generalResponse = $request->validated('general_response');

        $this->resolveAllAction->execute($round, $user, $generalResponse);

        return redirect()->back()->with('success', 'All feedback resolved and returned to customer.');
    }
}
