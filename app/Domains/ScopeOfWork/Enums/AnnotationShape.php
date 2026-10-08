<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Enums;

/** FREEHAND deliberately excluded from v1 (Contradiction #1). */
enum AnnotationShape: string
{
    case ARROW = 'arrow';
    case BOX = 'box';
    case DASHED_LINE = 'dashed_line';
    case DOT = 'dot';
    case TEXT_LABEL = 'text_label';
}
