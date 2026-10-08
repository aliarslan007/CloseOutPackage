<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\ForwardFeedbackToTechnicianAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\ForwardFeedbackToTechnicianRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class ForwardFeedbackToTechnicianController extends Controller
{
    public function __construct(
        private readonly ForwardFeedbackToTechnicianAction $forwardAction,
    ) {}

    public function __invoke(
        ForwardFeedbackToTechnicianRequest $request,
        EntityFeedbackRound $round,
    ): RedirectResponse {
        $dto = $request->toDTO();
        /** @var User $user */
        $user = $request->user();

        $this->forwardAction->execute($round, $dto, $user);

        return redirect()->back()->with('success', 'Feedback review assigned to technician.');
    }
}
