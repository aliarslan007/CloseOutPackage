<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowImageGroup;
use App\Domains\ScopeOfWork\Models\SowReport;
use Illuminate\Support\Facades\DB;

final class DeleteSowImageGroupAction
{
    public function __construct(
        private readonly AssertSowImagesEditableAction $assertEditable,
    ) {}

    public function execute(SowReport $report, SowImageGroup $group): void
    {
        $this->assertEditable->execute($report, 'Only draft or in-review SOWs can be deleted.');

        DB::transaction(function () use ($group): void {
            // Ungroup explicitly rather than leaning on the FK's ON DELETE SET NULL,
            // so the behaviour is identical on every driver.
            $group->images()->update(['sow_image_group_id' => null]);
            $group->delete();
        });
    }
}
