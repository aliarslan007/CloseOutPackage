<?php

declare(strict_types=1);

namespace App\Domains\Photos\Requests;

use App\Domains\Photos\DTOs\UpdatePhotoNotesDTO;
use App\Domains\Photos\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePhotoNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Photo|string|int|null $photo */
        $photo = $this->route('photo');

        if (! ($photo instanceof Photo)) {
            $photo = Photo::query()->find($photo);
        }

        if ($photo === null) {
            return false;
        }

        return Gate::allows('updateNotes', $photo);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.max' => 'Photo note cannot exceed 500 characters.',
        ];
    }

    public function toDTO(): UpdatePhotoNotesDTO
    {
        $notes = $this->input('notes');

        return new UpdatePhotoNotesDTO(
            notes: is_string($notes) && trim($notes) !== '' ? trim($notes) : null,
        );
    }
}
