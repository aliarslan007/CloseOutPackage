<?php

declare(strict_types=1);

namespace App\Domains\Projects\Actions;

use App\Domains\Customers\Actions\AssignEngineerToProjectAction;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Projects\DTOs\UpdateProjectDTO;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Projects\Events\ProjectStatusChanged;
use App\Domains\Projects\Models\Project;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateProjectAction
{
    public function execute(Project $project, UpdateProjectDTO $dto, User $actor): Project
    {
        return DB::transaction(function () use ($project, $dto, $actor): Project {
            $previousStatus = $this->resolveStatus($project->getRawOriginal('status'));

            $attributes = $this->resolveAttributes($dto);

            if ($attributes !== []) {
                $project->update($attributes);
            }

            if ($dto->managerId !== null && $dto->managerId !== $project->manager_id) {
                app(AssignManagerAction::class)->execute($project, $dto->managerId, $actor);
            }

            if ($dto->customerEngineerProvided) {
                $this->syncCustomerEngineer($project, $dto->customerEngineerId);
            }

            $currentStatus = $this->resolveStatus($project->getRawOriginal('status'));

            if ($currentStatus !== $previousStatus) {
                ProjectStatusChanged::dispatch($project, $previousStatus, $currentStatus);
            }

            $this->syncPackageSiteInfos($project);

            return $project->refresh();
        });
    }

    private function syncCustomerEngineer(Project $project, ?int $engineerId): void
    {
        $engineer = $engineerId !== null ? CustomerEngineer::query()->find($engineerId) : null;

        app(AssignEngineerToProjectAction::class)->execute($project, $engineer);
    }

    private function syncPackageSiteInfos(Project $project): void
    {
        $manager = $project->manager;
        $packages = $project->packages()->with('siteInfo')->get();

        $completeAddress = trim(implode(', ', array_filter([
            $project->address,
            $project->city,
            trim(($project->state ?? '').' '.($project->zip ?? '')),
        ])));

        foreach ($packages as $package) {
            $siteInfo = $package->siteInfo;
            if ($siteInfo === null) {
                continue;
            }

            // Engineer fields always sync (even to null) so the package never
            // keeps a stale snapshot once the project resolves differently.
            $siteInfo->update(array_filter([
                'site_name' => $project->name,
                'ps_number' => $project->ps_number,
                'project_number' => $project->project_number,
                'address' => $completeAddress ?: $project->address,
                'complete_date' => $project->completed_date,
                'pm_user_id' => $manager?->id,
                'pm_name' => $manager?->name,
                'pm_phone' => $manager?->phone,
            ], fn ($val): bool => $val !== null && $val !== '') + [
                'vzw_engineer_name' => $project->vzw_engineer_name,
                'vzw_engineer_phone' => $project->vzw_engineer_phone,
                'vzw_engineer_email' => $project->vzw_engineer_email,
            ]);
        }
    }

    private function resolveStatus(mixed $rawStatus): ProjectStatus
    {
        if (! is_string($rawStatus)) {
            return ProjectStatus::PENDING;
        }

        return ProjectStatus::tryFrom($rawStatus) ?? ProjectStatus::PENDING;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAttributes(UpdateProjectDTO $dto): array
    {
        $attributes = [];

        if ($dto->marketId !== null) {
            $attributes['market_id'] = $dto->marketId;
        }

        if ($dto->customerProvided) {
            // The form always submits the customer select, so trust it: a null
            // here means "Select a customer…" — unlink the project.
            $attributes['customer_id'] = $dto->customerId;
        }

        if ($dto->name !== null) {
            $attributes['name'] = $dto->name;
        }

        if ($dto->psNumber !== null) {
            $attributes['ps_number'] = $dto->psNumber;
        }

        if ($dto->projectNumber !== null) {
            $attributes['project_number'] = $dto->projectNumber;
        }

        if ($dto->address !== null) {
            $attributes['address'] = $dto->address;
        }

        if ($dto->city !== null) {
            $attributes['city'] = $dto->city;
        }

        if ($dto->state !== null) {
            $attributes['state'] = $dto->state;
        }

        if ($dto->zip !== null) {
            $attributes['zip'] = $dto->zip;
        }

        if ($dto->vzwEngineerName !== null) {
            $attributes['vzw_engineer_name'] = $dto->vzwEngineerName;
        }

        if ($dto->vzwEngineerEmail !== null) {
            $attributes['vzw_engineer_email'] = $dto->vzwEngineerEmail;
        }

        if ($dto->vzwEngineerPhone !== null) {
            $attributes['vzw_engineer_phone'] = $dto->vzwEngineerPhone;
        }

        if ($dto->completedDate !== null) {
            $attributes['completed_date'] = $dto->completedDate;
        }

        foreach (
            [
                'awarded_date' => $dto->awardedDate,
                'rec_issued_date' => $dto->recIssuedDate,
                'construction_started_date' => $dto->constructionStartedDate,
                'site_completed_date' => $dto->siteCompletedDate,
            ] as $column => $date
        ) {
            if ($date !== null) {
                $attributes[$column] = $date;
            }
        }

        if ($dto->status !== null) {
            $attributes['status'] = $dto->status;
        }

        if ($dto->notes !== null) {
            $attributes['notes'] = $dto->notes;
        }

        return $attributes;
    }
}
