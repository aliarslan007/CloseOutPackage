<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Queries\TemplateQuery;
use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

class FieldTemplateController extends Controller
{
    public function __construct(private readonly TemplateQuery $templateQuery) {}

    public function __invoke(): Response
    {
        return Inertia::render('Templates/Index', [
            'templates' => $this->templateQuery->forField($this->user()),
        ]);
    }
}
