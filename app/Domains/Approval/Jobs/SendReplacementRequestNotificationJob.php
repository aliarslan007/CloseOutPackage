<?php

declare(strict_types=1);

namespace App\Domains\Approval\Jobs;

use App\Domains\Approval\Notifications\PhotoReplacementRequestedNotification;
use App\Domains\Photos\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendReplacementRequestNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 60, 120];

    public function __construct(public readonly Photo $photo) {}

    public function handle(): void
    {
        $this->photo->load('uploader');

        $uploader = $this->photo->uploader;

        if ($uploader === null) {
            return;
        }

        $uploader->notify(new PhotoReplacementRequestedNotification($this->photo));
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Replacement request notification failed permanently', [
            'photo_id' => $this->photo->id,
            'error' => $exception?->getMessage(),
        ]);
    }
}
