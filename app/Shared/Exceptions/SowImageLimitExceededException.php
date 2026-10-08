<?php

declare(strict_types=1);

namespace App\Shared\Exceptions;

/** §9.20 — max annotated images per SOW report, enforced at import/add time. */
class SowImageLimitExceededException extends BusinessRuleException {}
