<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\EntityFeedback\Actions\AcknowledgeFeedbackAction;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class EntityFeedbackAcknowledgeController extends Controller
{
    public function __construct(
        private readonly AcknowledgeFeedbackAction $acknowledgeAction,
    ) {}

    public function __invoke(EntityFeedbackRound $round): RedirectResponse
    {
        $this->authorize('acknowledge', $round);

        $this->acknowledgeAction->execute($round, $this->user());

        return redirect()->back()->with('success', 'Acknowledged — the customer has been told work has started.');
    }
}
