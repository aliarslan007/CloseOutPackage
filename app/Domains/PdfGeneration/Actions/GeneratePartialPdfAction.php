<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\DTOs\GeneratePdfDTO;
use App\Domains\PdfGeneration\Jobs\GeneratePartialPdfJob;

final class GeneratePartialPdfAction
{
    public function execute(GeneratePdfDTO $dto): void
    {
        $package = CloseoutPackage::query()->findOrFail($dto->closeoutPackageId);

        GeneratePartialPdfJob::dispatch($package, $dto->emailTo);
    }
}
