<?php

declare(strict_types=1);

namespace App\Domains\Approval\DTOs;

final readonly class ReadinessReport
{
    /**
     * @param  list<ReadinessFailure>  $failures
     */
    public function __construct(
        public bool $isReady,
        public array $failures,
    ) {}
}
