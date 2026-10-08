<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers;

use App\Domains\Users\Actions\ChangeUserPasswordAction;
use App\Domains\Users\Requests\UpdatePasswordRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(UpdatePasswordRequest $request, ChangeUserPasswordAction $action): RedirectResponse
    {
        $action->execute($this->user(), $request->toDTO());

        return back()->with('status', 'password-updated')->with('success', 'Password updated successfully.');
    }
}
