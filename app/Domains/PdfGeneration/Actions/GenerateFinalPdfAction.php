<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Actions;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\PdfGeneration\DTOs\GeneratePdfDTO;
use App\Domains\PdfGeneration\Jobs\GeneratePdfJob;
use App\Shared\Exceptions\PackageNotApprovedException;

final class GenerateFinalPdfAction
{
    public function execute(GeneratePdfDTO $dto): void
    {
        $package = CloseoutPackage::query()->findOrFail($dto->closeoutPackageId);

        if (! $package->isApproved()) {
            throw new PackageNotApprovedException('Only approved packages can generate a final PDF.');
        }

        GeneratePdfJob::dispatch($package, $dto->generatedBy);
    }
}
