<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

use App\Domains\CloseoutPackages\DTOs\DriftConflict;

/**
 * Raised when a package cannot be submitted because its template changed in a
 * way the current answers cannot satisfy. Mapped to HTTP 409 by ExceptionRenderer.
 */
final class TemplateDriftConflictException extends ConflictException
{
    /** @var list<DriftConflict> */
    public readonly array $conflicts;

    /** @param list<DriftConflict> $conflicts */
    public function __construct(array $conflicts)
    {
        $count = count($conflicts);
        parent::__construct("Template changes require your attention ({$count} conflict(s)) before submitting.");
        $this->conflicts = $conflicts;
    }
}
