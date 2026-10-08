<?php

declare(strict_types=1);

namespace App\Domains\Approval\Requests;

use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\Photos\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RequestPhotoReplacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Photo $photo */
        $photo = $this->route('photo');

        return Gate::allows('review', [PhotoApproval::class, $photo]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) ? $reason : null;
    }
}
