<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when a chunk cannot be persisted because the storage backend is
 * unavailable (e.g. disk full). Rendered as HTTP 507.
 */
class MediaStorageUnavailableException extends CopException {}
