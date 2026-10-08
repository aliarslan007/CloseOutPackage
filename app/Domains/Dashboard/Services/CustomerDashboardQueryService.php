<?php

declare(strict_types=1);

namespace App\Domains\Dashboard\Services;

use App\Domains\ChangeOrders\Enums\ChangeOrderStatus;
use App\Domains\ChangeOrders\Models\ChangeOrder;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Photos\Models\Photo;
use App\Domains\Projects\Models\Project;
use App\Domains\Tracking\Enums\TrackingStatus;
use App\Domains\Tracking\Models\LocationPing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

final class CustomerDashboardQueryService
{
    /**
     * @param  Builder<Project>  $base
     * @return list<array{id: string, label: string, project: string, href: string, meta: string, created_at: string|null}>
     */
    public function customerActivity(Builder $base): array
    {
        $projectIds = (clone $base)->select('id');

        return Activity::query()
            ->whereIn('subject_id', $projectIds)
            ->where('subject_type', Project::class)
            ->with('subject:id,name')
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Activity $activity): array => [
                'id' => (string) $activity->id,
                'label' => Str::headline($activity->description),
                'project' => $activity->subject?->name ?? 'Project update',
                'href' => $activity->subject !== null ? route('projects.show', $activity->subject) : route('dashboard'),
                'meta' => $activity->created_at?->diffForHumans() ?? '',
                'created_at' => $activity->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param  Builder<Project>  $base
     * @return list<array{id: int, name: string, status: string, progress: int, href: string}>
     */
    public function customerProjects(Builder $base): array
    {
        return (clone $base)->latest('updated_at')->limit(6)->get(['id', 'name', 'status', 'progress_percentage'])
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status->value,
                'progress' => (int) ($project->progress_percentage ?? 0),
                'href' => route('projects.show', $project),
            ])->all();
    }

    /**
     * @param  Builder<Project>  $base
     * @return list<array{id: int, title: string, project: string, amount: string, href: string, status: string}>
     */
    public function customerAttention(Builder $base): array
    {
        return ChangeOrder::query()
            ->whereHas('project')
            ->whereIn('project_id', (clone $base)->select('id'))
            ->whereIn('status', [
                ChangeOrderStatus::SENT_TO_CUSTOMER->value,
                ChangeOrderStatus::CUSTOMER_ACTION_REQUIRED->value,
            ])
            ->with('project:id,name')
            ->latest()
            ->limit(5)
            ->get(['id', 'project_id', 'title', 'total_amount', 'status'])
            ->filter(fn (ChangeOrder $order): bool => $order->project !== null)
            ->map(fn (ChangeOrder $order): array => [
                'id' => $order->id,
                'title' => $order->title,
                'project' => $order->project?->name ?? 'Project',
                'amount' => '$'.number_format((float) $order->total_amount, 2),
                'href' => $order->project !== null ? route('customer.change-orders.show', [$order->project, $order]) : '#',
                'status' => $order->status->value,
            ])->values()->all();
    }

    /**
     * @param  Builder<CloseoutPackage>  $packages
     * @return list<array{id: int, url: string, full_url: string, project: string, captured_at: string|null}>
     */
    public function recentPhotos(Builder $packages): array
    {
        return Photo::query()
            ->whereIn(
                'closeout_package_id',
                (clone $packages)
                    ->whereIn('status', CloseoutPackage::CUSTOMER_ACCESSIBLE_STATUSES)
                    ->whereNull('locked_at')
                    ->select('id')
            )
            ->whereNull('replaced_at')
            ->where('is_approved', true)
            ->whereHas('media')
            ->with(['package.project:id,name', 'media'])
            ->latest('captured_at')
            ->latest('id')
            ->limit(4)
            ->get()
            ->map(fn (Photo $photo): array => [
                'id' => $photo->id,
                'url' => $photo->thumb_url ?? '',
                'full_url' => $photo->original_url ?? $photo->url ?? '',
                'project' => $photo->package?->project?->name ?? 'Project photo',
                'captured_at' => $photo->captured_at?->toIso8601String(),
            ])->all();
    }

    /**
     * @param  Builder<Project>  $base
     * @return array{technicians: int, project: string|null, href: string|null, updated_at: string|null}
     */
    public function fieldActivity(Builder $base): array
    {
        $ping = LocationPing::query()
            ->withinActiveWindow()
            ->where('status', TrackingStatus::ACTIVE->value)
            ->whereIn('project_id', (clone $base)->select('id'))
            ->with('project:id,name')
            ->latest('pinged_at')
            ->first();

        return [
            'technicians' => LocationPing::query()->withinActiveWindow()->where('status', TrackingStatus::ACTIVE->value)->whereIn('project_id', (clone $base)->select('id'))->distinct('user_id')->count('user_id'),
            'project' => $ping?->project?->name,
            'href' => $ping?->project !== null ? route('projects.show', $ping->project) : null,
            'updated_at' => $ping?->pinged_at?->toIso8601String(),
        ];
    }
}
