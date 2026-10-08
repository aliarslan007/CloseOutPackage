<?php

declare(strict_types=1);

namespace App\Domains\Photos\DTOs;

final readonly class UpdatePhotoNotesDTO
{
    public function __construct(
        public ?string $notes = null,
    ) {}
}
