<?php

declare(strict_types=1);

namespace App\Domains\Chat\Notifications;

use App\Domains\Chat\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChatMessageReceivedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ChatMessage $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message_id' => $this->message->id,
            'project_id' => $this->message->project_id,
            'sender_id' => $this->message->sender_id,
            'body' => $this->message->body,
        ];
    }
}
