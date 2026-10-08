<?php

declare(strict_types=1);

namespace App\Domains\Email\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Outbox row actions (send/cancel/resend/retry) carry no payload. The
 * per-row policy ability is enforced in the controller via $this->authorize,
 * so a plain FormRequest is sufficient here.
 */
class SendCustomerEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
