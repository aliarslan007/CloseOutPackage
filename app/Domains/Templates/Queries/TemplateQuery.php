<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\Template;
use App\Domains\Users\Models\User;
use App\Shared\Enums\Role;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class TemplateQuery
{
    private const CACHE_TTL_SECONDS = 300;

    public static function flushCache(): void
    {
        if (Cache::supportsTags()) {
            Cache::tags(['templates'])->flush();
        }
    }

    /**
     * @return LengthAwarePaginator<int, Template>
     */
    public function forAdmin(): LengthAwarePaginator
    {
        return Template::query()
            ->with(['market', 'sections'])
            ->withCount('checklistItems')
            ->latest()
            ->paginate(20);
    }

    /**
     * Templates grouped by market name for the admin index view.
     *
     * @return array{grouped: array<int, array{market: string, templates: Collection<int, Template>}>, unassigned: Collection<int, Template>}
     */
    public function groupedByMarket(): array
    {
        $templates = Template::query()
            ->with(['market', 'sections.categories', 'checklistItems', 'duplicatedFrom'])
            ->withCount('checklistItems')
            ->orderBy('market_id')
            ->orderBy('name')
            ->get();

        /** @var array<int, list<Template>> $grouped */
        $grouped = [];
        $unassigned = new Collection;

        foreach ($templates as $template) {
            $grouped[$template->market_id][] = $template;
        }

        $result = [];
        foreach ($grouped as $marketId => $items) {
            $marketName = $items[0]->market->name ?? 'Market deleted — link a market to reactivate';
            $result[] = [
                'market' => $marketName,
                'templates' => new Collection($items),
            ];
        }

        return [
            'grouped' => $result,
            'unassigned' => $unassigned,
        ];
    }

    public function show(Template $template): Template
    {
        $template->load(['market', 'sections.categories', 'checklistItems', 'media']);

        $template->setAttribute('additional_note_images', $this->additionalNoteImages($template));

        return $template;
    }

    /**
     * Payload for the additional-site-notes image grid. Built here
     * rather than as a model accessor so the model stays free of URL concerns.
     *
     * @return list<array{id: int, file_name: string, url: string, thumb_url: string}>
     */
    public function additionalNoteImages(Template $template): array
    {
        return $template
            ->getMedia(Template::ADDITIONAL_NOTES_COLLECTION)
            ->sortBy('order_column')
            ->values()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'file_name' => $media->file_name,
                'url' => route('templates.additional-notes.images.show', [
                    'template' => $template,
                    'media' => $media,
                ]),
                'thumb_url' => route('templates.additional-notes.images.show', [
                    'template' => $template,
                    'media' => $media,
                ]),
            ])
            ->all();
    }

    /**
     * @return Collection<int, Template>
     */
    public function forField(User $user): Collection
    {
        if ($user->hasRole(Role::ADMIN->value)) {
            return $this->remember('all', fn (): Collection => Template::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get());
        }

        /** @var list<int> $marketIds */
        $marketIds = $user->markets()->pluck('markets.id')->all();

        if ($marketIds === []) {
            return new Collection;
        }

        return $this->remember(
            'field.'.$user->id,
            fn (): Collection => Template::query()
                ->whereIn('market_id', $marketIds)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * @return Collection<int, Template>
     */
    public function forMarket(int $marketId): Collection
    {
        return $this->remember(
            "market.{$marketId}",
            fn (): Collection => Template::query()
                ->where('market_id', $marketId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * @return Collection<int, FavoriteTemplate>
     */
    public function favoritesFor(User $user): Collection
    {
        return FavoriteTemplate::query()
            ->with('template.market')
            ->where('user_id', $user->id)
            // whereHas applies Template's SoftDeletes scope, so favorites of
            // deleted templates stay dormant and reappear if it is restored.
            ->whereHas('template')
            ->latest()
            ->get();
    }

    /**
     * Lean favorite options for the given user, optionally scoped to a market
     * (favorites whose template belongs to that market). Single source of
     * truth for the favorite projection used by list UIs.
     *
     * @return list<array{id: int, name: string, template_id: int, category_count: int, created_at: ?string, template: array{id: int, name: string, market: ?array{id: int, name: string}}|null}>
     */
    public function favoriteOptions(User $user, ?int $marketId = null): array
    {
        $favorites = $this->favoritesFor($user);

        if ($marketId !== null) {
            $favorites = $favorites->filter(
                fn (FavoriteTemplate $favorite): bool => $favorite->template?->market_id === $marketId,
            );
        }

        return array_values($favorites
            ->map(fn (FavoriteTemplate $favorite): array => [
                'id' => $favorite->id,
                'name' => $favorite->name,
                'template_id' => $favorite->template_id,
                'category_count' => count($favorite->snapshot ?? []),
                'created_at' => $favorite->created_at?->toISOString(),
                'template' => $this->favoriteTemplatePayload($favorite),
            ])
            ->all());
    }

    /**
     * @return array{id: int, name: string, market: ?array{id: int, name: string}}|null
     */
    private function favoriteTemplatePayload(FavoriteTemplate $favorite): ?array
    {
        if ($favorite->template === null) {
            return null;
        }

        return [
            'id' => $favorite->template->id,
            'name' => $favorite->template->name,
            'market' => $favorite->template->market !== null
                ? [
                    'id' => $favorite->template->market->id,
                    'name' => $favorite->template->market->name,
                ]
                : null,
        ];
    }

    /**
     * @param  Closure(): Collection<int, Template>  $callback
     * @return Collection<int, Template>
     */
    private function remember(string $key, Closure $callback): Collection
    {
        if (! Cache::supportsTags()) {
            return $callback();
        }

        return Cache::tags(['templates'])->remember($key, self::CACHE_TTL_SECONDS, $callback);
    }
}
