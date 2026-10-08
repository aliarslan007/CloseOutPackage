<?php

declare(strict_types=1);

namespace App\Domains\ChangeOrders\Notifications;

use App\Domains\ChangeOrders\Models\ChangeOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ChangeOrderSentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly ChangeOrder $changeOrder,
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
        $creatorName = $this->changeOrder->creator?->name ?? 'Project Manager';
        $totalFormatted = '$'.number_format((float) $this->changeOrder->total_amount, 2);

        return [
            'title' => 'Change order submitted for review',
            'body' => "{$creatorName} submitted change order \"{$this->changeOrder->title}\" ({$totalFormatted}) for {$projectName}.",
            'project_id' => $this->changeOrder->project_id,
            'change_order_id' => $this->changeOrder->id,
            'category' => 'change_order',
        ];
    }
}
