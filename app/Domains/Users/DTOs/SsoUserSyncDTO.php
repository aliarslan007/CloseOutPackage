<?php

declare(strict_types=1);

namespace App\Domains\Users\DTOs;

final readonly class SsoUserSyncDTO
{
    public function __construct(
        public string $uuid,
        public string $firstName,
        public string $lastName,
        public string $email,
        public ?string $password = null,
    ) {}
}
