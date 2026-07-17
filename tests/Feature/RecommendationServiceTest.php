<?php

use App\Models\AdminPick;
use App\Models\AprioriRule;
use App\Models\Category;
use App\Models\Product;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('returns empty collection when no products exist', function () {
    $service = new RecommendationService;
    $result = $service->get(null, 6);

    expect($result)->toBeEmpty();
});

it('returns global best sellers when no context product', function () {
    $category = Category::factory()->create();
    $low = Product::factory()->create(['category_id' => $category->id, 'total_sold' => 5, 'stock' => 10]);
    $high = Product::factory()->create(['category_id' => $category->id, 'total_sold' => 50, 'stock' => 10]);

    $service = new RecommendationService;
    $result = $service->get(null, 2);

    expect($result)->toHaveCount(2);
    expect($result->first()->id)->toBe($high->id);
});

it('excludes context product from recommendations', function () {
    $category = Category::factory()->create();
    $context = Product::factory()->create(['category_id' => $category->id, 'total_sold' => 100, 'stock' => 10]);
    Product::factory()->create(['category_id' => $category->id, 'total_sold' => 50, 'stock' => 10]);

    $service = new RecommendationService;
    $result = $service->get($context, 6);

    expect($result->pluck('id'))->not->toContain($context->id);
});

it('excludes out-of-stock products', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'total_sold' => 100, 'stock' => 0]);
    $inStock = Product::factory()->create(['category_id' => $category->id, 'total_sold' => 10, 'stock' => 5]);

    $service = new RecommendationService;
    $result = $service->get(null, 6);

    expect($result->pluck('id'))->toContain($inStock->id);
});

it('falls back to admin picks as last resort', function () {
    $category = Category::factory()->create();
    $pick = Product::factory()->create(['category_id' => $category->id, 'total_sold' => 0, 'stock' => 5]);
    AdminPick::create(['product_id' => $pick->id, 'sort_order' => 1]);

    $service = new RecommendationService;
    $result = $service->get(null, 6);

    expect($result->pluck('id'))->toContain($pick->id);
});

it('respects the limit parameter', function () {
    $category = Category::factory()->create();
    Product::factory(10)->create(['category_id' => $category->id, 'stock' => 10, 'total_sold' => 10]);

    $service = new RecommendationService;
    $result = $service->get(null, 3);

    expect($result)->toHaveCount(3);
});

it('caches results within the same catalog version', function () {
    $category = Category::factory()->create();
    Product::factory(3)->create(['category_id' => $category->id, 'stock' => 10, 'total_sold' => 10]);

    $service = new RecommendationService;

    // Two calls with same params should return identical (cached) results
    $first = $service->get(null, 6);
    $second = $service->get(null, 6);

    expect($first->pluck('id'))->toEqual($second->pluck('id'));
});

it('returns Apriori consequent products when context matches antecedent', function () {
    $category = Category::factory()->create();
    $context = Product::factory()->create(['name' => 'Ayam Geprek Beku', 'category_id' => $category->id, 'stock' => 10]);
    $recommended = Product::factory()->create(['name' => 'Sambal Bawang', 'category_id' => $category->id, 'stock' => 10, 'total_sold' => 50]);
    Product::factory()->create(['name' => 'Nasi Goreng Beku', 'category_id' => $category->id, 'stock' => 10, 'total_sold' => 10]);

    AprioriRule::create([
        'antecedent' => ['Ayam Geprek Beku'],
        'consequent' => ['Sambal Bawang'],
        'support' => 0.05,
        'confidence' => 0.8,
        'lift' => 1.5,
    ]);

    $result = (new RecommendationService)->get($context, 6);

    // Apriori level should return the consequent product first
    expect($result->pluck('name'))->toContain('Sambal Bawang');
    expect($result->first()->name)->toBe('Sambal Bawang');
});

it('falls through to next level when Apriori consequent is empty', function () {
    $category = Category::factory()->create();
    $context = Product::factory()->create(['name' => 'Ayam Geprek Beku', 'category_id' => $category->id, 'stock' => 10]);
    $fallback = Product::factory()->create(['category_id' => $category->id, 'stock' => 10, 'total_sold' => 50]);

    AprioriRule::create([
        'antecedent' => ['Ayam Geprek Beku'],
        'consequent' => [],
        'support' => 0.02,
        'confidence' => 0.6,
        'lift' => 1.0,
    ]);

    $result = (new RecommendationService)->get($context, 6);

    // Should fall through to Level 2 (category best sellers), not crash
    expect($result)->not->toBeEmpty();
    expect($result->pluck('id'))->toContain($fallback->id);
});

it('returns only products from same category in Level 2', function () {
    $cat1 = Category::factory()->create();
    $cat2 = Category::factory()->create();
    $context = Product::factory()->create(['category_id' => $cat1->id, 'stock' => 10]);
    Product::factory()->create(['category_id' => $cat1->id, 'stock' => 10, 'total_sold' => 50]);
    Product::factory()->create(['category_id' => $cat2->id, 'stock' => 10, 'total_sold' => 100]);

    // Use limit=1 so only Level 2 runs (fills all slots), Level 3 doesn't kick in
    $result = (new RecommendationService)->get($context, 1);

    expect($result)->toHaveCount(1);
    expect($result->first()->category_id)->toBe($cat1->id);
});

it('invalidates cache when catalog_version changes', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['category_id' => $category->id, 'stock' => 10, 'total_sold' => 10]);

    $service = new RecommendationService;
    $first = $service->get(null, 6);
    expect($first)->not->toBeEmpty();

    // Simulate catalog_version bump (as if a product/category/admin pick changed)
    Cache::increment('catalog_version');

    $newProduct = Product::factory()->create(['category_id' => $category->id, 'total_sold' => 999, 'stock' => 10]);
    $second = $service->get(null, 6);

    // Results should differ because cache was invalidated by version bump
    expect($first->pluck('id'))->not->toEqual($second->pluck('id'));
    expect($second->pluck('id'))->toContain($newProduct->id);
});
