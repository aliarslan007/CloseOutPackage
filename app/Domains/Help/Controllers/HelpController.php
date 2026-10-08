<?php

declare(strict_types=1);

namespace App\Domains\Help\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class HelpController extends Controller
{
    /**
     * Render the role-aware Help Center.
     *
     * The article content lives in the frontend registry
     * (resources/js/help/content.ts) and is filtered client-side by the
     * signed-in user's roles. No backend data is needed here.
     */
    public function __invoke(): Response
    {
        return Inertia::render('Help/Index');
    }
}
