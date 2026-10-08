<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** §9.3 — optimistic concurrency: client's annotations_updated_at is stale → 409. */
class SowStaleAnnotationsException extends ConflictException {}
