<?php

declare(strict_types=1);

namespace App\Domains\Markets\Actions;

use App\Domains\Markets\Events\MarketUserAccessRevoked;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class RevokeMarketAccessAction
{
    public function execute(Market $market, User $user): void
    {
        DB::transaction(function () use ($market, $user): void {
            $market->users()->detach($user->id);

            MarketUserAccessRevoked::dispatch($market, $user);

            activity('security')
                ->performedOn($market)
                ->causedBy(Auth::user())
                ->event('access.revoked')
                ->withProperties(['user_id' => $user->id, 'email' => $user->email])
                ->log('Market access revoked');
        });

        MarketQuery::flushCache();
        TemplateQuery::flushCache();
    }
}
