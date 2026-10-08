<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\DTOs\RecordIssueDTO;
use App\Domains\Projects\Events\IssueRecorded;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\IssueAttachmentService;
use App\Domains\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class RecordIssueHistoryAction
{
    public function __construct(private readonly IssueAttachmentService $attachmentService) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function execute(Project $project, RecordIssueDTO $dto, User $reporter, array $attachments = []): IssueHistory
    {
        return DB::transaction(function () use ($project, $dto, $reporter, $attachments): IssueHistory {
            $issue = IssueHistory::create([
                'project_id' => $project->id,
                'reported_by' => $reporter->id,
                'type' => $dto->type,
                'description' => $dto->description,
                'verizon_guidance' => $dto->verizonGuidance,
            ]);

            if ($attachments !== []) {
                $this->attachmentService->attach($issue, $attachments, IssueHistory::MEDIA_COLLECTION);
            }

            IssueRecorded::dispatch($issue);

            return $issue;
        });
    }
}
