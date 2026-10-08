<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/**
 * Thrown when a reassembled upload is not a valid image of its declared type
 * (magic-byte / decode failure). Rendered at job level: the asset is marked
 * failed with this message.
 */
class InvalidMediaFileException extends MediaException {}
