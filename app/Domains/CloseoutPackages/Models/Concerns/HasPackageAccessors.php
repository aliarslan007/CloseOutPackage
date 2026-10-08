<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Models\Concerns;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Projects\Models\Project;
use App\Domains\Templates\Models\Template;

/**
 * Virtual attribute accessors appended to every serialized CloseoutPackage.
 *
 * @mixin CloseoutPackage
 *
 * @property-read Template|null $template
 * @property-read Project|null $project
 * @property-read int $revision_major
 * @property-read int $revision_minor
 */
trait HasPackageAccessors
{
    /**
     * Human-readable display title derived from the template.
     * Falls back to a generic label when no template is loaded.
     */
    public function getTitleAttribute(): string
    {
        /** @var Template|null $template */
        $template = $this->relationLoaded('template') ? $this->template : null;

        return $template !== null
            ? "Close-Out Package — {$template->name}"
            : "Package #{$this->getRouteKey()}";
    }

    /**
     * Formatted revision string in "R<major>.<minor>" notation.
     * Matches the revision_label format used in the PDF engine.
     */
    public function getRevisionNumberAttribute(): string
    {
        /** @var int $major */
        $major = $this->revision_major;
        /** @var int $minor */
        $minor = $this->revision_minor;

        return "R{$major}.{$minor}";
    }

    /**
     * Whether the package is locked against opening, downloading, and emailing.
     */
    public function getLockedAttribute(): bool
    {
        return $this->isLocked();
    }
}
