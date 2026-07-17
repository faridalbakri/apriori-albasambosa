<?php

use App\Enums\OrderStatus;
use App\Livewire\CheckoutPage;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Category::factory()->create();
    $this->product = Product::factory()->create([
        'stock' => 10,
        'stock_reserved' => 0,
        'price' => 50_000,
    ]);
});

// --- Page Load ---

test('checkout page loads for guest', function () {
    $this->get(route('checkout.index'))->assertOk();
});

test('checkout page loads for authenticated user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('checkout.index'))
        ->assertOk();
});

// --- Pickup Checkout ---

test('guest can checkout with pickup', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '14:00')
        ->set('guestName', 'Test Guest')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', true)
        ->call('checkout');

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->payment_method)->toBe('midtrans_snap')
        ->and(str_starts_with($order->order_number, 'ALBA-'.now()->format('Ymd')))->toBeTrue()
        ->and((float) $order->total_price)->toBe(100000.0) // 2 × 50000 + 0 shipping
        ->and($order->phone)->toBe('+6281234567890')
        ->and($order->pickup_time)->not->toBeNull();

    // Stock reserved
    expect((int) $this->product->fresh()->stock_reserved)->toBe(2);

    // Cart cleared
    expect(Cart::count())->toBe(0);

    // Snap token stored in session (sandbox keys configured in .env)
    expect(session()->get('snap_token_'.$order->id))->toBeString();
});

test('authenticated user can checkout with pickup', function () {
    $user = User::factory()->create();

    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1]);
    $cart->user_id = $user->id;
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::actingAs($user)
        ->test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '15:00')
        ->set('recipientPhone', '+6281234567899')
        ->call('checkout');

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and($order->user_id)->toBe($user->id)
        ->and((float) $order->total_price)->toBe(50000.0);
});

// --- Delivery Checkout ---

test('guest can checkout with delivery', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'delivery')
        ->set('recipientName', 'Budi')
        ->set('recipientPhone', '+6281234567891')
        ->set('addressDetail', 'Jl. Merdeka No.1, Jakarta')
        ->set('destinationPostalCode', '12950')
        ->set('availableRates', [
            ['courier_name' => 'JNE', 'courier_code' => 'jne', 'courier_service_name' => 'REG', 'courier_service_code' => 'reg', 'price' => 18_000, 'duration' => '1-2 hari'],
        ])
        ->set('selectedRateKey', 'jne|reg')
        ->set('guestName', 'Budi')
        ->set('guestPhone', '+6281234567891')
        ->set('pdpConsent', true)
        ->call('checkout');

    $order = Order::first();

    expect((float) $order->shipping_cost)->toBe(18000.0)
        ->and($order->recipient_name)->toBe('Budi')
        ->and($order->address_detail)->toBe('Jl. Merdeka No.1, Jakarta')
        ->and($order->postal_code)->toBe('12950')
        ->and((float) $order->total_price)->toBe(68000.0); // 50000 + 18000
});

// --- Validation ---

test('pickup requires date and time', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('guestName', 'Test')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', true)
        ->call('checkout')
        ->assertHasErrors(['pickupDate', 'pickupTime']);
});

test('guest checkout requires pdp consent', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '14:00')
        ->set('guestName', 'Test')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', false)
        ->call('checkout')
        ->assertHasErrors(['pdpConsent']);
});

// --- Stock ---

test('cannot checkout with insufficient stock', function () {
    $this->product->forceFill(['stock' => 1, 'stock_reserved' => 0])->save();

    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 5, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '14:00')
        ->set('guestName', 'Test')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', true)
        ->call('checkout');

    // Order should NOT be created
    expect(Order::count())->toBe(0)
        ->and((int) $this->product->fresh()->stock_reserved)->toBe(0);
});

// --- Order Number ---

test('order number format is correct', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '14:00')
        ->set('guestName', 'Test')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', true)
        ->call('checkout');

    $order = Order::first();

    expect($order->order_number)->toMatch('/^ALBA-\d{8}-\d{3}$/');
});

// --- Success Page ---

test('success page shows order details', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '14:00')
        ->set('guestName', 'Test')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', true)
        ->call('checkout');

    $order = Order::first();

    $this->get(route('checkout.success', $order))
        ->assertOk()
        ->assertSee($order->order_number)
        ->assertSee('Menunggu Pembayaran');
});

// --- Registered User Delivery ---

test('registered user can checkout with delivery', function () {
    $user = User::factory()->create();

    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => null]);
    $cart->user_id = $user->id;
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::actingAs($user)
        ->test(CheckoutPage::class)
        ->set('shippingMethod', 'delivery')
        ->set('recipientName', $user->name)
        ->set('recipientPhone', '+6281234567890')
        ->set('addressDetail', 'Jl. Test No. 123, Jakarta')
        ->set('destinationPostalCode', '12950')
        ->set('availableRates', [
            ['courier_name' => 'JNE', 'courier_code' => 'jne', 'courier_service_name' => 'REG', 'courier_service_code' => 'reg', 'price' => 18_000, 'duration' => '1-2 hari'],
        ])
        ->set('selectedRateKey', 'jne|reg')
        ->call('checkout');

    $order = Order::first();

    expect($order)->not->toBeNull()
        ->and($order->user_id)->toBe($user->id)
        ->and((float) $order->shipping_cost)->toBe(18000.0)
        ->and($order->address_detail)->toBe('Jl. Test No. 123, Jakarta');
});

// --- Success Page Security ---

test('registered user cannot access another users success page', function () {
    $alice = User::factory()->create();
    $bob = User::factory()->create();

    $order = Order::factory()->forUser($bob)->create(['user_id' => $bob->id]);

    $this->actingAs($alice)
        ->get(route('checkout.success', $order))
        ->assertForbidden();
});

test('registered user can access own order success page', function () {
    $user = User::factory()->create();

    $order = Order::factory()->forUser($user)->create(['user_id' => $user->id]);

    session()->put('snap_token_'.$order->id, 'test-snap-token');

    $this->actingAs($user)
        ->get(route('checkout.success', $order))
        ->assertOk()
        ->assertSee($order->order_number);
});

test('guest cannot access success page without last_order_id in session', function () {
    $order = Order::factory()->create(['user_id' => null]);

    $this->get(route('checkout.success', $order))
        ->assertRedirect(route('orders.track', ['order_number' => $order->order_number]));
});

test('guest cannot access success page with mismatched last_order_id', function () {
    $order = Order::factory()->create(['user_id' => null]);
    $otherOrder = Order::factory()->create(['user_id' => null]);

    session()->put('last_order_id', $otherOrder->id);

    $this->get(route('checkout.success', $order))
        ->assertRedirect(route('orders.track', ['order_number' => $order->order_number]));
});

test('success page preserves session for refresh', function () {
    $order = Order::factory()->create(['user_id' => null]);

    session()->put('last_order_id', $order->id);
    session()->put('snap_token_'.$order->id, 'test-snap-token-xyz');

    $this->get(route('checkout.success', $order))->assertOk();

    // Session persists so user can refresh the payment page
    expect(session()->has('last_order_id'))->toBeTrue()
        ->and(session()->has('snap_token_'.$order->id))->toBeTrue();
});

test('success page remains accessible on refresh', function () {
    $order = Order::factory()->create(['user_id' => null]);

    session()->put('last_order_id', $order->id);
    session()->put('snap_token_'.$order->id, 'test-snap-token-ghi');

    // First visit
    $this->get(route('checkout.success', $order))->assertOk();

    // Second visit (simulates refresh) — still accessible
    $this->get(route('checkout.success', $order))->assertOk();
});

// --- Empty Cart Guard ---

test('checkout dispatches error when cart is empty', function () {
    Livewire::test(CheckoutPage::class)
        ->set('shippingMethod', 'pickup')
        ->set('pickupDate', now()->addDay()->format('Y-m-d'))
        ->set('pickupTime', '14:00')
        ->set('guestName', 'Test')
        ->set('guestPhone', '+6281234567890')
        ->set('pdpConsent', true)
        ->call('checkout')
        ->assertDispatched('notify');

    expect(Order::count())->toBe(0);
});
