<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdatePackageNotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('updateSiteNotes', $this->route('package'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:20000'],
        ];
    }

    public function notes(): ?string
    {
        $notes = $this->input('notes');

        return is_string($notes) && trim($notes) !== '' ? $notes : null;
    }
}
