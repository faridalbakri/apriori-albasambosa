<?php

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
});

it('shows Beli Bersama section on product detail page', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'stock' => 10]);
    Product::factory(3)->create(['category_id' => $category->id, 'stock' => 10, 'total_sold' => 100]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk()
        ->assertSee('Beli Bersama');
});

it('hides Beli Bersama section when no recommendations available', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'stock' => 10]);

    $response = $this->get(route('catalog.show', $product));

    $response->assertOk()
        ->assertDontSee('Beli Bersama');
});
