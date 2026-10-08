<?php

declare(strict_types=1);

namespace App\Domains\Email\Contracts;

use App\Domains\Email\DTOs\SendEmailDTO;

interface SendsCopEmail
{
    public function toSendCopEmail(object $notifiable): ?SendEmailDTO;
}
