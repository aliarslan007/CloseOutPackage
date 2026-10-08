<?php

declare(strict_types=1);

namespace App\Domains\Email\Requests;

use App\Domains\Email\Models\EmailLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class BulkDeleteCustomerEmailRequest extends FormRequest
{
    /**
     * Both outbox roles may prune their own outbox rows; row scope is
     * enforced by BulkDeleteCustomerEmailsAction (managers only touch
     * their managed projects).
     */
    public function authorize(): bool
    {
        return Gate::allows('viewAny', EmailLog::class);
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
