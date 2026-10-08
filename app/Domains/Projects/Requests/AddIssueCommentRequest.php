<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\DTOs\AddIssueCommentDTO;
use App\Domains\Projects\Services\IssueAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class AddIssueCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('addComment', [$this->route('issue'), $this->route('project')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'body' => ['required', 'string', 'max:2000'],
        ], IssueAttachmentService::validationRules());
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return IssueAttachmentService::validationMessages();
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

    public function toDTO(): AddIssueCommentDTO
    {
        $body = $this->validated('body');

        return new AddIssueCommentDTO(body: is_string($body) ? $body : '');
    }
}
