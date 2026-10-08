<?php

declare(strict_types=1);

namespace App\Domains\Markets\Controllers;

use App\Domains\Markets\Actions\GrantMarketAccessAction;
use App\Domains\Markets\Actions\RevokeMarketAccessAction;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Requests\GrantMarketAccessRequest;
use App\Domains\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

class MarketUserController extends Controller
{
    public function __construct(
        private readonly GrantMarketAccessAction $grantMarketAccessAction,
        private readonly RevokeMarketAccessAction $revokeMarketAccessAction,
    ) {}

    public function store(GrantMarketAccessRequest $request, Market $market): RedirectResponse
    {
        /** @var int $userId */
        $userId = $request->validated('user_id');

        $this->grantMarketAccessAction->execute(
            $market,
            $userId,
            $this->user(),
        );

        return redirect()->back()->with('success', 'Market access granted.');
    }

    public function destroy(Market $market, User $user): RedirectResponse
    {
        $this->authorize('revokeAccess', $market);

        $this->revokeMarketAccessAction->execute($market, $user);

        return redirect()->back()->with('success', 'Market access revoked.');
    }
}
