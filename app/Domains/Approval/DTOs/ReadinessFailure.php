<?php

declare(strict_types=1);

namespace App\Domains\Approval\DTOs;

final readonly class ReadinessFailure
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $rule,
        public string $message,
        public array $context = [],
    ) {}
}
