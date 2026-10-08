<?php

declare(strict_types=1);

namespace App\Domains\Projects\Rules;

use App\Domains\Customers\Models\CustomerEngineer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Guards the project form cascade: the chosen engineer must really belong to
 * the submitted customer + region (market), never a foreign territory.
 */
final class EngineerBelongsToRegion implements ValidationRule
{
    public function __construct(
        private readonly mixed $customerId,
        private readonly mixed $marketId,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_numeric($value)) {
            return;
        }

        $query = CustomerEngineer::query()->whereKey((int) $value);

        if (is_numeric($this->customerId)) {
            $query->where('customer_id', (int) $this->customerId);
        }

        if (is_numeric($this->marketId)) {
            $query->where('market_id', (int) $this->marketId);
        }

        if (! $query->exists()) {
            $fail('The selected engineer does not belong to the chosen customer and region.');
        }
    }
}
