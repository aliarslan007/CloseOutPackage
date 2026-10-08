<?php

declare(strict_types=1);

namespace App\Domains\Markets\Actions;

use App\Domains\Markets\Events\MarketUserAccessGranted;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Domains\Users\Models\User;
use Illuminate\Support\Facades\DB;

final class GrantMarketAccessAction
{
    public function execute(Market $market, int $userId, ?User $grantedBy): void
    {
        $user = User::findOrFail($userId);

        DB::transaction(function () use ($market, $user, $grantedBy): void {
            $market->users()->attach($user->id, ['granted_by' => $grantedBy?->id]);

            MarketUserAccessGranted::dispatch($market, $user, $grantedBy);

            activity('security')
                ->performedOn($market)
                ->causedBy($grantedBy)
                ->event('access.granted')
                ->withProperties(['user_id' => $user->id, 'email' => $user->email])
                ->log('Market access granted');
        });

        MarketQuery::flushCache();
        TemplateQuery::flushCache();
    }
}
