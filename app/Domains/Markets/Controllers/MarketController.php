<?php

declare(strict_types=1);

namespace App\Domains\Markets\Controllers;

use App\Domains\Markets\Actions\CreateMarketAction;
use App\Domains\Markets\Actions\DeleteMarketAction;
use App\Domains\Markets\Actions\UpdateMarketAction;
use App\Domains\Markets\Models\Market;
use App\Domains\Markets\Queries\MarketQuery;
use App\Domains\Markets\Requests\StoreMarketRequest;
use App\Domains\Markets\Requests\UpdateMarketRequest;
use App\Domains\Users\Queries\UserQuery;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class MarketController extends Controller
{
    public function __construct(
        private readonly MarketQuery $marketQuery,
        private readonly UserQuery $userQuery,
        private readonly CreateMarketAction $createMarketAction,
        private readonly UpdateMarketAction $updateMarketAction,
        private readonly DeleteMarketAction $deleteMarketAction,
    ) {}

    public function index(): Response
    {
        $assignableUsers = $this->userQuery->assignableForMarket();

        return Inertia::render('Admin/Markets/Index', [
            'markets' => $this->marketQuery->listForAdmin(),
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Markets/Create');
    }

    public function store(StoreMarketRequest $request): RedirectResponse
    {
        $this->createMarketAction->execute($request->toDTO());

        return redirect()->route('admin.markets.index')->with('success', 'Market created.');
    }

    public function edit(Market $market): Response
    {
        $market->load('users.roles');

        $assignableUsers = $this->userQuery->assignableForMarket();

        return Inertia::render('Admin/Markets/Edit', [
            'market' => $market,
            'assignedUsers' => $market->users,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function update(UpdateMarketRequest $request, Market $market): RedirectResponse
    {
        $this->updateMarketAction->execute($market, $request->toDTO());

        return redirect()->route('admin.markets.index')->with('success', 'Market updated.');
    }

    public function destroy(Market $market): RedirectResponse
    {
        $this->authorize('delete', $market);

        $this->deleteMarketAction->execute($market);

        return redirect()->route('admin.markets.index')->with('success', 'Market deleted.');
    }
}
