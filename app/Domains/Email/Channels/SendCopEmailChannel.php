<?php

declare(strict_types=1);

namespace App\Domains\Email\Channels;

use App\Domains\Email\Actions\SendCopEmailAction;
use App\Domains\Email\Contracts\SendsCopEmail;
use Illuminate\Notifications\Notification;

class SendCopEmailChannel
{
    public function __construct(private readonly SendCopEmailAction $sendCopEmailAction) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof SendsCopEmail) {
            return;
        }

        $dto = $notification->toSendCopEmail($notifiable);

        if ($dto === null) {
            return;
        }

        $this->sendCopEmailAction->execute($dto);
    }
}
