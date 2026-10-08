<?php

declare(strict_types=1);

namespace App\Domains\Projects\Models\Concerns;

use App\Domains\CloseoutPackages\Enums\PackageStatus;
use App\Domains\CloseoutPackages\Models\CloseoutPackage;
use App\Domains\Customers\Models\CustomerEngineer;
use App\Domains\Customers\Queries\CustomerHierarchyQuery;
use App\Domains\Projects\Models\Project;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;

/**
 * Accessors and virtual attributes appended to serialized Project models.
 *
 * @mixin Project
 *
 * @property-read Collection<int, CloseoutPackage> $closeoutPackages
 */
trait HasProjectAccessors
{
    private ?CustomerEngineer $resolvedDisplayEngineer = null;

    private bool $displayEngineerResolved = false;

    /**
     * Resolve Verizon Engineer Name: explicitly assigned engineer first,
     * then the default engineer for the project's customer + market, then
     * the legacy snapshot column. Unassigned projects resolve to null so
     * the UI renders its empty state instead of a global fallback.
     *
     * @return Attribute<string|null, never>
     */
    protected function vzwEngineerName(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->resolveDisplayEngineer()?->name
                ?? (($value !== null && $value !== '') ? $value : null),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function vzwEngineerEmail(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->resolveDisplayEngineer()?->email
                ?? (($value !== null && $value !== '') ? $value : null),
        );
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function vzwEngineerPhone(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $this->resolveDisplayEngineer()?->phone
                ?? (($value !== null && $value !== '') ? $value : null),
        );
    }

    /**
     * The engineer of record for display: the explicitly assigned engineer,
     * else the default engineer for this project's customer + market.
     * Memoized per instance (one resolution covers all three fields).
     */
    private function resolveDisplayEngineer(): ?CustomerEngineer
    {
        if ($this->displayEngineerResolved) {
            return $this->resolvedDisplayEngineer;
        }
        $this->displayEngineerResolved = true;

        if ($this->getAttribute('customer_engineer_id') !== null) {
            $linked = $this->relationLoaded('customerEngineer')
                ? $this->getRelation('customerEngineer')
                : $this->customerEngineer()->first();

            if ($linked instanceof CustomerEngineer) {
                return $this->resolvedDisplayEngineer = $linked;
            }
        }

        $customerId = $this->getAttribute('customer_id');
        $marketId = $this->getAttribute('market_id');

        if (! is_numeric($customerId) || ! is_numeric($marketId)) {
            return $this->resolvedDisplayEngineer = null;
        }

        return $this->resolvedDisplayEngineer = app(CustomerHierarchyQuery::class)
            ->defaultEngineerFor((int) $customerId, (int) $marketId);
    }

    /**
     * Count of the project's packages per status — lets the manager spot
     * "submitted, needs review" projects directly from the project list.
     * Only serialized on the index via withAppends() after eager loading.
     *
     * @return array<string, int>
     */
    public function getPackageStatusesAttribute(): array
    {
        $counts = array_fill_keys(
            array_column(PackageStatus::cases(), 'value'),
            0,
        );

        foreach ($this->closeoutPackages->groupBy(fn (CloseoutPackage $package): string => $package->status->value) as $status => $packages) {
            $counts[(string) $status] = $packages->count();
        }

        return $counts;
    }
}
