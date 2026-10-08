<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Events;

use App\Domains\PdfGeneration\Models\PackageRevision;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PdfGenerationCompleted implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly PackageRevision $revision) {}
}
