<?php

use App\Models\Order;
use App\Models\Product;
use App\Services\MidtransService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $product = Product::factory()->create(['price' => 50_000]);

    $this->order = Order::factory()->create([
        'total_price' => 100_000,
        'shipping_cost' => 0,
    ]);

    $item = $this->order->items()->make([
        'product_id' => $product->id,
        'quantity' => 2,
    ]);
    $item->price = 50_000;
    $item->save();
});

// --- Mock Mode: createSnapToken ---

test('createSnapToken returns deterministic mock token', function () {
    // Mock mode is enabled globally via phpunit.xml (MIDTRANS_MOCK=true)
    $token = MidtransService::createSnapToken($this->order);

    expect($token)->toBe('mock-snap-token-'.$this->order->id);
});

test('createSnapToken returns unique tokens per order', function () {
    $order2 = Order::factory()->create(['total_price' => 50_000]);

    $token1 = MidtransService::createSnapToken($this->order);
    $token2 = MidtransService::createSnapToken($order2);

    expect($token1)->not->toBe($token2)
        ->and($token1)->toBeString()
        ->and($token2)->toBeString();
});

// --- Mock Mode: getOrderStatus ---

test('getOrderStatus returns null in mock mode', function () {
    $result = MidtransService::getOrderStatus($this->order->order_number);

    expect($result)->toBeNull();
});

test('getOrderStatus returns null for any order number in mock mode', function () {
    $result = MidtransService::getOrderStatus('ALBA-20990101-999');

    expect($result)->toBeNull();
    // null triggers fallback-to-webhook-payload path in ProcessMidtransWebhook
});
