<?php

use App\Models\AdminPick;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows creating up to 5 admin picks', function () {
    $products = Product::factory(5)->create(['stock' => 10]);

    foreach ($products as $i => $product) {
        AdminPick::create(['product_id' => $product->id, 'sort_order' => $i]);
    }

    expect(AdminPick::count())->toBe(5);
});

it('throws when exceeding max 5 admin picks', function () {
    $products = Product::factory(6)->create(['stock' => 10]);

    foreach ($products->take(5) as $i => $product) {
        AdminPick::create(['product_id' => $product->id, 'sort_order' => $i]);
    }

    AdminPick::create(['product_id' => $products->last()->id, 'sort_order' => 6]);
})->throws(RuntimeException::class, 'Maksimal 5 Pilihan Admin.');

it('allows updating an existing admin pick without triggering max constraint', function () {
    $products = Product::factory(5)->create(['stock' => 10]);

    foreach ($products as $i => $product) {
        AdminPick::create(['product_id' => $product->id, 'sort_order' => $i]);
    }

    $pick = AdminPick::first();
    $pick->update(['sort_order' => 99]);

    expect($pick->fresh()->sort_order)->toBe(99);
    expect(AdminPick::count())->toBe(5);
});
