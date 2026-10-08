<?php

declare(strict_types=1);

namespace App\Domains\CloseoutPackages\Actions;

use App\Domains\CloseoutPackages\DTOs\UpdatePackageChecklistDTO;
use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Shared\Exceptions\InvalidStateTransitionException;
use Illuminate\Support\Facades\DB;

final class UpdatePackageChecklistAction
{
    public function execute(CloseoutPackage $package, UpdatePackageChecklistDTO $dto): void
    {
        if ($package->status !== PackageStatus::DRAFT) {
            throw new InvalidStateTransitionException('This package has been submitted and its checklist can no longer be changed.');
        }

        DB::transaction(function () use ($package, $dto): void {
            foreach ($dto->responses as $response) {
                $package->checklistResponses()->updateOrCreate(
                    ['checklist_item_id' => $response['checklist_item_id']],
                    [
                        'answer' => $response['answer'] ?? null,
                        'explain' => $response['explain'] ?? null,
                        'is_na' => (bool) ($response['is_na'] ?? false),
                        'quantity' => $response['quantity'] ?? null,
                    ],
                );
            }
        });
    }
}
