<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Controllers;

use App\Domains\ChangeOrders\Actions\MarkChangeOrderViewedAction;
use App\Domains\ChangeOrders\Actions\RecordCustomerDecisionAction;
use App\Domains\ChangeOrders\Enums\CustomerDecision;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\ChangeOrders\Models\ChangeOrderRecipient;
use App\Domains\EntityFeedback\Presenters\FeedbackRoundPresenter;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Domains\Users\Queries\UserQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ChangeOrderDecisionController extends Controller
{
    private const LINK_EXPIRED_MESSAGE = 'This decision link has expired.';

    private const LINK_UNUSABLE_MESSAGE = 'This decision link is no longer valid.';

    private const ALREADY_RESPONDED_MESSAGE = 'You have already submitted your decision for this change order. For security purposes, your link has been revoked. For further guidance, please contact your project manager or an administrator.';

    public function __construct(
        private readonly RecordCustomerDecisionAction $decisionAction,
        private readonly MarkChangeOrderViewedAction $markViewedAction,
        private readonly UserQuery $userQuery,
        private readonly FeedbackRoundPresenter $presenter,
    ) {}

    /** Portal view (customer logged in, project access checked via policy). */
    public function show(Request $request, Project $project, ChangeOrder $changeOrder): Response
    {
        $this->authorize('view', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        // First open counts as "read" even when the customer came via the
        // portal instead of the emailed link.
        $this->markViewedAction->execute($changeOrder, (int) $request->user()->id);

        $changeOrder->load(['creator.media', 'items', 'project.market', 'photos']);

        if ($changeOrder->creator !== null) {
            $changeOrder->creator->setAttribute('profile_photo_url', $changeOrder->creator->profilePhotoUrl());
        }

        return Inertia::render('Customer/ChangeOrders/Show', [
            'project' => ['id' => $project->id, 'hash_id' => $project->hash_id, 'name' => $project->name],
            'changeOrder' => $changeOrder,
            'feedbackRound' => $this->presenter->present($changeOrder->latestActiveFeedbackRound(), $request->user()),
            'feedbackRoundHistory' => $this->presenter->presentAll($changeOrder->feedbackRoundHistory(), $request->user()),
        ]);
    }

    public function decide(Request $request, Project $project, ChangeOrder $changeOrder): RedirectResponse
    {
        $this->authorize('decideAsCustomer', $changeOrder);
        abort_unless($changeOrder->project_id === $project->id, 404);

        $validated = $request->validate($this->decisionRules());

        $this->decisionAction->execute($changeOrder, $this->normalizeDecisions($validated), $request->user());

        return redirect()
            ->route('customer.change-orders.show', [$project, $changeOrder])
            ->with('success', 'Your decision has been recorded.');
    }

    /** Email-token landing (no login) — renders the TokenResponse page. */
    public function tokenShow(Request $request, string $token): Response
    {
        $recipient = $this->resolveRecipient($token);

        if ($recipient === null) {
            return $this->unavailableResponse($token, submitted: $request->session()->get('token_decided') === $token);
        }

        $changeOrder = $recipient->changeOrder()->with(['creator.media', 'items', 'project.market', 'photos'])->firstOrFail();

        if ($changeOrder->creator !== null) {
            $changeOrder->creator->setAttribute('profile_photo_url', $changeOrder->creator->profilePhotoUrl());
        }

        // Opening the emailed link is the customer's first read — recorded
        // exactly once and never changed, no matter how often it is reopened.
        $this->markViewedAction->execute($changeOrder, (int) $recipient->user_id);

        return Inertia::render('ChangeOrders/TokenResponse', [
            'token' => $token,
            'changeOrder' => $changeOrder,
            'isExpired' => false,
            'feedbackRound' => $this->presenter->present($changeOrder->latestActiveFeedbackRound(), null),
            'feedbackRoundHistory' => $this->presenter->presentAll($changeOrder->feedbackRoundHistory(), null),
        ]);
    }

    public function tokenDecide(Request $request, string $token): RedirectResponse
    {
        $recipient = ChangeOrderRecipient::query()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->first();

        if ($recipient === null) {
            return $this->rejectToken($token, self::LINK_UNUSABLE_MESSAGE);
        }

        if ($recipient->hasResponded()) {
            return $this->rejectToken($token, self::ALREADY_RESPONDED_MESSAGE);
        }

        if ($recipient->hasExpired()) {
            return $this->rejectToken($token, self::LINK_EXPIRED_MESSAGE);
        }

        $validated = $request->validate($this->decisionRules());

        $changeOrder = $recipient->changeOrder()->firstOrFail();

        $this->decisionAction->execute($changeOrder, $this->normalizeDecisions($validated), $recipient->user);

        // The token is now spent, so the follow-up GET would otherwise render the
        // dead-link page. Flag the token so it renders a confirmation instead —
        // flashed, so a later manual reload still shows the honest dead-link copy.
        return redirect()
            ->route('change-orders.token.show', ['token' => $token])
            ->with('success', 'Your decision has been recorded.')
            ->with('token_decided', $token);
    }

    /**
     * @return array<string, mixed>
     */
    private function decisionRules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.item_id' => ['required', 'integer'],
            'decisions.*.decision' => ['required', Rule::enum(CustomerDecision::class)],
            'decisions.*.note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{item_id: int, decision: CustomerDecision, note?: ?string}>
     */
    private function normalizeDecisions(array $validated): array
    {
        /** @var list<array<string, mixed>> $raw */
        $raw = (array) ($validated['decisions'] ?? []);

        return array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'],
            'decision' => CustomerDecision::from(is_string($row['decision']) ? $row['decision'] : 'pending'),
            'note' => isset($row['note']) && is_string($row['note']) ? $row['note'] : null,
        ], $raw);
    }

    private function resolveRecipient(string $token): ?ChangeOrderRecipient
    {
        /** @var ChangeOrderRecipient|null $recipient */
        $recipient = ChangeOrderRecipient::query()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->first();

        if ($recipient === null || ! $recipient->canDecide()) {
            return null;
        }

        return $recipient;
    }

    private function rejectToken(string $token, string $message): RedirectResponse
    {
        return redirect()
            ->route('change-orders.token.show', ['token' => $token])
            ->with('error', $message);
    }

    /**
     * Renders the dead-link page: why it is dead, plus every admin as the
     * fallback contact. The login URL is computed server-side so it always
     * points at the environment the plate link was sent from.
     *
     * `submitted` distinguishes the customer who just decided from someone
     * arriving at a link that was already used — same token, very different
     * message.
     */
    private function unavailableResponse(
        string $token,
        ?ChangeOrderRecipient $recipient = null,
        bool $submitted = false,
    ): Response {
        /** @var ChangeOrderRecipient|null $recipient */
        $recipient ??= ChangeOrderRecipient::query()
            ->where('token_hash', ChangeOrderRecipient::hashToken($token))
            ->first();

        return Inertia::render('ChangeOrders/TokenResponse', [
            'token' => $token,
            'changeOrder' => null,
            'isExpired' => true,
            'reason' => match (true) {
                $submitted => 'submitted',
                $recipient === null => 'invalid',
                $recipient->hasResponded() => 'responded',
                default => 'expired',
            },
            'contacts' => $this->adminContacts(),
            'loginUrl' => route('login'),
        ]);
    }

    /**
     * Only admins are shown as the fallback contact — the customer never needs
     * to chase a project manager for a dead link.
     *
     * @return list<array{name: string, email: string, role: string}>
     */
    private function adminContacts(): array
    {
        return $this->userQuery->admins()
            ->map(fn (User $admin): array => [
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => 'Administrator',
            ])
            ->all();
    }
}
