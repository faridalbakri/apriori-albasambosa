<?php

namespace App\Services;

use App\Models\AdminPick;
use App\Models\AprioriRule;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class RecommendationService
{
    /**
     * Get recommended products using 4-level cold-start cascade.
     *
     * Level 1: Apriori rules (if context product and rules exist)
     * Level 2: Best sellers from same category
     * Level 3: Global best sellers
     * Level 4: Admin picks
     *
     * Each level only fills the remaining slots up to $limit.
     * Products with stock <= 0 are excluded.
     */
    public function get(?Product $context = null, int $limit = 6): Collection
    {
        // cache version prevents __PHP_Incomplete_Class after deploys.
        // Use catalog_version so product/stock/AdminPick changes invalidate recommendations.
        $version = Cache::get('catalog_version', 1);
        $cacheKey = 'recommendations.v'.$version.'.'.md5(serialize([$context?->id, $limit]));

        // guard against __PHP_Incomplete_Class from stale cached
        // Eloquent models after deploys. Check type before returning.
        $cached = Cache::get($cacheKey);

        if ($cached instanceof Collection) {
            return $cached;
        }

        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 600, fn () => $this->compute($context, $limit));
    }

    private function compute(?Product $context, int $limit): Collection
    {
        $results = collect();
        $excludeIds = collect();

        if ($context) {
            $excludeIds->push($context->id);
        }

        // Level 1: Apriori rules
        $remaining = $limit - $results->count();
        if ($remaining > 0 && $context) {
            $aprioriProducts = $this->fromApriori($context, $remaining, $excludeIds);
            $results = $results->concat($aprioriProducts);
            $excludeIds = $excludeIds->concat($aprioriProducts->pluck('id'));
        }

        // Level 2: Best sellers from same category
        $remaining = $limit - $results->count();
        if ($remaining > 0 && $context?->category_id) {
            $categoryProducts = $this->fromCategoryBestSellers($context->category_id, $remaining, $excludeIds);
            $results = $results->concat($categoryProducts);
            $excludeIds = $excludeIds->concat($categoryProducts->pluck('id'));
        }

        // Level 3: Global best sellers
        $remaining = $limit - $results->count();
        if ($remaining > 0) {
            $globalProducts = $this->fromGlobalBestSellers($remaining, $excludeIds);
            $results = $results->concat($globalProducts);
            $excludeIds = $excludeIds->concat($globalProducts->pluck('id'));
        }

        // Level 4: Admin picks
        $remaining = $limit - $results->count();
        if ($remaining > 0) {
            $adminPicks = $this->fromAdminPicks($remaining, $excludeIds);
            $results = $results->concat($adminPicks);
        }

        return $results->take($limit);
    }

    private function baseQuery(): Builder
    {
        return Product::with('category')->where('stock', '>', 0);
    }

    /**
     * Level 1: Find products in consequent of Apriori rules where
     * the context product ID appears in the antecedent.
     */
    private function fromApriori(Product $context, int $limit, Collection $excludeIds): Collection
    {
        // whereJsonContains filters at DB level, avoids loading all rules into memory.
        // Rules store product IDs (not names), so renaming a product doesn't break associations.
        $rules = AprioriRule::whereJsonContains('antecedent', $context->id)->get();

        if ($rules->isEmpty()) {
            return collect();
        }

        $consequentIds = collect();

        foreach ($rules as $rule) {
            $consequent = $rule->consequent ?? [];
            foreach ($consequent as $id) {
                $consequentIds->push((int) $id);
            }
        }

        if ($consequentIds->isEmpty()) {
            return collect();
        }

        return $this->baseQuery()
            ->whereIn('id', $consequentIds->unique()->values())
            ->tap(fn ($q) => $this->excludeIds($q, $excludeIds))
            ->orderByDesc('total_sold')
            ->take($limit)
            ->get();
    }

    /**
     * Level 2: Best sellers within the same category.
     */
    private function fromCategoryBestSellers(int $categoryId, int $limit, Collection $excludeIds): Collection
    {
        return $this->baseQuery()
            ->where('category_id', $categoryId)
            ->tap(fn ($q) => $this->excludeIds($q, $excludeIds))
            ->orderByDesc('total_sold')
            ->take($limit)
            ->get();
    }

    /**
     * Level 3: Global best sellers across all categories.
     */
    private function fromGlobalBestSellers(int $limit, Collection $excludeIds): Collection
    {
        return $this->baseQuery()
            ->tap(fn ($q) => $this->excludeIds($q, $excludeIds))
            ->orderByDesc('total_sold')
            ->take($limit)
            ->get();
    }

    /**
     * Level 4: Admin-picked products, ordered by sort_order.
     */
    private function fromAdminPicks(int $limit, Collection $excludeIds): Collection
    {
        $pickIds = AdminPick::orderBy('sort_order')
            ->pluck('product_id')
            ->diff($excludeIds)
            ->take($limit);

        if ($pickIds->isEmpty()) {
            return collect();
        }

        // lookup map O(n) instead of search() O(n²) in sortBy
        $orderMap = array_flip($pickIds->toArray());

        return $this->baseQuery()
            ->whereIn('id', $pickIds->toArray())
            ->get()
            ->sortBy(fn (Product $product) => $orderMap[$product->id] ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Add a whereNotIn('id', ...) clause when exclude IDs are present.
     */
    private function excludeIds(Builder $query, Collection $excludeIds): void
    {
        if ($excludeIds->isNotEmpty()) {
            $query->whereNotIn('id', $excludeIds->toArray());
        }
    }
}
