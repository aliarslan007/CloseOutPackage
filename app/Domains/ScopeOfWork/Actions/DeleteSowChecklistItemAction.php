<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowChecklistItem;
use Illuminate\Support\Facades\DB;

final class DeleteSowChecklistItemAction
{
    public function execute(SowChecklistItem $item): void
    {
        DB::transaction(function () use ($item): void {
            $item->response()->delete();
            $item->delete();
        });
    }
}
