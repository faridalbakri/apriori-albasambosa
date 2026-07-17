<?php

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Cache;

class CategoryObserver
{
    /**
     * Handle the Category "saved" event (created + updated).
     */
    public function saved(Category $category): void
    {
        Cache::forget('config:categories');
        Cache::increment('catalog_version');

        // Clear individual product caches — product detail pages cache
        // $product->load('category'), so a category rename makes them stale.
        $category->products()->select('id')->chunk(100, function ($products) {
            foreach ($products as $product) {
                Cache::forget("product:{$product->id}");
            }
        });
    }

    /**
     * Handle the Category "deleted" event.
     */
    public function deleted(Category $category): void
    {
        Cache::forget('config:categories');
        Cache::increment('catalog_version');

        $category->products()->select('id')->chunk(100, function ($products) {
            foreach ($products as $product) {
                Cache::forget("product:{$product->id}");
            }
        });
    }
}
