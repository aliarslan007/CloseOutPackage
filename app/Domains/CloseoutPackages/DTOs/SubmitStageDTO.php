<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\DTOs;

final readonly class SubmitStageDTO
{
    public function __construct(public int $packageStageId) {}
}
