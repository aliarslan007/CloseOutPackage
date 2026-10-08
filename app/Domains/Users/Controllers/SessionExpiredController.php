<?php

declare(strict_types=1);

namespace App\Domains\Users\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SessionExpiredController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Auth/SessionExpired');
    }
}
