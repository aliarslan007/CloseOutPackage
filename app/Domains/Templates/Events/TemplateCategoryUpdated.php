<?php

declare(strict_types=1);

namespace App\Domains\Templates\Events;

use App\Domains\Templates\Enums\TemplateDriftChangeType;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TemplateCategoryUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly TemplatePictureCategory $category,
        public readonly TemplateDriftChangeType $changeType,
    ) {}
}
