<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowImageGroup;
use App\Domains\ScopeOfWork\Models\SowReport;

final class UpdateSowImageGroupAction
{
    public function __construct(
        private readonly AssertSowImagesEditableAction $assertEditable,
    ) {}

    public function execute(SowReport $report, SowImageGroup $group, string $name): SowImageGroup
    {
        $this->assertEditable->execute($report, 'Only draft or in-review SOWs can be renamed.');

        $group->update(['name' => $name]);

        return $group;
    }
}
