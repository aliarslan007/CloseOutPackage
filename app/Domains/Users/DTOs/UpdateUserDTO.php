<?php

declare(strict_types=1);

namespace App\Domains\Users\DTOs;

use Illuminate\Http\UploadedFile;

final readonly class UpdateUserDTO
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public string $email,
        public string $role,
        public bool $isActive,
        public ?string $company = null,
        public ?string $phone = null,
        public ?UploadedFile $profilePhoto = null,
    ) {}
}
