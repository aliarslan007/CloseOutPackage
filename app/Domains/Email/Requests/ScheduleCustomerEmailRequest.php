<?php

declare(strict_types=1);

namespace App\Domains\Email\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the schedule payload for one outbox row. The per-row policy
 * ability is enforced in the controller ($this->authorize), matching the
 * other outbox actions.
 */
class ScheduleCustomerEmailRequest extends FormRequest
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
        return [
            // Client converts the local datetime-local value to UTC before
            // posting. DeliverCustomerEmailAction additionally rejects past
            // times atomically and enforces the scheduling horizon.
            'scheduled_at' => ['required', 'date', 'after:now'],
        ];
    }
}
