<?php

declare(strict_types=1);

namespace App\Domains\Projects\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ForwardIssuesToCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'manager']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'issue_ids' => ['required', 'array', 'min:1'],
            'issue_ids.*' => ['required', 'integer', 'exists:issue_histories,id'],
            'recipient_emails' => ['required', 'array', 'min:1'],
            'recipient_emails.*' => ['required', 'string', 'email:rfc'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<int> Validated issue IDs.
     */
    public function issueIds(): array
    {
        /** @var array<int> */
        return $this->validated('issue_ids');
    }

    /**
     * @return array<string> Validated recipient e-mail addresses.
     */
    public function recipientEmails(): array
    {
        /** @var array<string> */
        return $this->validated('recipient_emails');
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) && $note !== '' ? $note : null;
    }
}
