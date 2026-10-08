<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Notifications;

use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SowReportPublishedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SowReport $report,
        private readonly User $manager,
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
        $site = $this->report->site_name ?: $this->report->title;

        return [
            'title' => 'SOW report approved and finalized',
            'report_id' => $this->report->id,
            'site_name' => $site,
            'manager_id' => $this->manager->id,
            'manager_name' => $this->manager->name,
            'category' => 'sow',
            'link' => route('sow.reports.show', $this->report->id),
            'body' => "Your SOW report for {$site} has been approved and finalized by {$this->manager->name}.",
        ];
    }
}
