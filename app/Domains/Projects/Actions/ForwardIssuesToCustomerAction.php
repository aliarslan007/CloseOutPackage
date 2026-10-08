<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Email\Actions\SendCopEmailAction;
use App\Domains\Email\Services\EmailComposerService;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Collection;

final class ForwardIssuesToCustomerAction
{
    public function __construct(
        private readonly EmailComposerService $composer,
        private readonly SendCopEmailAction $sender,
    ) {}

    /**
     * Forward a collection of project issues to one or more customer recipients.
     *
     * @param  array<int>  $issueIds
     * @param  array<array{email: string, name: string}>  $recipients
     */
    public function execute(
        Project $project,
        array $issueIds,
        array $recipients,
        ?string $managerNote,
        User $forwardedBy,
    ): void {
        /** @var Collection<int, IssueHistory> $issues */
        $issues = IssueHistory::query()
            ->whereIn('id', $issueIds)
            ->where('project_id', $project->id)
            ->with(['reporter', 'comments'])
            ->get();

        $issueRows = $issues->map(fn (IssueHistory $issue): array => [
            'id' => $issue->id,
            'type' => $issue->type instanceof \BackedEnum ? $issue->type->value : (string) $issue->type,
            'description' => $issue->description ?? '',
            'verizon_guidance' => $issue->verizon_guidance,
            'resolved_at' => $issue->resolved_at?->toIso8601String(),
            'created_at' => $issue->created_at?->toIso8601String() ?? '',
            'reporter_name' => $issue->reporter?->name,
            'comment_count' => $issue->comments->count(),
        ])->all();

        foreach ($recipients as $recipient) {
            $dto = $this->composer->composeIssueForward(
                project: $project,
                recipientEmail: $recipient['email'],
                recipientName: $recipient['name'],
                issueRows: $issueRows,
                managerNote: $managerNote,
                forwardedBy: $forwardedBy,
            );

            $this->sender->execute($dto);
        }
    }
}
