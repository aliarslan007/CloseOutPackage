<?php

declare(strict_types=1);

namespace App\Domains\Photos\Jobs;

use App\Domains\Photos\Models\Photo;
use App\Domains\Photos\Notifications\PhotoReplacedNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPhotoReplacedNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(
        public readonly Photo $oldPhoto,
        public readonly Photo $newPhoto,
    ) {}

    public function handle(): void
    {
        $this->oldPhoto->load(['package.project.manager', 'categorySelection.templatePictureCategory']);

        $package = $this->oldPhoto->package;

        if ($package === null) {
            return;
        }

        $manager = $package->project?->manager;

        if ($manager === null) {
            return;
        }

        $manager->notify(new PhotoReplacedNotification(
            $package,
            $this->oldPhoto,
            $this->newPhoto,
            $this->newPhoto->uploader->name ?? 'A field supervisor',
        ));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Photo replaced notification failed permanently', [
            'old_photo_id' => $this->oldPhoto->id,
            'new_photo_id' => $this->newPhoto->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
