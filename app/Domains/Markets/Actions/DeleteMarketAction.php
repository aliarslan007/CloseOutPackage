<?php

declare(strict_types=1);

namespace App\Domains\Markets\Actions;

use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Projects\Enums\ProjectStatus;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Shared\Exceptions\MarketHasActiveProjectsException;
use Illuminate\Support\Facades\DB;

final class DeleteMarketAction
{
    public function execute(Market $market): void
    {
        if ($this->hasActiveProjects($market)) {
            throw new MarketHasActiveProjectsException('This market has active projects and cannot be deleted.');
        }

        DB::transaction(function () use ($market): void {
            // Templates outlive their market: they stay on file but go inactive,
            // because a template with no market cannot be picked for field work.
            // Re-linking one to a market (template edit) clears the reason again.
            $market->templates()->update([
                'is_active' => false,
                'inactive_reason' => sprintf(
                    'Market "%s" was deleted on %s. Link this template to a market to reactivate it.',
                    $market->name,
                    now()->toDateString(),
                ),
            ]);

            $market->delete();
        });

        TemplateQuery::flushCache();
        MarketQuery::flushCache();
    }

    /**
     * Block deletion whenever ongoing work references the market — a deleted
     * market would leave its projects dangling (no market name in emails,
     * no market scope in visibility). Deactivating the market first must not
     * bypass that; only fully completed/archived projects allow deletion.
     */
    private function hasActiveProjects(Market $market): bool
    {
        return $market->projects()
            ->whereNotIn('status', [ProjectStatus::COMPLETED->value, ProjectStatus::ARCHIVED->value])
            ->exists();
    }
}
