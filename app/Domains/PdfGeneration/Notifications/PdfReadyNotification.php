<?php

declare(strict_types=1);

namespace App\Domains\PdfGeneration\Notifications;

use App\Domains\PdfGeneration\Models\PackageRevision;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PdfReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly PackageRevision $revision) {}

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
        $projectName = $this->revision->package->project->name ?? 'your project';

        return [
            'revision_id' => $this->revision->id,
            'package_id' => $this->revision->closeout_package_id,
            'revision_label' => $this->revision->revision_label,
            'pdf_type' => $this->revision->type->value,
            'generated_at' => $this->revision->generated_at?->toISOString(),
            'body' => "The close-out package {$this->revision->revision_label} for {$projectName} is ready to download.",
        ];
    }
}
