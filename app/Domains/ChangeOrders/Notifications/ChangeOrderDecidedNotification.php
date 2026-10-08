<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Notifications;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChangeOrderDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ChangeOrder $changeOrder,
        private readonly User $decidedBy,
    ) {}

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
        $projectName = $this->changeOrder->project?->name ?? 'Site Project';
        $statusLabel = $this->changeOrder->status->label();

        return [
            'title' => "Change order {$statusLabel}",
            'body' => "{$this->decidedBy->name} recorded decisions for \"{$this->changeOrder->title}\" ({$projectName}). Status: {$statusLabel}.",
            'project_id' => $this->changeOrder->project_id,
            'change_order_id' => $this->changeOrder->id,
            'status' => $this->changeOrder->status->value,
            'category' => 'change_order',
        ];
    }
}
