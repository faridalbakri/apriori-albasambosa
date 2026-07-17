<?php

use App\Actions\TransitionOrderStatus;
use App\Contracts\DeliveryService;
use App\Enums\OrderStatus;
use App\Jobs\ProcessBiteshipWebhook;
use App\Models\Category;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Shipment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Category::factory()->create();
    config([
        'services.biteship.mock' => true,
        'services.biteship.api_key' => 'test-api-key-for-biteship',
    ]);

    $this->product = Product::factory()->create(['stock' => 10, 'price' => 50_000]);

    // Create a processed order with shipment ready for shipping
    $this->order = new Order;
    $this->order->fill([
        'order_number' => 'ALBA-20260711-001',
        'payment_method' => 'midtrans_snap',
        'recipient_name' => 'Budi',
        'phone' => '+6281234567890',
        'address_detail' => 'Jl. Merdeka No.1, Jakarta',
        'postal_code' => '12950',
    ]);
    $this->order->total_price = 50_000;
    $this->order->shipping_cost = 0;
    $this->order->status = OrderStatus::Processing;
    $this->order->save();

    $this->shipment = Shipment::create([
        'order_id' => $this->order->id,
        'courier' => 'jne',
        'courier_service' => 'reg',
        'tracking_status' => 'pending',
    ]);
});

// --- Webhook ---

test('biteship webhook endpoint returns 200 ok', function () {
    Queue::fake();

    config(['services.biteship.mock' => true]);

    $payload = [
        'event' => 'order.status',
        'waybill_id' => 'WAYBILL-001',
        'status' => 'confirmed',
        'courier' => [
            'company' => 'jne',
            'waybill_id' => 'WAYBILL-001',
        ],
        'order_id' => 'BS-ORDER-001',
    ];

    $response = $this->postJson('/api/webhook/biteship', $payload, [
        'X-Biteship-Signature' => hash_hmac('sha256', json_encode($payload), config('services.biteship.api_key')),
    ]);

    $response->assertOk()->assertJson(['status' => 'ok']);

    Queue::assertPushed(ProcessBiteshipWebhook::class);
});

test('biteship webhook rejects missing signature', function () {
    Queue::fake();

    config(['services.biteship.mock' => false]);

    $payload = ['event' => 'order.status', 'waybill_id' => 'W-001', 'status' => 'confirmed', 'courier' => ['company' => 'jne', 'waybill_id' => 'W-001'], 'order_id' => 'BS-1'];

    $this->postJson('/api/webhook/biteship', $payload)
        ->assertStatus(401);
});

test('biteship webhook rejects invalid signature', function () {
    Queue::fake();

    config(['services.biteship.mock' => false]);

    $payload = ['event' => 'order.status', 'waybill_id' => 'W-001', 'status' => 'confirmed', 'courier' => ['company' => 'jne', 'waybill_id' => 'W-001'], 'order_id' => 'BS-1'];

    $this->postJson('/api/webhook/biteship', $payload, [
        'X-Biteship-Signature' => 'wrong-signature',
    ])->assertStatus(403);
});

// --- Webhook Job ---

test('biteship webhook job updates tracking status', function () {
    // Set waybill_id first so the job can find the shipment
    $this->shipment->update(['waybill_id' => 'TEST-WAYBILL-C']);

    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'TEST-WAYBILL-C',
        status: 'confirmed',
        courierCompany: 'jne',
        courierWaybillId: 'TEST-WAYBILL-C',
        biteshipOrderId: 'BS-ORDER-001',
    );

    $job->handle(app(TransitionOrderStatus::class));

    expect($this->shipment->fresh()->tracking_status)->toBe('confirmed');
});

test('biteship webhook picked_up transitions order to shipping', function () {
    $this->shipment->update(['waybill_id' => 'TEST-WAYBILL-2']);

    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'TEST-WAYBILL-2',
        status: 'picked_up',
        courierCompany: 'jne',
        courierWaybillId: 'TEST-WAYBILL-2',
        biteshipOrderId: 'BS-ORDER-002',
    );

    $job->handle(app(TransitionOrderStatus::class));

    expect($this->shipment->fresh()->tracking_status)->toBe('picked_up')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Shipping);
});

test('biteship webhook delivered transitions order to delivered', function () {
    // Order must be in Shipping state to transition to Delivered
    $this->order->forceFill(['status' => OrderStatus::Shipping])->save();
    $this->shipment->update(['waybill_id' => 'TEST-WAYBILL-3']);

    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'TEST-WAYBILL-3',
        status: 'delivered',
        courierCompany: 'jne',
        courierWaybillId: 'TEST-WAYBILL-3',
        biteshipOrderId: 'BS-ORDER-003',
    );

    $job->handle(app(TransitionOrderStatus::class));

    expect($this->order->fresh()->status)->toBe(OrderStatus::Delivered);
});

test('biteship webhook is idempotent', function () {
    $this->shipment->update([
        'waybill_id' => 'TEST-WAYBILL-4',
        'tracking_status' => 'delivered',
    ]);

    // Order already delivered — job should skip
    $this->order->forceFill(['status' => OrderStatus::Delivered])->save();

    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'TEST-WAYBILL-4',
        status: 'delivered',
        courierCompany: 'jne',
        courierWaybillId: 'TEST-WAYBILL-4',
        biteshipOrderId: 'BS-ORDER-004',
    );

    $job->handle(app(TransitionOrderStatus::class));

    // Status should remain unchanged (idempotent)
    expect($this->shipment->fresh()->tracking_status)->toBe('delivered');
});

test('biteship webhook logs failed notification when status is failed', function () {
    $this->shipment->update(['waybill_id' => 'TEST-WAYBILL-FAIL']);

    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'TEST-WAYBILL-FAIL',
        status: 'failed',
        courierCompany: 'jne',
        courierWaybillId: 'TEST-WAYBILL-FAIL',
        biteshipOrderId: 'BS-ORDER-FAIL',
    );

    $job->handle(app(TransitionOrderStatus::class));

    expect(NotificationLog::where('status', 'failed')->count())->toBe(1);
});

// --- BiteshipService ---

test('biteship service returns mock couriers', function () {
    $service = app(DeliveryService::class);
    $couriers = $service->getCouriers();

    expect($couriers)->toBeArray()->toHaveCount(5);
});

test('biteship service returns mock rates', function () {
    $service = app(DeliveryService::class);
    $rates = $service->getRates(
        origin: ['postal_code' => 12440],
        destination: ['postal_code' => 12950],
        items: [['name' => 'Test', 'value' => 50000, 'quantity' => 1, 'weight' => 1000]],
    );

    expect($rates)->toHaveKey('pricing')
        ->and($rates['pricing'])->toHaveCount(5);
});

test('biteship service filters rates by courier', function () {
    $service = app(DeliveryService::class);
    $rates = $service->getRates(
        origin: ['postal_code' => 12440],
        destination: ['postal_code' => 12950],
        items: [['name' => 'Test', 'value' => 50000, 'quantity' => 1, 'weight' => 1000]],
        couriers: 'jne',
    );

    expect($rates['pricing'])->toHaveCount(1)
        ->and($rates['pricing'][0]['courier_code'])->toBe('jne');
});

test('biteship service verifies webhook signature', function () {
    $service = app(DeliveryService::class);
    $payload = 'test-payload';
    $signature = hash_hmac('sha256', $payload, config('services.biteship.api_key'));

    expect($service->verifyWebhookSignature($payload, $signature))->toBeTrue();
});

test('biteship service rejects invalid webhook signature', function () {
    $service = app(DeliveryService::class);

    expect($service->verifyWebhookSignature('test', 'invalid'))->toBeFalse();
});

// --- Additional tests (Fase 4 review L1) ---

test('biteship webhook rejects non-whitelisted ip', function () {
    Queue::fake();

    config([
        'services.biteship.mock' => false,
        'services.biteship.webhook_ips' => '1.2.3.4',
    ]);

    $payload = ['event' => 'order.status', 'waybill_id' => 'W-001', 'status' => 'confirmed', 'courier' => ['company' => 'jne', 'waybill_id' => 'W-001'], 'order_id' => 'BS-1'];

    $this->postJson('/api/webhook/biteship', $payload, [
        'X-Biteship-Signature' => hash_hmac('sha256', json_encode($payload), config('services.biteship.api_key')),
    ])->assertStatus(403);
});

test('biteship webhook job skips when shipment not found', function () {
    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'NONEXISTENT',
        status: 'delivered',
        courierCompany: 'jne',
        courierWaybillId: 'NONEXISTENT',
        biteshipOrderId: 'BS-NONE',
    );

    // Should not throw — just log and return
    $job->handle(app(TransitionOrderStatus::class));

    expect(true)->toBeTrue(); // no exception = pass
});

test('biteship webhook job skips terminal order', function () {
    $this->shipment->update(['waybill_id' => 'TERMINAL-1']);

    // Set order to terminal state
    $this->order->forceFill(['status' => OrderStatus::Completed])->save();

    $job = new ProcessBiteshipWebhook(
        event: 'order.status',
        waybillId: 'TERMINAL-1',
        status: 'delivered',
        courierCompany: 'jne',
        courierWaybillId: 'TERMINAL-1',
        biteshipOrderId: 'BS-TERMINAL',
    );

    $job->handle(app(TransitionOrderStatus::class));

    // Tracking should be updated but order status unchanged (terminal)
    expect($this->shipment->fresh()->tracking_status)->toBe('delivered')
        ->and($this->order->fresh()->status)->toBe(OrderStatus::Completed);
});
