<?php

declare(strict_types=1);

namespace App\Domains\Templates\Actions;

use App\Domains\Templates\Models\FavoriteTemplate;
use App\Domains\Templates\Models\TemplatePictureCategory;
use Illuminate\Support\Facades\Log;

final class LoadFavoriteTemplateAction
{
    /**
     * @return list<array<string, mixed>>
     */
    public function execute(FavoriteTemplate $favorite): array
    {
        /** @var list<array<string, mixed>> $snapshot */
        $snapshot = $favorite->snapshot ?? [];

        if ($snapshot === []) {
            return [];
        }

        $validIds = $this->validCategoryIds($snapshot, $favorite->id);

        return array_values(array_filter(
            $snapshot,
            fn (array $item): bool => $this->isReferenced($item) && in_array($item['category_id'], $validIds, true),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $snapshot
     * @return list<int>
     */
    private function validCategoryIds(array $snapshot, int $favoriteId): array
    {
        $referencedIds = [];

        foreach ($snapshot as $item) {
            $categoryId = $item['category_id'] ?? null;

            if (is_int($categoryId)) {
                $referencedIds[] = $categoryId;
            }
        }

        /** @var list<int> $validIds */
        $validIds = TemplatePictureCategory::query()
            ->whereIn('id', $referencedIds)
            ->pluck('id')
            ->all();

        $orphans = array_diff($referencedIds, $validIds);

        if ($orphans !== []) {
            Log::warning('Favorite template references orphaned categories', [
                'favorite_id' => $favoriteId,
                'category_ids' => array_values($orphans),
            ]);
        }

        return $validIds;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function isReferenced(array $item): bool
    {
        return is_int($item['category_id'] ?? null);
    }
}
