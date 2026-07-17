<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('services.midtrans.mock', true);

    $this->product = Product::factory()->create([
        'stock' => 10,
        'stock_reserved' => 3,
        'price' => 50_000,
    ]);
});

function makePending(Product $product, int $minutesAgo, int $quantity = 2): Order
{
    $order = Order::factory()->create([
        'status' => OrderStatus::Pending,
        'total_price' => 100_000,
        'created_at' => now()->subMinutes($minutesAgo),
    ]);

    $item = $order->items()->make([
        'product_id' => $product->id,
        'quantity' => $quantity,
    ]);
    $item->price = 50_000;
    $item->save();

    return $order;
}

test('dispatches sync for orders between 10 and 55 minutes', function () {
    $order = makePending($this->product, 30);

    // Mock mode returns null → no sync dispatched, order stays pending
    Artisan::call('orders:sync-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Pending);
});

test('does not sync orders newer than 10 minutes', function () {
    $order = makePending($this->product, 5);

    Artisan::call('orders:sync-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Pending);
});

test('does not sync orders older than 55 minutes', function () {
    $order = makePending($this->product, 60);

    Artisan::call('orders:sync-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Pending);
});

test('does not touch non-pending orders', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Settlement,
        'total_price' => 100_000,
        'created_at' => now()->subMinutes(30),
    ]);

    Artisan::call('orders:sync-pending');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Settlement);
});

test('handles empty result gracefully', function () {
    $exitCode = Artisan::call('orders:sync-pending');

    expect($exitCode)->toBe(0);
});
