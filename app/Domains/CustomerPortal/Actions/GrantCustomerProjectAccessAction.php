<?php

declare(strict_types=1);

namespace App\Domains\CustomerPortal\Actions;

use App\Domains\CustomerPortal\DTOs\GrantCustomerAccessDTO;
use App\Domains\CustomerPortal\Events\CustomerAccessGranted;
use App\Domains\CustomerPortal\Models\CustomerProjectAccess;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class GrantCustomerProjectAccessAction
{
    public function execute(GrantCustomerAccessDTO $dto): void
    {
        $customer = User::query()->findOrFail($dto->userId);

        foreach ($dto->projectIds as $projectId) {
            $project = Project::query()->findOrFail($projectId);

            DB::transaction(function () use ($dto, $customer, $project): void {
                $access = CustomerProjectAccess::updateOrCreate(
                    ['user_id' => $customer->id, 'project_id' => $project->id],
                    [
                        'granted_by' => $dto->grantedBy,
                        'expires_at' => $dto->expiresAt,
                    ],
                );

                CustomerAccessGranted::dispatch($access);

                activity('security')
                    ->performedOn($project)
                    ->causedBy($dto->grantedBy !== null ? User::query()->find($dto->grantedBy) : null)
                    ->event('customer.access.granted')
                    ->withProperties(['customer_id' => $customer->id, 'expires_at' => $dto->expiresAt?->toDateTimeString()])
                    ->log('Customer project access granted');
            });
        }
    }
}
