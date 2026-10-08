<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\DTOs;

use App\Domains\PdfGeneration\Enums\PdfType;

final readonly class GeneratePdfDTO
{
    public function __construct(
        public int $closeoutPackageId,
        public PdfType $type,
        public int $generatedBy,
        public ?string $emailTo = null,
    ) {}
}
