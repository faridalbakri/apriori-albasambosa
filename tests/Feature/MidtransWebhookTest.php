<?php

use App\Enums\OrderStatus;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Fake WhatsApp jobs — Twilio SDK can't be mocked via Http::fake()
    Queue::fake([SendWhatsAppNotification::class]);

    $product = Product::factory()->create([
        'stock' => 10,
        'stock_reserved' => 3,
        'total_sold' => 0,
        'price' => 50_000,
    ]);

    $this->order = Order::factory()->create([
        'status' => OrderStatus::Pending,
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

function makePayload(Order $order, string $status, string $fraud = 'accept'): array
{
    return [
        'order_id' => $order->order_number,
        'transaction_status' => $status,
        'fraud_status' => $fraud,
        'status_code' => '200',
        'gross_amount' => '100000.00',
    ];
}

function makeSignature(array $payload): string
{
    $serverKey = config('services.midtrans.server_key');

    return hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].$serverKey);
}

// --- Signature Verification ---

test('webhook rejects request with missing signature', function () {
    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);
    unset($payload['signature_key']);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertStatus(422);
});

test('webhook rejects request with invalid signature', function () {
    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = 'invalid-hash';

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertStatus(403);
});

test('webhook rejects request with missing order_id', function () {
    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);
    unset($payload['order_id']);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertStatus(422);
});

// --- IP Whitelist ---

test('webhook rejects non-whitelisted IP in production', function () {
    Config::set('services.midtrans.is_production', true);
    Config::set('services.midtrans.mock', false); // disable mock to test IP whitelist

    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload, [
        'REMOTE_ADDR' => '1.2.3.4',
    ]);

    $response->assertStatus(403);
});

// --- Idempotency ---

test('webhook is idempotent for already processed orders', function () {
    // Move order out of Pending so the job skips it
    $this->order->forceFill(['status' => OrderStatus::Settlement])->save();

    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Settlement); // unchanged
});

// --- Status Mapping ---

test('webhook maps settlement to Settlement status', function () {

    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();

    // Process the queued job synchronously
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Settlement);
});

test('webhook maps expire to Expire status', function () {
    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response(['transaction_status' => 'expire']),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'expire']),
    ]);

    $payload = makePayload($this->order, 'expire');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Expire);
});

test('webhook maps cancel to Cancel status', function () {
    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response(['transaction_status' => 'cancel']),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'cancel']),
    ]);

    $payload = makePayload($this->order, 'cancel');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Cancel);
});

test('webhook maps deny to Failed status', function () {
    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response(['transaction_status' => 'deny']),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'deny']),
    ]);

    $payload = makePayload($this->order, 'deny');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Failed);
});

// --- Fraud Challenge ---

test('webhook treats fraud_challenge as failed', function () {
    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response([
            'transaction_status' => 'settlement',
            'fraud_status' => 'challenge',
        ]),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response([
            'transaction_status' => 'settlement',
            'fraud_status' => 'challenge',
        ]),
    ]);

    $payload = makePayload($this->order, 'settlement', 'challenge');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Failed);
});

// --- Pending Status (No Transition) ---

test('webhook does not change status on pending notification', function () {
    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response(['transaction_status' => 'pending']),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'pending']),
    ]);

    $payload = makePayload($this->order, 'pending');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Pending); // unchanged
});

// --- Refund Mapping ---

test('webhook maps refund to RefundPending status', function () {
    // Move order to a state where refund_pending is valid
    $this->order->forceFill(['status' => OrderStatus::Delivered])->save();

    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response(['transaction_status' => 'refund']),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'refund']),
    ]);

    $payload = makePayload($this->order, 'refund');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::RefundPending);
});

// --- Secondary Verification Fallback ---

test('webhook falls back to payload when secondary verification fails', function () {
    // Simulate Midtrans API being down — Http::fake rejects the call
    Http::fake([
        '*' => Http::response('Service Unavailable', 503),
    ]);

    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    // Falls back to webhook payload, which says settlement
    expect($this->order->status)->toBe(OrderStatus::Settlement);
});

// --- Stock Effects ---

test('settlement deducts stock and stock_reserved', function () {

    $payload = makePayload($this->order, 'settlement');
    $payload['signature_key'] = makeSignature($payload);

    $this->postJson('/api/webhook/midtrans', $payload);
    $this->artisan('queue:work', ['--once' => true]);

    $product = $this->order->items->first()->product->fresh();
    expect((int) $product->stock)->toBe(8)     // 10 - 2
        ->and((int) $product->stock_reserved)->toBe(1) // 3 - 2
        ->and((int) $product->total_sold)->toBe(2);    // incremented
});

test('expire releases stock_reserved only', function () {
    Http::fake([
        'https://api.midtrans.com/v2/*' => Http::response(['transaction_status' => 'expire']),
        'https://api.sandbox.midtrans.com/v2/*' => Http::response(['transaction_status' => 'expire']),
    ]);

    $payload = makePayload($this->order, 'expire');
    $payload['signature_key'] = makeSignature($payload);

    $this->postJson('/api/webhook/midtrans', $payload);
    $this->artisan('queue:work', ['--once' => true]);

    $product = $this->order->items->first()->product->fresh();
    expect((int) $product->stock)->toBe(10)       // unchanged
        ->and((int) $product->stock_reserved)->toBe(1); // 3 - 2
});

// --- Additional Status Mapping ---

test('webhook maps failure to Failed status', function () {
    $payload = makePayload($this->order, 'failure');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Failed);
});

test('webhook maps capture to Settlement status', function () {
    $payload = makePayload($this->order, 'capture');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Settlement);
});

test('webhook maps partial_refund to RefundPending status', function () {
    $this->order->forceFill(['status' => OrderStatus::Delivered])->save();

    $payload = makePayload($this->order, 'partial_refund');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::RefundPending);
});

// --- Stock Effects: Cancel & Failure from Pending ---

test('cancel releases stock_reserved only', function () {
    $payload = makePayload($this->order, 'cancel');
    $payload['signature_key'] = makeSignature($payload);

    $this->postJson('/api/webhook/midtrans', $payload);
    $this->artisan('queue:work', ['--once' => true]);

    $product = $this->order->items->first()->product->fresh();
    expect((int) $product->stock)->toBe(10)            // unchanged
        ->and((int) $product->stock_reserved)->toBe(1); // 3 - 2
});

test('failure releases stock_reserved only', function () {
    $payload = makePayload($this->order, 'failure');
    $payload['signature_key'] = makeSignature($payload);

    $this->postJson('/api/webhook/midtrans', $payload);
    $this->artisan('queue:work', ['--once' => true]);

    $product = $this->order->items->first()->product->fresh();
    expect((int) $product->stock)->toBe(10)            // unchanged
        ->and((int) $product->stock_reserved)->toBe(1); // 3 - 2
});

// --- Fraud Challenge: Remaining Status Variants ---

test('webhook treats capture with fraud_challenge as failed', function () {
    $payload = makePayload($this->order, 'capture', 'challenge');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Failed);
});

test('webhook treats deny with fraud_challenge as failed', function () {
    $payload = makePayload($this->order, 'deny', 'challenge');
    $payload['signature_key'] = makeSignature($payload);

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    $this->artisan('queue:work', ['--once' => true]);

    $this->order->refresh();
    expect($this->order->status)->toBe(OrderStatus::Failed);
});

// --- Order Not Found ---

test('webhook handles unknown order gracefully', function () {
    $payload = [
        'order_id' => 'ALBA-20260101-999',
        'transaction_status' => 'settlement',
        'fraud_status' => 'accept',
        'status_code' => '200',
        'gross_amount' => '100000.00',
        'signature_key' => makeSignature([
            'order_id' => 'ALBA-20260101-999',
            'status_code' => '200',
            'gross_amount' => '100000.00',
            'transaction_status' => 'settlement',
            'fraud_status' => 'accept',
        ]),
    ];

    $response = $this->postJson('/api/webhook/midtrans', $payload);

    $response->assertOk();
    // Job runs but order not found — no exception, just warning log
    $this->artisan('queue:work', ['--once' => true]);
    $this->assertTrue(true); // test passes if no exception thrown
});
