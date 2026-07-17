<?php

use App\Actions\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Jobs\SendWhatsAppNotification;
use App\Models\Category;
use App\Models\NotificationLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Twilio\Rest\Client as TwilioClient;

uses(RefreshDatabase::class);

// --- Job Dispatch from TransitionOrderStatus ---

beforeEach(function () {
    Category::factory()->create();
    Queue::fake();
});

test('dispatch whatsapp job on transition to ready_pickup', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 50_000]);
    $order = Order::factory()->withStatus(OrderStatus::Processing)->create(['phone' => '08123456789']);

    // Attach order item for stock effects
    $item = $order->items()->make(['product_id' => $product->id, 'quantity' => 1]);
    $item->price = $product->price;
    $item->save();

    $action = app(TransitionOrderStatus::class);
    $action($order, OrderStatus::ReadyPickup);

    Queue::assertPushed(SendWhatsAppNotification::class, function ($job) use ($order) {
        return $job->orderId === $order->id;
    });
});

test('dispatch whatsapp job on transition to delivered', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 50_000]);
    $order = Order::factory()->withStatus(OrderStatus::Shipping)->create(['phone' => '08123456789']);

    $item = $order->items()->make(['product_id' => $product->id, 'quantity' => 1]);
    $item->price = $product->price;
    $item->save();

    $action = app(TransitionOrderStatus::class);
    $action($order, OrderStatus::Delivered);

    Queue::assertPushed(SendWhatsAppNotification::class, function ($job) use ($order) {
        return $job->orderId === $order->id;
    });
});

test('does not dispatch whatsapp job for non-notify status', function () {
    $product = Product::factory()->create(['stock' => 10, 'price' => 50_000]);
    $order = Order::factory()->withStatus(OrderStatus::Settlement)->create();

    $item = $order->items()->make(['product_id' => $product->id, 'quantity' => 1]);
    $item->price = $product->price;
    $item->save();

    $action = app(TransitionOrderStatus::class);
    $action($order, OrderStatus::Processing);

    Queue::assertNotPushed(SendWhatsAppNotification::class);
});

// --- SendWhatsAppNotification Job ---

function createNotifiableOrder(OrderStatus $status, ?string $phone = '+6281234567890'): Order
{
    $product = Product::factory()->create(['stock' => 10, 'price' => 50_000]);
    $order = Order::factory()->withStatus($status)->create(['phone' => $phone]);

    $item = $order->items()->make(['product_id' => $product->id, 'quantity' => 1]);
    $item->price = $product->price;
    $item->save();

    return $order;
}

test('sends whatsapp notification successfully for ready_pickup', function () {
    $order = createNotifiableOrder(OrderStatus::ReadyPickup);

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with(Mockery::pattern('/^whatsapp:\+62/'), Mockery::on(function ($opts) use ($order) {
            return str_contains($opts['body'], 'siap diambil')
                && str_contains($opts['body'], $order->order_number);
        }))
        ->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    $log = NotificationLog::first();
    expect($log)
        ->not->toBeNull()
        ->and($log->status)->toBe('sent')
        ->and($log->metadata['channel'])->toBe('whatsapp')
        ->and($log->metadata)->toHaveKey('channel', 'whatsapp')
        ->and($log->metadata)->toHaveKey('order_id', $order->id);
});

test('sends whatsapp notification successfully for delivered', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered);

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    $log = NotificationLog::first();
    expect($log->status)->toBe('sent');
});

test('defers failure log to failed hook when twilio throws', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = Mockery::mock();
    $mockTwilio->messages->shouldReceive('create')
        ->once()
        ->andThrow(new RuntimeException('Twilio API error'));

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);

    try {
        $job->handle();
    } catch (RuntimeException) {
        // Expected — job should re-throw for queue retry
    }

    // No log during retry — deferred to failed() hook (M5 fix)
    expect(NotificationLog::count())->toBe(0);

    // Simulate final failure after all retries exhausted
    $job->failed(new RuntimeException('Twilio API error'));

    $log = NotificationLog::first();
    expect($log)
        ->not->toBeNull()
        ->and($log->status)->toBe('failed')
        ->and($log->metadata)->toHaveKey('error');
});

test('skips notification when phone is null', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered, null);

    $job = new SendWhatsAppNotification($order->id);
    $job->handle();

    expect(NotificationLog::count())->toBe(0);
});

test('skips notification when phone is empty string', function () {
    $order = createNotifiableOrder(OrderStatus::ReadyPickup, '');

    $job = new SendWhatsAppNotification($order->id);
    $job->handle();

    expect(NotificationLog::count())->toBe(0);
});

test('skips notification for non-notify status', function () {
    $order = createNotifiableOrder(OrderStatus::Processing);

    $job = new SendWhatsAppNotification($order->id);
    $job->handle();

    expect(NotificationLog::count())->toBe(0);
});

test('skips notification when order not found', function () {
    $job = new SendWhatsAppNotification(99999);
    $job->handle();

    expect(NotificationLog::count())->toBe(0);
});

// --- Phone Normalization ---

test('normalizes 08xx to +62 format', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered, '08123456789');

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('whatsapp:+628123456789', Mockery::any())
        ->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    expect(NotificationLog::first()->status)->toBe('sent');
});

test('normalizes 62 prefix to +62 format', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered, '628123456789');

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('whatsapp:+628123456789', Mockery::any())
        ->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    expect(NotificationLog::first()->status)->toBe('sent');
});

test('normalizes phone number with non-digit characters', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered, '0812-3456-789');

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('whatsapp:+628123456789', Mockery::any())
        ->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    expect(NotificationLog::first()->status)->toBe('sent');
});

test('handles already formatted E.164 number', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered, '+628123456789');

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')
        ->once()
        ->with('whatsapp:+628123456789', Mockery::any())
        ->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    expect(NotificationLog::first()->status)->toBe('sent');
});

// --- Phone Validation ---

test('skips notification for too-short phone number', function () {
    $order = createNotifiableOrder(OrderStatus::Delivered, '12345');

    $job = new SendWhatsAppNotification($order->id);
    $job->handle();

    expect(NotificationLog::count())->toBe(0);
});

// --- Metadata includes user_id ---

test('notification log includes user_id when order belongs to user', function () {
    $user = User::factory()->create();
    $order = createNotifiableOrder(OrderStatus::Delivered);
    $order->forceFill(['user_id' => $user->id])->save();

    $mockMessages = Mockery::mock();
    $mockMessages->shouldReceive('create')->once()->andReturn(true);

    $mockTwilio = Mockery::mock(TwilioClient::class);
    $mockTwilio->messages = $mockMessages;

    $job = Mockery::mock(SendWhatsAppNotification::class, [$order->id])->makePartial()->shouldAllowMockingProtectedMethods();
    $job->shouldReceive('createTwilioClient')->andReturn($mockTwilio);
    $job->handle();

    expect(NotificationLog::first()->user_id)->toBe($user->id);
});
