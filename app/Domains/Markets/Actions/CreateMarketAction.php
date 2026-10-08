<?php

declare(strict_types=1);

namespace App\Domains\Markets\Actions;

use App\Domains\Markets\DTOs\CreateMarketDTO;
use App\Domains\Markets\Events\MarketCreated;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CreateMarketAction
{
    public function execute(CreateMarketDTO $dto): Market
    {
        $market = DB::transaction(function () use ($dto): Market {
            $market = Market::create([
                'name' => $dto->name,
                'slug' => Market::generateUniqueSlug($dto->name),
                'description' => $dto->description,
                'is_active' => $dto->isActive,
                'created_by' => Auth::id(),
            ]);

            MarketCreated::dispatch($market);

            return $market;
        });

        MarketQuery::flushCache();

        return $market;
    }
}
