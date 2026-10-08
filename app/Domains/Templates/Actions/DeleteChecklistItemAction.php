<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\PackageChecklistResponse;
use App\Domains\Templates\Models\ChecklistItem;
use Illuminate\Support\Facades\DB;

final class DeleteChecklistItemAction
{
    public function execute(ChecklistItem $item): void
    {
        DB::transaction(function () use ($item): void {
            PackageChecklistResponse::query()
                ->where('checklist_item_id', $item->id)
                ->whereNull('answer')
                ->whereHas('package', fn ($query) => $query->where('status', PackageStatus::DRAFT))
                ->delete();

            $item->delete();
        });
    }
}
