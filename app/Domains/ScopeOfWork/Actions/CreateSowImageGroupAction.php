<?php

declare(strict_types=1);

namespace App\Domains\ScopeOfWork\Actions;

use App\Domains\ScopeOfWork\Models\SowImageGroup;
use App\Domains\ScopeOfWork\Models\SowReport;

final class CreateSowImageGroupAction
{
    public function __construct(
        private readonly AssertSowImagesEditableAction $assertEditable,
    ) {}

    public function execute(SowReport $report, string $name): SowImageGroup
    {
        $this->assertEditable->execute($report, 'Only draft or in-review SOWs can be grouped.');

        // Append to the end so new groups land last in the PDF. max() is null on
        // the first group, so seed from 0 rather than casting null to 0 and +1.
        $maxOrder = $report->imageGroups()->max('sort_order');
        $nextOrder = is_numeric($maxOrder) ? ((int) $maxOrder) + 1 : 0;

        return $report->imageGroups()->create([
            'name' => $name,
            'sort_order' => $nextOrder,
        ]);
    }
}
