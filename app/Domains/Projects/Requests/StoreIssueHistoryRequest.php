<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use App\Domains\Projects\DTOs\RecordIssueDTO;
use App\Domains\Projects\Enums\IssueType;
use App\Domains\Projects\Models\IssueHistory;
use App\Domains\Projects\Services\IssueAttachmentService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreIssueHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', [IssueHistory::class, $this->route('project')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge([
            'type' => ['required', Rule::enum(IssueType::class)],
            'description' => ['required', 'string', 'max:3000'],
            'verizon_guidance' => ['nullable', 'string', 'max:3000'],
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

    public function toDTO(): RecordIssueDTO
    {
        $type = $this->validated('type');
        $description = $this->validated('description');

        return new RecordIssueDTO(
            type: is_string($type) ? IssueType::from($type) : IssueType::OTHER,
            description: is_string($description) ? $description : '',
            verizonGuidance: $this->nullableString('verizon_guidance'),
        );
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : null;
    }
}
