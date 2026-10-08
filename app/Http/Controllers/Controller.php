<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Users\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;

abstract class Controller
{
    use AuthorizesRequests, ValidatesRequests;

    /**
     * Return the authenticated user.
     *
     * @throws \RuntimeException when the request is not authenticated.
     */
    protected function user(): User
    {
        $user = request()->user();

        assert($user instanceof User);

        return $user;
    }
}
