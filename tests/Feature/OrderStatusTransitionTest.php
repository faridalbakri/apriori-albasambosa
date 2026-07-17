<?php

use App\Actions\TransitionOrderStatus;
use App\Enums\OrderStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    $this->product = Product::factory()->create([
        'stock' => 10,
        'stock_reserved' => 3,
        'price' => 50_000,
    ]);

    $this->order = Order::factory()->create([
        'status' => OrderStatus::Pending,
    ]);

    // Attach an order item so stock effects have something to operate on
    // price is a protected field — set it explicitly after creation
    $item = $this->order->items()->make([
        'product_id' => $this->product->id,
        'quantity' => 2,
    ]);
    $item->price = 50_000;
    $item->save();

    $this->action = app(TransitionOrderStatus::class);
    $this->admin = User::factory()->create(['role' => 'admin']);
});

// --- Valid Transitions ---

test('pending can transition to settlement', function () {
    $result = ($this->action)($this->order, OrderStatus::Settlement, $this->admin);

    expect($result->status)->toBe(OrderStatus::Settlement);

    // Stock: physical stock decreased, reserved released
    expect((int) $this->product->fresh()->stock)->toBe(8)
        ->and((int) $this->product->fresh()->stock_reserved)->toBe(1);
});

test('pending can transition to expire', function () {
    $result = ($this->action)($this->order, OrderStatus::Expire);

    expect($result->status)->toBe(OrderStatus::Expire);

    // Stock: reserved released
    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

test('pending can transition to cancel', function () {
    $result = ($this->action)($this->order, OrderStatus::Cancel);

    expect($result->status)->toBe(OrderStatus::Cancel);

    $product = $this->product->fresh();
    expect((int) $product->stock_reserved)->toBe(1);
});

test('pending can transition to failed', function () {
    $result = ($this->action)($this->order, OrderStatus::Failed);

    expect($result->status)->toBe(OrderStatus::Failed);
});

test('settlement can transition to processing', function () {
    $this->order->forceFill(['status' => OrderStatus::Settlement])->save();

    $result = ($this->action)($this->order, OrderStatus::Processing, $this->admin);

    expect($result->status)->toBe(OrderStatus::Processing);
});

test('processing can transition to ready_pickup', function () {
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();

    $result = ($this->action)($this->order, OrderStatus::ReadyPickup, $this->admin);

    expect($result->status)->toBe(OrderStatus::ReadyPickup);
});

test('processing can transition to shipping', function () {
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();

    $result = ($this->action)($this->order, OrderStatus::Shipping, $this->admin);

    expect($result->status)->toBe(OrderStatus::Shipping);
});

test('delivered can transition to refund_pending', function () {
    $this->order->forceFill(['status' => OrderStatus::Delivered])->save();

    $result = ($this->action)($this->order, OrderStatus::RefundPending, $this->admin);

    expect($result->status)->toBe(OrderStatus::RefundPending);
});

test('refund_pending can transition to refund_done', function () {
    $this->order->forceFill(['status' => OrderStatus::RefundPending])->save();

    $result = ($this->action)($this->order, OrderStatus::RefundDone, $this->admin);

    expect($result->status)->toBe(OrderStatus::RefundDone);
});

// --- Invalid Transitions ---

test('cannot transition from completed', function () {
    $this->order->forceFill(['status' => OrderStatus::Completed])->save();

    ($this->action)($this->order, OrderStatus::Processing, $this->admin);
})->throws(InvalidStatusTransitionException::class);

test('cannot transition from pending to processing directly', function () {
    ($this->action)($this->order, OrderStatus::Processing, $this->admin);
})->throws(InvalidStatusTransitionException::class);

test('cannot transition from settlement to completed', function () {
    $this->order->forceFill(['status' => OrderStatus::Settlement])->save();

    ($this->action)($this->order, OrderStatus::Completed, $this->admin);
})->throws(InvalidStatusTransitionException::class);

// --- Audit Trail ---

test('transition creates order status log', function () {
    ($this->action)($this->order, OrderStatus::Settlement, $this->admin);

    $log = $this->order->statusLogs()->latest('created_at')->first();

    expect($log)->not->toBeNull()
        ->and($log->old_status)->toBe('pending')
        ->and($log->new_status)->toBe('settlement')
        ->and($log->user_id)->toBe($this->admin->id);
});

test('transition without actor sets user_id to null', function () {
    ($this->action)($this->order, OrderStatus::Expire);

    $log = $this->order->statusLogs()->latest('created_at')->first();

    expect($log->user_id)->toBeNull();
});

// --- Stock Effects Edge Cases ---

// --- New transitions (Fase 3 review fix) ---

test('processing can transition to failed', function () {
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();

    $result = ($this->action)($this->order, OrderStatus::Failed, $this->admin);

    expect($result->status)->toBe(OrderStatus::Failed);
});

test('processing can transition to cancel', function () {
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();

    $result = ($this->action)($this->order, OrderStatus::Cancel, $this->admin);

    expect($result->status)->toBe(OrderStatus::Cancel);
});

test('ready_pickup can transition to failed', function () {
    $this->order->forceFill(['status' => OrderStatus::ReadyPickup])->save();

    $result = ($this->action)($this->order, OrderStatus::Failed, $this->admin);

    expect($result->status)->toBe(OrderStatus::Failed);
});

test('settlement does not affect stock twice on repeated items', function () {
    // Create another product and order item
    $product2 = Product::factory()->create(['stock' => 5, 'stock_reserved' => 1, 'price' => 30_000]);
    $item2 = $this->order->items()->make([
        'product_id' => $product2->id,
        'quantity' => 1,
    ]);
    $item2->price = 30_000;
    $item2->save();

    ($this->action)($this->order, OrderStatus::Settlement);

    // First product: stock 10-2=8, reserved 3-2=1
    expect((int) $this->product->fresh()->stock)->toBe(8)
        ->and((int) $this->product->fresh()->stock_reserved)->toBe(1);

    // Second product: stock 5-1=4, reserved 1-1=0
    expect((int) $product2->fresh()->stock)->toBe(4)
        ->and((int) $product2->fresh()->stock_reserved)->toBe(0);
});

// --- Post-Deduction Cancel/Fail Stock Restoration ---

test('processing to cancel restores stock after deduction', function () {
    // Simulate: pending → settlement (stock deducted) → processing → cancel
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();

    ($this->action)($this->order, OrderStatus::Cancel, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancel);

    // Stock restored: physical back to 10, reserved stays at 1 (unchanged after settlement deduction)
    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

test('processing to failed restores stock after deduction', function () {
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();

    ($this->action)($this->order, OrderStatus::Failed, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Failed);

    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

test('pending to cancel releases reserved only (pre-deduction)', function () {
    ($this->action)($this->order, OrderStatus::Cancel);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancel);

    // Pre-deduction cancel: stock unchanged, reserved released
    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

// --- Remaining Valid Transitions ---

test('ready_pickup can transition to completed', function () {
    // pending → settlement → processing → ready_pickup
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();
    $this->order->forceFill(['status' => OrderStatus::ReadyPickup])->save();

    ($this->action)($this->order, OrderStatus::Completed, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('shipping can transition to delivered', function () {
    // pending → settlement → processing → shipping
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();
    $this->order->forceFill(['status' => OrderStatus::Shipping])->save();

    ($this->action)($this->order, OrderStatus::Delivered, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Delivered);
});

test('shipping to cancel restores stock after deduction', function () {
    // pending → settlement (deduct) → processing → shipping → cancel (restore)
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();
    $this->order->forceFill(['status' => OrderStatus::Shipping])->save();

    ($this->action)($this->order, OrderStatus::Cancel, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Cancel);

    // Stock restored: physical back to 10, reserved stays at 1 (unchanged after settlement deduction)
    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

test('shipping to failed restores stock after deduction', function () {
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();
    $this->order->forceFill(['status' => OrderStatus::Shipping])->save();

    ($this->action)($this->order, OrderStatus::Failed, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Failed);

    $product = $this->product->fresh();
    expect((int) $product->stock)->toBe(10)
        ->and((int) $product->stock_reserved)->toBe(1);
});

test('delivered can transition to completed', function () {
    ($this->action)($this->order, OrderStatus::Settlement);
    $this->order->forceFill(['status' => OrderStatus::Processing])->save();
    $this->order->forceFill(['status' => OrderStatus::Shipping])->save();
    $this->order->forceFill(['status' => OrderStatus::Delivered])->save();

    ($this->action)($this->order, OrderStatus::Completed, $this->admin);

    expect($this->order->fresh()->status)->toBe(OrderStatus::Completed);
});

test('cancel can transition to refund_pending', function () {
    // Cancel from pending releases reserved
    $this->order = ($this->action)($this->order, OrderStatus::Cancel, $this->admin);

    $this->order = ($this->action)($this->order, OrderStatus::RefundPending, $this->admin);

    expect($this->order->status)->toBe(OrderStatus::RefundPending);
});
