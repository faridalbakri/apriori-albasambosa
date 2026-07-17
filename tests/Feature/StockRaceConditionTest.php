<?php

use App\Actions\CreateOrder;
use App\Enums\OrderStatus;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(function () {
    Bus::fake();
    Category::factory()->create();
});

// ── Stock Reservation Guards ──

it('prevents checkout when stock is insufficient due to other reservation', function () {
    $product = Product::factory()->create([
        'stock' => 1,
        'stock_reserved' => 0,
        'price' => 50_000,
    ]);

    // stock_reserved is NOT in $fillable — use increment (bypasses mass-assignment)
    $product->increment('stock_reserved', 1);

    $cart = new Cart;
    $cart->fill(['product_id' => $product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $product->price;
    $cart->save();

    $action = new CreateOrder;

    expect(fn () => $action(
        collect([$cart]),
        null,
        'pickup',
        now()->addDay()->format('Y-m-d H:i'),
        'Test Customer',
        '+6281234567890',
        0,
        null,
        null,
    ))->toThrow(RuntimeException::class, 'tidak mencukupi');

    expect(Cart::count())->toBe(1);
    expect(Order::count())->toBe(0);
});

it('successfully checks out when stock is exactly available', function () {
    $product = Product::factory()->create([
        'stock' => 5,
        'stock_reserved' => 0,
        'price' => 50_000,
    ]);

    $cart = new Cart;
    $cart->fill(['product_id' => $product->id, 'quantity' => 5, 'session_id' => session()->getId()]);
    $cart->price = $product->price;
    $cart->save();

    $action = new CreateOrder;

    $order = $action(
        collect([$cart]),
        null,
        'pickup',
        now()->addDay()->format('Y-m-d H:i'),
        'Test Customer',
        '+6281234567890',
        0,
        null,
        null,
    );

    expect($order->status)->toBe(OrderStatus::Pending)
        ->and((int) $product->fresh()->stock_reserved)->toBe(5);

    // Cart cleared after successful checkout
    expect(Cart::count())->toBe(0);
});

it('prevents two sequential checkouts exceeding stock', function () {
    $product = Product::factory()->create([
        'stock' => 3,
        'stock_reserved' => 0,
        'price' => 50_000,
    ]);

    // First checkout: reserve 2 of 3
    $cart1 = new Cart;
    $cart1->fill(['product_id' => $product->id, 'quantity' => 2, 'session_id' => 'sess-1']);
    $cart1->price = $product->price;
    $cart1->save();

    $action = new CreateOrder;

    $order1 = $action(
        collect([$cart1]),
        null,
        'pickup',
        now()->addDay()->format('Y-m-d H:i'),
        'Customer A',
        '+6281234567890',
        0,
        null,
        null,
    );

    expect($order1->status)->toBe(OrderStatus::Pending)
        ->and((int) $product->fresh()->stock_reserved)->toBe(2);

    // Second checkout: try to reserve 2 more (only 1 available: 3-2=1)
    $cart2 = new Cart;
    $cart2->fill(['product_id' => $product->id, 'quantity' => 2, 'session_id' => 'sess-2']);
    $cart2->price = $product->price;
    $cart2->save();

    expect(fn () => $action(
        collect([$cart2]),
        null,
        'pickup',
        now()->addDay()->format('Y-m-d H:i'),
        'Customer B',
        '+6281234567891',
        0,
        null,
        null,
    ))->toThrow(RuntimeException::class, 'tidak mencukupi');

    // Only 1 order created, only 2 reserved (not 4)
    expect(Order::count())->toBe(1)
        ->and((int) $product->fresh()->stock_reserved)->toBe(2);
});

// ── DB Transaction Atomicity ──

it('rolls back all changes if any product has insufficient stock', function () {
    $product1 = Product::factory()->create(['stock' => 5, 'stock_reserved' => 0, 'price' => 30_000]);
    $product2 = Product::factory()->create(['stock' => 1, 'stock_reserved' => 1, 'price' => 20_000]);

    $cart1 = new Cart;
    $cart1->fill(['product_id' => $product1->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart1->price = $product1->price;
    $cart1->save();

    $cart2 = new Cart;
    $cart2->fill(['product_id' => $product2->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart2->price = $product2->price;
    $cart2->save();

    $action = new CreateOrder;

    expect(fn () => $action(
        collect([$cart1, $cart2]),
        null,
        'pickup',
        now()->addDay()->format('Y-m-d H:i'),
        'Test Customer',
        '+6281234567890',
        0,
        null,
        null,
    ))->toThrow(RuntimeException::class);

    // Product 1 stock_reserved should NOT be incremented (rolled back)
    expect((int) $product1->fresh()->stock_reserved)->toBe(0);

    // No order created
    expect(Order::count())->toBe(0);

    // Carts preserved (not cleared)
    expect(Cart::count())->toBe(2);
});

it('throws on empty cart', function () {
    $action = new CreateOrder;

    expect(fn () => $action(
        collect([]),
        null,
        'pickup',
        now()->addDay()->format('Y-m-d H:i'),
        'Test',
        '+6281234567890',
        0,
        null,
        null,
    ))->toThrow(InvalidArgumentException::class, 'Keranjang belanja kosong.');
});
