<?php

declare(strict_types=1);

namespace App\Domains\Markets\Actions;

use App\Domains\Markets\DTOs\UpdateMarketDTO;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use Illuminate\Support\Facades\DB;

final class UpdateMarketAction
{
    public function execute(Market $market, UpdateMarketDTO $dto): Market
    {
        return DB::transaction(function () use ($market, $dto): Market {
            $attributes = $this->resolveAttributes($dto, $market);

            if ($attributes !== []) {
                $market->update($attributes);
            }

            MarketQuery::flushCache();

            return $market;
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveAttributes(UpdateMarketDTO $dto, Market $market): array
    {
        $attributes = [];

        if ($dto->name !== null && $dto->name !== $market->name) {
            $attributes['name'] = $dto->name;
            $attributes['slug'] = Market::generateUniqueSlug($dto->name);
        }

        if ($dto->description !== null) {
            $attributes['description'] = $dto->description;
        }

        if ($dto->isActive !== null) {
            $attributes['is_active'] = $dto->isActive;
        }

        return $attributes;
    }
}
