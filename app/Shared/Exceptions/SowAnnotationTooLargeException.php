<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** §4.3 — annotation layer JSON rejected above 512 KB. */
class SowAnnotationTooLargeException extends BusinessRuleException {}
