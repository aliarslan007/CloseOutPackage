<?php

declare(strict_types=1);

namespace App\Domains\Approval\Requests;

use App\Domains\Approval\Models\PhotoApproval;
use App\Domains\Photos\Models\Photo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class ApprovePhotoRequest extends FormRequest
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
        return [];
    }
}
