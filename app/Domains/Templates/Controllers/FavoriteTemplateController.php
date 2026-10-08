<?php

declare(strict_types=1);

namespace App\Domains\Templates\Controllers;

use App\Domains\Templates\Actions\DeleteFavoriteTemplateAction;
use App\Domains\Templates\Actions\SaveFavoriteTemplateAction;
use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Queries\TemplateQuery;
use App\Domains\Templates\Requests\StoreFavoriteTemplateRequest;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FavoriteTemplateController extends Controller
{
    public function __construct(
        private readonly TemplateQuery $templateQuery,
        private readonly SaveFavoriteTemplateAction $saveFavoriteAction,
        private readonly DeleteFavoriteTemplateAction $deleteFavoriteAction,
    ) {}

    public function index(): Response
    {
        $favorites = $this->templateQuery->favoriteOptions($this->user());

        return Inertia::render('Templates/Favorites', [
            'favorites' => $favorites,
        ]);
    }

    public function store(StoreFavoriteTemplateRequest $request): RedirectResponse
    {
        $this->saveFavoriteAction->execute($request->toDTO($this->user()->id));

        return redirect()->back()->with('success', 'Favorite template saved.');
    }

    public function destroy(FavoriteTemplate $favorite): RedirectResponse
    {
        $this->authorize('delete', $favorite);

        $this->deleteFavoriteAction->execute($favorite);

        return redirect()->back()->with('success', 'Favorite template deleted.');
    }
}
