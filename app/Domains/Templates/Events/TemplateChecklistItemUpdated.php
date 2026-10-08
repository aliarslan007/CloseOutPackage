<?php

declare(strict_types=1);

namespace App\Domains\Templates\Events;

use App\Domains\Templates\Enums\TemplateDriftChangeType;
use App\Domains\Templates\Models\ChecklistItem;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemplateChecklistItemUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly ChecklistItem $item,
        public readonly TemplateDriftChangeType $changeType,
    ) {}
}
