<?php

declare(strict_types=1);

namespace App\Domains\Templates\Queries;

use App\Domains\Templates\Models\CategoryPreset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class CategoryPresetQuery
{
    /**
     * Admin library page: list with optional filters and sort.
     *
     * @param  array{search?: string|null, status?: string|null, sort?: string|null}  $filters
     * @return Collection<int, CategoryPreset>
     */
    public function listForAdmin(array $filters = []): Collection
    {
        $query = CategoryPreset::query();

        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyStatus($query, $filters['status'] ?? null);
        $this->applySort($query, $filters['sort'] ?? null);

        return $query->get();
    }

    /**
     * Active presets ordered by name — used by the builder's "pull from library" picker.
     *
     * @return Collection<int, CategoryPreset>
     */
    public function listActive(): Collection
    {
        return CategoryPreset::query()
            ->active()
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  Builder<CategoryPreset>  $query
     */
    private function applySearch(Builder $query, ?string $search): void
    {
        $term = trim((string) $search);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $inner) use ($term): void {
            $inner->where('name', 'like', '%'.$term.'%')
                ->orWhere('description', 'like', '%'.$term.'%');
        });
    }

    /**
     * @param  Builder<CategoryPreset>  $query
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === null || $status === '' || $status === 'all') {
            return;
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }
    }

    /**
     * @param  Builder<CategoryPreset>  $query
     */
    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'name_desc' => $query->orderByDesc('name'),
            'oldest' => $query->orderBy('created_at'),
            'newest' => $query->orderByDesc('created_at'),
            default => $query->orderBy('name'),
        };
    }
}
