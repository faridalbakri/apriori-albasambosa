<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::forever('retention.guest_months', 24);
});

test('dry run displays guest orders past retention without modifying data', function () {
    $oldOrder = Order::factory()->create([
        'user_id' => null,
        'phone' => '+6281234567890',
        'status' => OrderStatus::Completed,
        'created_at' => now()->subMonths(25)->subDays(8),
    ]);

    $this->artisan('privacy:anonymize-guests --dry-run')
        ->expectsOutputToContain($oldOrder->order_number)
        ->assertExitCode(0);

    $oldOrder->refresh();
    expect($oldOrder->phone)->not->toBeNull();
});

test('execution nullifies phone on qualifying guest orders', function () {
    $order = Order::factory()->create([
        'user_id' => null,
        'phone' => '+6281234567890',
        'status' => OrderStatus::Completed,
        'created_at' => now()->subMonths(25)->subDays(8),
    ]);

    $this->artisan('privacy:anonymize-guests')->assertExitCode(0);

    $order->refresh();
    expect($order->phone)->toBeNull();
});

test('skips orders with active statuses', function () {
    $activeStatuses = ['pending', 'settlement', 'processing', 'ready_pickup', 'shipping'];

    foreach ($activeStatuses as $status) {
        $order = Order::factory()->create([
            'user_id' => null,
            'phone' => '+6281234567890',
            'status' => OrderStatus::from($status),
            'created_at' => now()->subMonths(30),
        ]);

        $this->artisan('privacy:anonymize-guests');

        $order->refresh();
        expect($order->phone)->not->toBeNull();
    }
});

test('skips orders within grace period', function () {
    $order = Order::factory()->create([
        'user_id' => null,
        'phone' => '+6281234567890',
        'status' => OrderStatus::Completed,
        'created_at' => now()->subMonths(24)->subDays(3),
    ]);

    $this->artisan('privacy:anonymize-guests');

    $order->refresh();
    expect($order->phone)->not->toBeNull();
});

test('skips orders that already have null phone', function () {
    Order::factory()->create([
        'user_id' => null,
        'phone' => null,
        'status' => OrderStatus::Completed,
        'created_at' => now()->subMonths(30),
    ]);

    $this->artisan('privacy:anonymize-guests')->assertExitCode(0);
});

test('inserts anonymization log for each anonymized guest order', function () {
    Order::factory()->create([
        'user_id' => null,
        'phone' => '+6281234567890',
        'status' => OrderStatus::Completed,
        'created_at' => now()->subMonths(30),
    ]);

    $this->artisan('privacy:anonymize-guests');

    $this->assertDatabaseHas('anonymization_logs', [
        'user_id' => null,
        'action_type' => 'auto_anonymize_guest',
    ]);
});
