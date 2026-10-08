<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Notifications;

use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

final class SowPdfReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SowReport $report,
        private readonly int $revisionNumber,
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
            'title' => 'SOW report PDF ready',
            'report_id' => $this->report->id,
            'site_name' => $site,
            'revision' => $this->revisionNumber,
            'category' => 'pdf',
            'link' => route('sow.reports.show', $this->report->id),
            'body' => "The PDF for {$site} (Rev {$this->revisionNumber}) is ready for download.",
        ];
    }
}
