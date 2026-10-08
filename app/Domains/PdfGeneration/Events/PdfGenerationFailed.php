<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Events;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PdfGenerationFailed implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly CloseoutPackage $package,
        public readonly string $error,
    ) {}
}
