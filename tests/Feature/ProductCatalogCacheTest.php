<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Catalog browsing tests ──
//
// caching removed from ProductController. Both 'database' and 'file'
// cache drivers fail to properly serialize/deserialize Eloquent models across
// HTTP requests (__PHP_Incomplete_Class / "property on string" errors).
// These queries are sub-millisecond with eager loading — premature optimization.
// Re-add caching tests when switching to Redis (which handles serialization).

it('renders the catalog page with products', function () {
    $category = Category::factory()->create();
    Product::factory(3)->create(['category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index'));

    $response->assertOk()
        ->assertSee($category->name);
});

it('filters products by search query', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Ayam Geprek', 'category_id' => $category->id, 'stock' => 10]);
    Product::factory()->create(['name' => 'Sambal Bawang', 'category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index', ['search' => 'Ayam']));

    $response->assertOk()
        ->assertSee('Ayam Geprek')
        ->assertDontSee('Sambal Bawang');
});

it('renders product detail page', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk()
        ->assertSee($product->name);
});
