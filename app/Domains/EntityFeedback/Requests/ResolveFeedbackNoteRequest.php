<?php

declare(strict_types=1);

namespace App\Domains\EntityFeedback\Requests;

use App\Domains\EntityFeedback\DTOs\ResolveFeedbackNoteDTO;
use App\Domains\EntityFeedback\Models\EntityFeedbackNote;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

class ResolveFeedbackNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var EntityFeedbackNote $note */
        $note = $this->route('note');

        return Gate::allows('resolveNote', [EntityFeedbackNote::class, $note]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'manager_response' => ['nullable', 'string', 'max:'.EntityFeedbackNote::MAX_BODY_LENGTH],
            'replacement_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,heic', 'max:10240'],
            'replace_in_change_order' => ['nullable', 'boolean'],
            'add_to_change_order_photos' => ['nullable', 'boolean'],
        ];
    }

    public function toDTO(): ResolveFeedbackNoteDTO
    {
        /** @var UploadedFile|null $file */
        $file = $this->file('replacement_image');

        return new ResolveFeedbackNoteDTO(
            resolvedBy: (int) $this->user()?->id,
            managerResponse: $this->validated('manager_response'),
            replacementImageFile: $file,
            replaceInChangeOrder: filter_var($this->validated('replace_in_change_order'), FILTER_VALIDATE_BOOLEAN),
            addToChangeOrderPhotos: filter_var($this->validated('add_to_change_order_photos'), FILTER_VALIDATE_BOOLEAN),
        );
    }
}
