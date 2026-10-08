<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Notifications;

use App\Domains\CloseoutPackages\DTOs\DriftConflict;
use App\Domains\CloseoutPackages\DTOs\DriftReport;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use Illuminate\Notifications\Notification;

class TemplateDriftNotification extends Notification
{
    public function __construct(
        private readonly CloseoutPackage $package,
        private readonly DriftReport $report,
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
        return [
            'title' => 'Template updated — action required',
            'package_id' => $this->package->id,
            'project_id' => $this->package->project_id,
            'body' => $this->buildSummary(),
        ];
    }

    private function buildSummary(): string
    {
        return implode(' ', array_map(
            fn (DriftConflict $conflict): string => "\"{$conflict->categoryName}\" now allows {$conflict->newValue} photo(s), but {$conflict->currentPhotoCount} are uploaded.",
            $this->report->conflicts,
        ));
    }
}
