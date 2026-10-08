<?php

declare(strict_types=1);

namespace App\Domains\MediaLibrary\Requests;

use App\Domains\MediaLibrary\Services\MediaPurgeService;
use App\Shared\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;

class StartMediaPurgeRequest extends FormRequest
{
    /**
     * The route already carries role:admin; this is the second lock on the same
     * door, because "delete every file in the system" must never depend on a
     * single middleware entry being read correctly.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->hasRole(Role::ADMIN->value);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string', 'in:'.MediaPurgeService::CONFIRMATION_PHRASE],
            // Echoes the total the admin was shown. The controller aborts when the
            // live total has moved, so nobody confirms numbers they never saw.
            'expected_total' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirmation.in' => 'Type '.MediaPurgeService::CONFIRMATION_PHRASE.' exactly to confirm.',
        ];
    }
}
