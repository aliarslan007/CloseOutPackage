<?php

declare(strict_types=1);

namespace App\Domains\Templates\Events;

use App\Domains\Templates\Models\Template;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemplateCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Template $template) {}
}
