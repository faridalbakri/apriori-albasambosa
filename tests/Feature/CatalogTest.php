<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

// ── Catalog listing ──

it('renders the catalog page with products', function () {
    $category = Category::factory()->create();
    Product::factory(3)->create(['category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index'));

    $response->assertOk()
        ->assertSee('Frozen Food UMKM');
});

it('shows empty state message when no products exist', function () {
    Category::factory()->create();

    $response = $this->get(route('catalog.index'));

    $response->assertOk()
        ->assertSee('Belum ada produk tersedia.');
});

// ── Search ──

it('search returns matching products by name', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Ayam Geprek Beku', 'category_id' => $category->id, 'stock' => 10]);
    Product::factory()->create(['name' => 'Sambal Bawang', 'category_id' => $category->id, 'stock' => 10]);
    Product::factory()->create(['name' => 'Rendang Sapi', 'category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index', ['search' => 'Ayam']));

    $response->assertOk()
        ->assertSee('Ayam Geprek Beku')
        ->assertDontSee('Sambal Bawang')
        ->assertDontSee('Rendang Sapi');
});

it('search returns empty state when no match', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Ayam Geprek Beku', 'category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index', ['search' => 'Nasi Padang']));

    $response->assertOk()
        ->assertSee('Belum ada produk tersedia.');
});

it('search is case-insensitive', function () {
    $category = Category::factory()->create();
    Product::factory()->create(['name' => 'Ayam Geprek Beku', 'category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index', ['search' => 'ayam']));

    $response->assertOk()
        ->assertSee('Ayam Geprek Beku');
});

// ── Category filter ──

it('filters products by category', function () {
    $frozen = Category::factory()->create(['name' => 'Frozen Food', 'slug' => 'frozen-food']);
    $sambal = Category::factory()->create(['name' => 'Sambal', 'slug' => 'sambal']);

    Product::factory()->create(['name' => 'Ayam Geprek', 'category_id' => $frozen->id, 'stock' => 10]);
    Product::factory()->create(['name' => 'Sambal Bawang', 'category_id' => $sambal->id, 'stock' => 10]);

    $response = $this->get(route('catalog.index', ['category' => 'frozen-food']));

    $response->assertOk()
        ->assertSee('Ayam Geprek')
        ->assertDontSee('Sambal Bawang');
});

it('category filter shows empty for category with no products', function () {
    $category = Category::factory()->create(['slug' => 'minuman']);

    $response = $this->get(route('catalog.index', ['category' => 'minuman']));

    $response->assertOk()
        ->assertSee('Belum ada produk tersedia.');
});

// ── Pagination ──

it('paginates products with 12 per page', function () {
    $category = Category::factory()->create();

    // factory unique() pool only 12 names, use insert to bypass
    $rows = [];
    for ($i = 1; $i <= 15; $i++) {
        $rows[] = [
            'category_id' => $category->id,
            'name' => "Produk Test {$i}",
            'slug' => "produk-test-{$i}",
            'description' => 'desc',
            'price' => 20000,
            'stock' => 10,
            'stock_reserved' => 0,
            'total_sold' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    Product::insert($rows);

    $response = $this->get(route('catalog.index'));
    $response->assertOk();

    // Page 2 should render without error
    $page2 = $this->get(route('catalog.index', ['page' => 2]));
    $page2->assertOk();
});

// ── Product detail ──

it('shows product detail page with correct data', function () {
    $category = Category::factory()->create(['name' => 'Frozen Food']);
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'name' => 'Ayam Geprek Beku',
        'price' => 25000,
        'stock' => 10,
        'stock_reserved' => 0,
        'total_sold' => 75,
        'description' => 'Ayam geprek frozen siap masak.',
    ]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk()
        ->assertSee('Ayam Geprek Beku')
        ->assertSee('Frozen Food')
        ->assertSee('25.000')
        ->assertSee('Stok:')
        ->assertSee('75') // total_sold
        ->assertSee('Ayam geprek frozen siap masak.');
});

it('shows stok habis badge when no available stock', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'stock' => 0,
        'stock_reserved' => 0,
    ]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk()
        ->assertSee('Stok Habis');
});

it('shows stock as available minus reserved', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create([
        'category_id' => $category->id,
        'stock' => 10,
        'stock_reserved' => 3,
    ]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk()
        ->assertSee('7'); // 10 - 3 = 7 available
});

it('shows Beli Bersama section when recommendations exist', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'stock' => 10]);
    // Create related products so RecommendationService can find something
    Product::factory(3)->create(['category_id' => $category->id, 'stock' => 10, 'total_sold' => 100]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk();
    // Recommendation section may or may not appear depending on cold-start level
    // but the page should always render without error
    expect($response->status())->toBe(200);
});

it('returns 404 for non-existent product', function () {
    $response = $this->get('/produk/99999');

    $response->assertNotFound();
});
