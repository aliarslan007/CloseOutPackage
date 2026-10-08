<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Customers\Actions\AssignEngineerToProjectAction;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Markets\Models\Market;
use App\Domains\Projects\DTOs\CreateProjectDTO;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Events\ProjectCreated;
use App\Domains\Projects\Events\ProjectManagerAssigned;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use App\Shared\Exceptions\MarketAccessDeniedException;
use Illuminate\Support\Facades\DB;

final class CreateProjectAction
{
    public function execute(CreateProjectDTO $dto, User $actor): Project
    {
        $market = Market::findOrFail($dto->marketId);

        if (! $this->canAccessMarket($actor, $market)) {
            throw new MarketAccessDeniedException('You do not have access to this market.');
        }

        return DB::transaction(function () use ($dto, $actor): Project {
            // A manager who creates a project owns it — they become its manager
            // automatically. Only admins assign a manager explicitly.
            $manager = null;
            if ($actor->hasRole(Role::MANAGER->value)) {
                $manager = $actor;
            } elseif ($dto->managerId !== null) {
                $manager = User::findOrFail($dto->managerId);
            }

            $project = Project::create([
                'market_id' => $dto->marketId,
                'customer_id' => $dto->customerId,
                'name' => $dto->name,
                'ps_number' => $dto->psNumber,
                'project_number' => $dto->projectNumber,
                'address' => $dto->address,
                'city' => $dto->city,
                'state' => $dto->state,
                'zip' => $dto->zip,
                'vzw_engineer_name' => $dto->vzwEngineerName,
                'vzw_engineer_email' => $dto->vzwEngineerEmail,
                'vzw_engineer_phone' => $dto->vzwEngineerPhone,
                'status' => ProjectStatus::PENDING,
                'progress_percentage' => 0,
                'manager_id' => $manager?->id,
                'created_by' => $actor->id,
                'completed_date' => $dto->completedDate,
                'awarded_date' => $dto->awardedDate,
                'rec_issued_date' => $dto->recIssuedDate,
                'construction_started_date' => $dto->constructionStartedDate,
                'site_completed_date' => $dto->siteCompletedDate,
                'notes' => $dto->notes,
            ]);

            ProjectCreated::dispatch($project);

            // Reuse the single assignment path so the legacy vzw_engineer_*
            // snapshot stays in sync with the assigned customer engineer.
            if ($dto->customerEngineerId !== null) {
                $engineer = CustomerEngineer::query()->find($dto->customerEngineerId);

                if ($engineer !== null) {
                    app(AssignEngineerToProjectAction::class)->execute($project, $engineer);
                }
            }

            // Notify an explicitly assigned manager (admin flow). A manager who
            // created the project is not notified about their own action.
            if ($manager !== null && $manager->id !== $actor->id) {
                ProjectManagerAssigned::dispatch($project, $manager);
            }

            return $project;
        });
    }

    private function canAccessMarket(User $user, Market $market): bool
    {
        return $user->hasRole(Role::ADMIN->value) || $market->userHasAccess($user);
    }
}
