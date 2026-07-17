<?php

use App\Models\AdminPick;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── Catalog version tests ──

it('increments catalog_version when product is created', function () {
    Cache::put('catalog_version', 1);

    Product::factory()->create();

    // ProductFactory auto-creates Category too, so 2 increments
    expect(Cache::get('catalog_version'))->toBe(3);
});

it('increments catalog_version when product is updated', function () {
    $product = Product::factory()->create();
    Cache::put('catalog_version', 1);

    $product->update(['name' => 'Updated Name']);

    expect(Cache::get('catalog_version'))->toBe(2);
});

it('increments catalog_version when product is deleted', function () {
    $product = Product::factory()->create();
    Cache::put('catalog_version', 1);

    $product->delete();

    expect(Cache::get('catalog_version'))->toBe(2);
});

it('increments catalog_version when category changes', function () {
    Cache::put('catalog_version', 1);

    Category::factory()->create();

    expect(Cache::get('catalog_version'))->toBe(2);
});

// ── Product detail cache tests ──

it('forgets product detail cache key on product update', function () {
    $product = Product::factory()->create();
    Cache::put("product:{$product->id}", $product, 600);

    $product->update(['name' => 'Updated Name']);

    expect(Cache::has("product:{$product->id}"))->toBeFalse();
});

it('forgets product detail cache key on product delete', function () {
    $product = Product::factory()->create();
    Cache::put("product:{$product->id}", $product, 600);

    $product->delete();

    expect(Cache::has("product:{$product->id}"))->toBeFalse();
});

// ── Config cache tests ──

it('forgets config:categories cache key on category change', function () {
    Cache::put('config:categories', collect(), 3600);

    Category::factory()->create();

    expect(Cache::has('config:categories'))->toBeFalse();
});

it('increments catalog_version on admin pick change', function () {
    $product = Product::factory()->create();
    $before = Cache::get('catalog_version', 1);

    AdminPick::create(['product_id' => $product->id, 'sort_order' => 1]);

    expect(Cache::get('catalog_version', 1))->toBeGreaterThan($before);
});

it('increments catalog_version on admin pick delete', function () {
    $product = Product::factory()->create();
    $pick = AdminPick::create(['product_id' => $product->id, 'sort_order' => 1]);
    $before = Cache::get('catalog_version', 1);

    $pick->delete();

    expect(Cache::get('catalog_version', 1))->toBeGreaterThan($before);
});
