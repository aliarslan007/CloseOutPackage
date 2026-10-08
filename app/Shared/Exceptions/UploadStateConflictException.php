<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when an upload is already in a terminal state (finalized, cancelled
 * or failed) that prevents the requested chunked-upload operation. 409.
 */
class UploadStateConflictException extends ConflictException {}
