<?php

declare(strict_types=1);

namespace App\Domains\Email\Requests;

use App\Domains\Email\Models\EmailLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class DestroyEmailLogRequest extends FormRequest
{
    /**
     * Role middleware keeps other roles off the page; this is the authority
     * behind the delete button, so both stay in step.
     */
    public function authorize(): bool
    {
        return Gate::allows('delete', EmailLog::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
        ];
    }
}
