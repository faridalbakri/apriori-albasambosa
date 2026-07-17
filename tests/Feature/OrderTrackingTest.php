<?php

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Category::factory()->create();
    $this->product = Product::factory()->create([
        'stock' => 10,
        'stock_reserved' => 0,
        'price' => 50_000,
    ]);

    $this->order = new Order;
    $this->order->fill([
        'order_number' => 'ALBA-'.now()->format('Ymd').'-001',
        'phone' => '+6281234567890',
        'recipient_name' => 'Test Guest',
        'payment_method' => null,
    ]);
    $this->order->total_price = 100_000;
    $this->order->status = OrderStatus::Pending->value;
    $this->order->shipping_cost = 0;
    $this->order->save();

    OrderStatusLog::create([
        'order_id' => $this->order->id,
        'old_status' => null,
        'new_status' => OrderStatus::Pending->value,
    ]);
});

// --- Page Load ---

test('cek status page loads', function () {
    $this->get(route('orders.track'))->assertOk();
});

// --- Lookup ---

test('guest can track order with valid order number and phone', function () {
    $response = $this->post(route('orders.lookup'), [
        'order_number' => $this->order->order_number,
        'phone' => '+6281234567890',
    ]);

    $response->assertOk()
        ->assertSee($this->order->order_number)
        ->assertSee('Menunggu Pembayaran')
        ->assertSee('Test Guest');
});

test('lookup fails with wrong phone', function () {
    $response = $this->post(route('orders.lookup'), [
        'order_number' => $this->order->order_number,
        'phone' => '+6289999999999',
    ]);

    $response->assertOk()
        ->assertSee('Pesanan Tidak Ditemukan');
    // Note: order_number still visible in form's old() value, which is expected UX
});

test('lookup fails with wrong order number', function () {
    $response = $this->post(route('orders.lookup'), [
        'order_number' => 'ALBA-20200101-999',
        'phone' => '+6281234567890',
    ]);

    $response->assertOk()
        ->assertSee('Pesanan Tidak Ditemukan');
});

test('lookup validates required fields', function () {
    $this->post(route('orders.lookup'), [])
        ->assertSessionHasErrors(['order_number', 'phone']);
});

// --- Registered user orders ---

test('registered user order is also trackable via phone', function () {
    $user = User::factory()->create();

    $order = new Order;
    $order->fill([
        'order_number' => 'ALBA-'.now()->format('Ymd').'-002',
        'phone' => '+6281234567890',
        'recipient_name' => $user->name,
    ]);
    $order->user_id = $user->id;
    $order->total_price = 50000;
    $order->status = OrderStatus::Pending->value;
    $order->shipping_cost = 0;
    $order->save();

    $this->post(route('orders.lookup'), [
        'order_number' => $order->order_number,
        'phone' => '+6281234567890',
    ])->assertOk()
        ->assertSee($order->order_number);
});

// --- Timeline ---

test('order with multiple status logs shows timeline', function () {
    OrderStatusLog::create([
        'order_id' => $this->order->id,
        'old_status' => OrderStatus::Pending->value,
        'new_status' => OrderStatus::Processing->value,
    ]);

    $this->order->update(['status' => OrderStatus::Processing->value]);

    $response = $this->post(route('orders.lookup'), [
        'order_number' => $this->order->order_number,
        'phone' => '+6281234567890',
    ]);

    $response->assertOk()
        ->assertSee('Diproses');
});

// --- Rate limiting ---

test('lookup is rate limited to 5 per minute', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('orders.lookup'), [
            'order_number' => $this->order->order_number,
            'phone' => '+6281234567890',
        ])->assertOk();
    }

    // 6th attempt should be throttled
    $this->post(route('orders.lookup'), [
        'order_number' => $this->order->order_number,
        'phone' => '+6281234567890',
    ])->assertTooManyRequests();
});
