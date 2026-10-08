<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Projects\DTOs\AddIssueCommentDTO;
use App\Domains\Projects\Events\IssueCommentAdded;
use App\Domains\Projects\Models\IssueComment;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Services\IssueAttachmentService;
use App\Domains\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

final class AddIssueCommentAction
{
    public function __construct(private readonly IssueAttachmentService $attachmentService) {}

    /**
     * @param  array<int, UploadedFile>  $attachments
     */
    public function execute(IssueHistory $issue, AddIssueCommentDTO $dto, User $author, array $attachments = []): IssueComment
    {
        return DB::transaction(function () use ($issue, $dto, $author, $attachments): IssueComment {
            $comment = IssueComment::create([
                'issue_history_id' => $issue->id,
                'user_id' => $author->id,
                'body' => $dto->body,
            ]);

            if ($attachments !== []) {
                $this->attachmentService->attach($comment, $attachments, IssueComment::MEDIA_COLLECTION);
            }

            IssueCommentAdded::dispatch($comment);

            return $comment;
        });
    }
}
