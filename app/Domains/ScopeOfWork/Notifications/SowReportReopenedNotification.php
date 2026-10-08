<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Notifications;

use App\Domains\ScopeOfWork\Models\SowReport;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SowReportReopenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SowReport $report,
        private readonly User $manager,
        private readonly ?string $reopenNotes = null,
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
        $body = "Your SOW report for {$site} was reopened by {$this->manager->name}.";

        if ($this->reopenNotes !== null && trim($this->reopenNotes) !== '') {
            $body .= ' Notes: '.$this->reopenNotes;
        }

        return [
            'title' => 'SOW report reopened for changes',
            'report_id' => $this->report->id,
            'site_name' => $site,
            'manager_id' => $this->manager->id,
            'manager_name' => $this->manager->name,
            'reopen_notes' => $this->reopenNotes,
            'category' => 'sow',
            'link' => route('sow.reports.show', $this->report->id),
            'body' => $body,
        ];
    }
}
