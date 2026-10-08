<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Base class for stateless upload-token failures (expired / invalid).
 * Rendered as HTTP 401 — the caller is not who they claim to be.
 */
abstract class UploadTokenException extends CopException {}
