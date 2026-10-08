<?php

declare(strict_types=1);

namespace App\Domains\Email\Controllers;

use App\Domains\Email\Actions\BulkDeleteCustomerEmailsAction;
use App\Domains\Email\Actions\DeliverCustomerEmailAction;
use App\Domains\Email\Enums\EmailType;
use App\Domains\Email\Models\EmailLog;
use App\Domains\Email\Queries\EmailLogQuery;
use App\Domains\Email\Requests\BulkDeleteCustomerEmailRequest;
use App\Domains\Email\Requests\CustomerEmailOutboxRequest;
use App\Domains\Email\Requests\ScheduleCustomerEmailRequest;
use App\Domains\Projects\Models\Project;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Customer Emails outbox. Listing is scoped (managers see their managed
 * projects, admins everything — the same set the policy authorizes row
 * actions on); the write actions are thin delegations to
 * DeliverCustomerEmailAction, which owns the state machine, the kill switch
 * and the atomic transitions.
 */
class CustomerEmailOutboxController extends Controller
{
    public function __construct(
        private readonly EmailLogQuery $emailLogQuery,
        private readonly DeliverCustomerEmailAction $delivery,
        private readonly BulkDeleteCustomerEmailsAction $bulkDelete,
    ) {}

    public function index(CustomerEmailOutboxRequest $request): Response
    {
        /** @var array{search?: ?string, status?: ?string, type?: ?string, project_id?: ?string} $filters */
        $filters = $request->validated();

        $user = $this->user();

        $paginator = $user->hasRole('admin')
            ? $this->emailLogQuery->outboxForAdmin($filters)
            : $this->emailLogQuery->outboxForManager($user, $filters);

        return Inertia::render('CustomerEmails/Index', [
            'logs' => $paginator->items(),
            'pagination' => EmailLogQuery::paginatorMeta($paginator),
            'filters' => $filters,
            'projects' => $this->projectOptions(),
            // The dialog anchors its "quick schedule" presets to server time,
            // so a stale tab cannot compute a preset in the past.
            'now' => now()->toIso8601String(),
            // Whether the send/schedule actions can fire at all right now.
            'customer_emails_enabled' => (bool) config('cop.customer_emails_enabled'),
        ]);
    }

    public function sendNow(Request $request, EmailLog $email_log): RedirectResponse
    {
        $this->authorize('send', $email_log);

        $this->delivery->sendNow($email_log, $this->user());

        return back()->with('success', 'Email queued for delivery.');
    }

    public function schedule(ScheduleCustomerEmailRequest $request, EmailLog $email_log): RedirectResponse
    {
        $this->authorize('schedule', $email_log);

        $scheduledAt = CarbonImmutable::parse($request->validated('scheduled_at'));

        $this->delivery->schedule($email_log, $scheduledAt, $this->user());

        return back()->with('success', 'Email scheduled for '.$scheduledAt->format('M j, Y g:ia').'.');
    }

    public function cancel(Request $request, EmailLog $email_log): RedirectResponse
    {
        $this->authorize('cancel', $email_log);

        $this->delivery->cancel($email_log, $this->user());

        return back()->with('success', 'Scheduled email cancelled.');
    }

    public function resend(Request $request, EmailLog $email_log): RedirectResponse
    {
        $this->authorize('resend', $email_log);

        $this->delivery->resend($email_log, $this->user());

        return back()->with('success', 'A fresh copy of the email was queued.');
    }

    public function retry(Request $request, EmailLog $email_log): RedirectResponse
    {
        $this->authorize('retry', $email_log);

        $this->delivery->retry($email_log, $this->user());

        return back()->with('success', 'Retry queued.');
    }

    /**
     * Bulk-delete outbox rows (failed, stuck in sending, or any other
     * entry the manager selects). Admins prune any outbox row; managers
     * are scoped to their managed projects inside the action.
     */
    public function destroyBulk(BulkDeleteCustomerEmailRequest $request): RedirectResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');

        $deleted = $this->bulkDelete->execute($this->user(), $ids);

        return back()->with('success', "Deleted {$deleted} customer email(s).");
    }

    /**
     * Project options for the outbox filter dropdown. Managers get their
     * managed projects; admins get the projects that actually have outbox
     * rows (capped — the filter only needs reachable options, not a census).
     *
     * @return list<array{id: int, name: string}>
     */
    private function projectOptions(): array
    {
        $user = $this->user();

        if ($user->hasRole('admin')) {
            return Project::query()
                ->select('projects.id', 'projects.name')
                ->whereIn('projects.id', fn ($q) => $q
                    ->select('project_id')
                    ->from('email_logs')
                    ->whereIn('type', array_map(fn (EmailType $t): string => $t->value, EmailType::manualTypes()))
                    ->whereNotNull('project_id'))
                ->orderBy('projects.name')
                ->limit(100)
                ->get()
                ->map(fn (Project $p): array => ['id' => $p->id, 'name' => $p->name])
                ->all();
        }

        return $user->managedProjects()
            ->select('projects.id', 'projects.name')
            ->orderBy('projects.name')
            ->get()
            ->map(fn (Project $p): array => ['id' => $p->id, 'name' => $p->name])
            ->all();
    }
}
