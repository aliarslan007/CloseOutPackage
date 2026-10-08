<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Controllers;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\EntityFeedback\Actions\AcceptFeedbackRoundAction;
use App\Domains\EntityFeedback\Actions\AddFeedbackAnnotationNoteAction;
use App\Domains\EntityFeedback\Actions\OpenFeedbackRoundAction;
use App\Domains\EntityFeedback\Actions\SubmitFeedbackToManagerAction;
use App\Domains\EntityFeedback\DTOs\OpenFeedbackRoundDTO;
use App\Domains\EntityFeedback\Enums\FeedbackEntityType;
use App\Domains\EntityFeedback\Models\EntityFeedbackRound;
use App\Domains\EntityFeedback\Requests\AddFeedbackAnnotationNoteRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Guest decision-link feedback. The emailed token is the credential here: there
 * is no authenticated user, so the token lookup plus its expiry check is what
 * authorises the request.
 */
class TokenEntityFeedbackController extends Controller
{
    public function __construct(
        private readonly OpenFeedbackRoundAction $openAction,
        private readonly AddFeedbackAnnotationNoteAction $addAction,
        private readonly SubmitFeedbackToManagerAction $submitAction,
        private readonly AcceptFeedbackRoundAction $acceptAction,
    ) {}

    public function storeNote(AddFeedbackAnnotationNoteRequest $request, string $token): RedirectResponse
    {
        $recipient = ChangeOrderRecipient::query()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->with(['changeOrder', 'user'])
            ->firstOrFail();

        if ($recipient->hasExpired()) {
            return redirect()->back()->with('error', 'This decision link has expired.');
        }

        /** @var ChangeOrder $changeOrder */
        $changeOrder = $recipient->changeOrder;
        /** @var User $author */
        $author = $recipient->user;

        $round = $this->openAction->execute(new OpenFeedbackRoundDTO(
            entityType: FeedbackEntityType::CHANGE_ORDER,
            entityId: $changeOrder->id,
            openedBy: $author->id,
        ));

        $dto = $request->toDTO();
        $this->addAction->execute($round, $dto);

        return redirect()->back()->with('success', 'Review note saved.');
    }

    public function submitRound(Request $request, string $token, EntityFeedbackRound $round): RedirectResponse
    {
        $recipient = ChangeOrderRecipient::query()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->with(['changeOrder', 'user'])
            ->firstOrFail();

        if ($recipient->hasExpired()) {
            return redirect()->back()->with('error', 'This decision link has expired.');
        }

        /** @var ChangeOrder $changeOrder */
        $changeOrder = $recipient->changeOrder;
        /** @var User $author */
        $author = $recipient->user;

        // A valid token may only ever act on its own change order. Without this,
        // a token issued for one change order could submit another entity's
        // round by guessing its id.
        if ($round->entity_type !== FeedbackEntityType::CHANGE_ORDER
            || $round->entity_id !== $changeOrder->id) {
            return redirect()->back()
                ->with('error', 'This feedback round does not belong to this decision link.');
        }

        $this->submitAction->execute($round, $author);

        return redirect()->back()->with('success', 'Review feedback submitted to manager.');
    }

    public function acceptRound(Request $request, string $token, EntityFeedbackRound $round): RedirectResponse
    {
        $recipient = ChangeOrderRecipient::query()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->with(['changeOrder', 'user'])
            ->firstOrFail();

        if ($recipient->hasExpired()) {
            return redirect()->back()->with('error', 'This decision link has expired.');
        }

        /** @var ChangeOrder $changeOrder */
        $changeOrder = $recipient->changeOrder;
        /** @var User $author */
        $author = $recipient->user;

        if ($round->entity_type !== FeedbackEntityType::CHANGE_ORDER
            || $round->entity_id !== $changeOrder->id) {
            return redirect()->back()
                ->with('error', 'This feedback round does not belong to this decision link.');
        }

        $this->acceptAction->execute($round, $author);

        return redirect()->back()->with('success', 'Feedback accepted and closed.');
    }
}
