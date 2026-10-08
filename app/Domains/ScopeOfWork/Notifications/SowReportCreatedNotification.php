<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Notifications;

use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SowReportCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SowReport $report,
        private readonly User $creator,
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
            'title' => 'New SOW report created',
            'report_id' => $this->report->id,
            'site_name' => $site,
            'creator_id' => $this->creator->id,
            'creator_name' => $this->creator->name,
            'category' => 'sow',
            'link' => route('sow.reports.show', $this->report->id),
            'body' => "Field technician {$this->creator->name} created a new SOW report for {$site}.",
        ];
    }
}
