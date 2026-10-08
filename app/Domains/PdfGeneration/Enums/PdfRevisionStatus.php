<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Enums;

enum PdfRevisionStatus: string
{
    case Pending = 'pending';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Generating',
            self::Ready => 'Ready',
            self::Failed => 'Failed',
        };
    }
}
