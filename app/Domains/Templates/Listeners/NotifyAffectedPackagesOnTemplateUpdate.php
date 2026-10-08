<?php

declare(strict_types=1);

namespace App\Domains\Templates\Listeners;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Jobs\NotifyPackageDriftJob;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Templates\Enums\TemplateDriftChangeType;
use App\Domains\Templates\Events\TemplateCategoryUpdated;
use App\Domains\Templates\Events\TemplateChecklistItemUpdated;
use Illuminate\Database\Eloquent\Collection;

/**
 * Fans out a queued drift-check job for every live draft package on the edited
 * template. Detection itself is deferred to the job — this listener stays cheap
 * so an admin's save request is never blocked by package work.
 */
class NotifyAffectedPackagesOnTemplateUpdate
{
    public function handle(TemplateCategoryUpdated|TemplateChecklistItemUpdated $event): void
    {
        // Only a bucket change can leave a draft unable to submit; required and
        // explain fields are safe-healed on the next draft save, and headings
        // have no effect on packages. No fan-out means no pointless jobs.
        if ($event->changeType !== TemplateDriftChangeType::BUCKET_CHANGED) {
            return;
        }

        $templateId = $this->resolveTemplateId($event);

        if ($templateId === null) {
            return;
        }

        CloseoutPackage::query()
            ->where('template_id', $templateId)
            ->where('status', PackageStatus::DRAFT->value)
            ->select('id')
            ->chunkById(100, function (Collection $packages): void {
                foreach ($packages as $package) {
                    NotifyPackageDriftJob::dispatch($package->id);
                }
            });
    }

    private function resolveTemplateId(TemplateCategoryUpdated|TemplateChecklistItemUpdated $event): ?int
    {
        if ($event instanceof TemplateChecklistItemUpdated) {
            return $event->item->template_id;
        }

        return $event->category->section?->template_id;
    }
}
