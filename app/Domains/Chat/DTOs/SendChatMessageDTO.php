<?php

declare(strict_types=1);

namespace App\Domains\Chat\DTOs;

final readonly class SendChatMessageDTO
{
    public function __construct(
        public int $projectId,
        public int $senderId,
        public string $body,
    ) {}
}
