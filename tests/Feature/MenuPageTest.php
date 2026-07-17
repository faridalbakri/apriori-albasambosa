<?php

use App\Livewire\ProductCatalog;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->frozen = Category::factory()->create(['name' => 'Frozen Food', 'order' => 1]);
    $this->sambal = Category::factory()->create(['name' => 'Sambal', 'order' => 2]);
    $this->empty = Category::factory()->create(['name' => 'Kosong', 'order' => 3]);

    $this->sambosa = Product::factory()->create([
        'category_id' => $this->frozen->id,
        'name' => 'Sambosa Original',
        'price' => 35000,
        'total_sold' => 100,
        'stock' => 10,
        'created_at' => now()->subHours(3),
    ]);
    $this->risoles = Product::factory()->create([
        'category_id' => $this->frozen->id,
        'name' => 'Risoles Ragout',
        'price' => 30000,
        'total_sold' => 50,
        'stock' => 5,
        'created_at' => now()->subHours(2),
    ]);
    $this->sambal_ijo = Product::factory()->create([
        'category_id' => $this->sambal->id,
        'name' => 'Sambal Ijo',
        'price' => 25000,
        'total_sold' => 75,
        'stock' => 8,
        'created_at' => now()->subHour(),
    ]);
});

test('menu page shows hero and product grid', function () {
    $this->get(route('catalog.index'))
        ->assertOk()
        ->assertSee('Dari Dapur Kami, ke Meja Anda');
});

test('product catalog shows all products', function () {
    Livewire::test(ProductCatalog::class)
        ->assertSee('Sambosa Original')
        ->assertSee('Risoles Ragout')
        ->assertSee('Sambal Ijo');
});

test('product catalog sorts by terlaris (total_sold) by default', function () {
    $html = Livewire::test(ProductCatalog::class)->html();
    $sambosaPos = strpos($html, 'Sambosa Original');
    $risolesPos = strpos($html, 'Risoles Ragout');
    expect($sambosaPos)->toBeLessThan($risolesPos);
});

test('product catalog can sort by termurah (price asc)', function () {
    $html = Livewire::test(ProductCatalog::class)
        ->set('sort', 'termurah')
        ->html();
    $sambalPos = strpos($html, 'Sambal Ijo');
    $risolesPos = strpos($html, 'Risoles Ragout');
    expect($sambalPos)->toBeLessThan($risolesPos);
});

test('product catalog can sort by terbaru (created_at desc)', function () {
    $html = Livewire::test(ProductCatalog::class)
        ->set('sort', 'terbaru')
        ->html();
    $risolesPos = strpos($html, 'Risoles Ragout');
    $sambosaPos = strpos($html, 'Sambosa Original');
    expect($risolesPos)->toBeLessThan($sambosaPos);
});

test('category pills exclude categories with no in-stock products', function () {
    Livewire::test(ProductCatalog::class)
        ->assertDontSee($this->empty->name)
        ->assertSee('Frozen Food')
        ->assertSee('Sambal');
});

test('category filter shows only that category', function () {
    Livewire::test(ProductCatalog::class)
        ->call('selectCategory', $this->sambal->slug)
        ->assertSee('Sambal Ijo')
        ->assertDontSee('Sambosa Original');
});

test('search resets category to all', function () {
    Livewire::test(ProductCatalog::class)
        ->call('selectCategory', $this->sambal->slug)
        ->set('search', 'Sambosa')
        ->assertSee('Sambosa Original');
});

test('quick-add via POST /cart/add adds product to cart', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
    ]);

    $response->assertOk()
        ->assertJson(['message' => 'Ditambahkan ke keranjang!']);

    $this->assertDatabaseHas('carts', [
        'product_id' => $product->id,
        'quantity' => 1,
    ]);
});

test('quick-add fails when product out of stock', function () {
    $product = Product::factory()->create(['stock' => 0]);

    $response = $this->postJson(route('cart.store'), [
        'product_id' => $product->id,
    ]);

    $response->assertStatus(422);
});
