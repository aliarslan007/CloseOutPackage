<?php

declare(strict_types=1);

namespace App\Domains\Users\DTOs;

final readonly class ChangeUserPasswordDTO
{
    public function __construct(
        public string $password,
    ) {}
}
