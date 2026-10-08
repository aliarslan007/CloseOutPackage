<?php

declare(strict_types=1);

namespace App\Domains\Projects\Controllers;

use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Actions\ForwardIssuesToCustomerAction;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Requests\ForwardIssuesToCustomerRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class ForwardIssuesToCustomerController extends Controller
{
    public function __construct(
        private readonly ForwardIssuesToCustomerAction $action,
    ) {}

    public function __invoke(ForwardIssuesToCustomerRequest $request, Project $project): RedirectResponse
    {
        $this->authorize('view', [IssueHistory::class, $project]);

        $recipients = $this->buildRecipients($project, $request->recipientEmails());

        $this->action->execute(
            project: $project,
            issueIds: $request->issueIds(),
            recipients: $recipients,
            managerNote: $request->note(),
            forwardedBy: $this->user(),
        );

        $count = count($request->recipientEmails());
        $word = $count === 1 ? 'customer' : 'customers';

        return redirect()
            ->route('projects.issues.index', $project)
            ->with('success', "Issues forwarded to {$count} {$word} successfully.");
    }

    /**
     * Build recipient array, using the display name from the CustomerProjectAccess
     * record when available, falling back to the email address.
     *
     * @param  array<string>  $emails
     * @return array<array{email: string, name: string}>
     */
    private function buildRecipients(Project $project, array $emails): array
    {
        $customerMap = CustomerProjectAccess::query()
            ->where('project_id', $project->id)
            ->with('user:id,name,email')
            ->get()
            ->keyBy(fn (CustomerProjectAccess $a): string => $a->user?->email ?? '')
            ->filter(fn (CustomerProjectAccess $a): bool => $a->user instanceof User);

        return array_map(function (string $email) use ($customerMap): array {
            $access = $customerMap->get($email);
            $name = ($access instanceof CustomerProjectAccess && $access->user instanceof User)
                ? $access->user->name
                : $email;

            return ['email' => $email, 'name' => $name];
        }, $emails);
    }
}
