<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->product = Product::factory()->create([
        'stock' => 10,
        'stock_reserved' => 3,
        'price' => 50_000,
    ]);
});

function createPendingOrder(Product $product, int $hoursAgo, int $quantity = 2): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'total_price' => 100_000,
        'created_at' => now()->subHours($hoursAgo),
    ]);

    $item = $order->items()->make([
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);
    $item->price = 50_000;
    $item->save();

    return $order;
}

test('expires orders older than 1 hour', function () {
    $order = createPendingOrder($this->product, 2);

    Artisan::call('orders:expire-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Expire);
});

test('does not expire orders newer than 1 hour', function () {
    $order = createPendingOrder($this->product, 0);

    Artisan::call('orders:expire-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Pending);
});

test('does not touch non-pending orders', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Settlement,
        'total_price' => 100_000,
        'created_at' => now()->subHours(2),
    ]);

    Artisan::call('orders:expire-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Settlement);
});

test('releases reserved stock on expiry', function () {
    createPendingOrder($this->product, 2, 2);

    Artisan::call('orders:expire-pending');

    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

test('handles empty result gracefully', function () {
    $exitCode = Artisan::call('orders:expire-pending');

    expect($exitCode)->toBe(0);
});
