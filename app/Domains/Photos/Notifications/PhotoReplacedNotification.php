<?php

declare(strict_types=1);

namespace App\Domains\Photos\Notifications;

use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Models\Photo;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PhotoReplacedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly CloseoutPackage $package,
        private readonly Photo $oldPhoto,
        private readonly Photo $newPhoto,
        private readonly string $uploaderName,
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
        $siteName = $this->package->project->name ?? 'the site';
        $category = $this->oldPhoto->categorySelection?->templatePictureCategory;
        $categoryName = $category !== null ? $category->name : 'Unknown category';

        return [
            'photo_id' => $this->newPhoto->id,
            'package_id' => $this->package->id,
            'project_id' => $this->package->project_id,
            'site_name' => $siteName,
            'category_name' => $categoryName,
            'title' => 'Photo replaced',
            'body' => $this->uploaderName.' uploaded a replacement for '.$categoryName.' on '.$siteName.'.',
        ];
    }
}
