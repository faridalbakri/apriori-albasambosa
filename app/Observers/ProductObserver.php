<?php

namespace App\Observers;

use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class ProductObserver
{
    /**
     * Handle the Product "saved" event (created + updated).
     */
    public function saved(Product $product): void
    {
        Cache::forget("product:{$product->id}");
        Cache::increment('catalog_version');
    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        Cache::forget("product:{$product->id}");
        Cache::increment('catalog_version');
    }
}
