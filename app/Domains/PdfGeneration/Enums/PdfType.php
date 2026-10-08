<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Enums;

enum PdfType: string
{
    case FINAL = 'final';
    case PARTIAL = 'partial';
}
