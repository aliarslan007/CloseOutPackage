<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Notifications;

use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SowReportSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SowReport $report,
        private readonly User $submitter,
        private readonly bool $isResubmission = false,
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
        $action = $this->isResubmission ? 'resubmitted' : 'submitted';

        return [
            'title' => $this->isResubmission
                ? 'SOW report resubmitted for review'
                : 'SOW report submitted for review',
            'report_id' => $this->report->id,
            'site_name' => $site,
            'submitter_id' => $this->submitter->id,
            'submitter_name' => $this->submitter->name,
            'is_resubmission' => $this->isResubmission,
            'category' => 'sow',
            'link' => route('sow.reports.show', $this->report->id),
            'body' => "SOW report for {$site} was {$action} for review by {$this->submitter->name}.",
        ];
    }
}
