<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Services\IssueAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class StoreIssueAttachmentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('manageMedia', [$this->route('issue'), $this->route('project')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Cap the incoming batch by what still fits under MAX_FILES total,
        // so existing + new can never exceed the per-issue limit.
        return array_merge(IssueAttachmentService::validationRules(), [
            'attachments' => ['required', 'array', 'min:1', 'max:'.$this->remainingSlots()],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $remaining = $this->remainingSlots();

        return array_merge(IssueAttachmentService::validationMessages(), [
            'attachments.required' => 'Select at least one photo or video to add.',
            'attachments.min' => 'Select at least one photo or video to add.',
            'attachments.max' => 'A maximum of '.IssueAttachmentService::MAX_FILES.
                ' attachments is allowed per issue — only '.$remaining.' more can be added.',
        ]);
    }

    /**
     * @return array<int, UploadedFile>
     */
    public function attachments(): array
    {
        /** @var array<int, UploadedFile>|null $files */
        $files = $this->file('attachments');

        if ($files === null) {
            return [];
        }

        return array_values(array_filter($files, fn (mixed $f): bool => $f instanceof UploadedFile));
    }

    private function remainingSlots(): int
    {
        $issue = $this->route('issue');
        $existing = $issue instanceof IssueHistory
            ? $issue->getMedia(IssueHistory::MEDIA_COLLECTION)->count()
            : 0;

        return max(0, IssueAttachmentService::MAX_FILES - $existing);
    }
}
