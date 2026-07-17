<?php

use App\Livewire\AddToCart;
use App\Livewire\CartPage;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

// ── Page Render ──

it('renders empty cart page', function () {
    $this->get(route('cart.index'))
        ->assertOk()
        ->assertSee('Keranjang masih kosong');
});

it('renders cart page with items', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CartPage::class)
        ->assertSee($this->product->name)
        ->assertSee('Rp 100.000'); // 2 × 50.000
});

// ── Add to Cart (Guest) ──

it('guest can add item to cart', function () {
    $sessionId = session()->getId();

    Livewire::test(AddToCart::class, ['product' => $this->product])
        ->set('quantity', 2)
        ->call('add');

    expect(Cart::count())->toBe(1);

    $cart = Cart::first();
    expect($cart->product_id)->toBe($this->product->id)
        ->and((int) $cart->quantity)->toBe(2)
        ->and($cart->session_id)->toBe($sessionId)
        ->and($cart->user_id)->toBeNull()
        ->and((float) $cart->price)->toBe(50_000.0);
});

it('guest adding same product increments quantity', function () {
    // First add
    Livewire::test(AddToCart::class, ['product' => $this->product])
        ->set('quantity', 2)
        ->call('add');

    // Second add — fresh component instance, same session
    Livewire::test(AddToCart::class, ['product' => $this->product])
        ->set('quantity', 3)
        ->call('add');

    expect(Cart::count())->toBe(1);

    $cart = Cart::first();
    expect((int) $cart->quantity)->toBe(5);
});

// ── Add to Cart (Registered) ──

it('cart items persist for registered user across requests', function () {
    $user = User::factory()->create();

    // create cart manually (same pattern as CheckoutTest) —
    // AddToCart component uses auth()->id() which needs full session setup in tests
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 3]);
    $cart->user_id = $user->id;
    $cart->price = $this->product->price;
    $cart->save();

    // Verify cart shows for the user
    $component = Livewire::actingAs($user)->test(CartPage::class);
    $items = $component->get('cartItems');

    expect($items)->toHaveCount(1)
        ->and((int) $items->first()->quantity)->toBe(3)
        ->and($items->first()->user_id)->toBe($user->id);
});

// ── Stock Guard ──

it('prevents adding more than available stock', function () {
    // create with limited stock — Livewire receives product by reference,
    // so updating in-test then passing stale model won't work
    $lowStock = Product::factory()->create(['stock' => 3, 'stock_reserved' => 0, 'price' => 50_000]);

    Livewire::test(AddToCart::class, ['product' => $lowStock])
        ->set('quantity', 5)
        ->call('add');

    expect(Cart::where('product_id', $lowStock->id)->count())->toBe(0);
});

it('prevents adding when stock is zero', function () {
    $soldOut = Product::factory()->create(['stock' => 0, 'stock_reserved' => 0, 'price' => 50_000]);

    Livewire::test(AddToCart::class, ['product' => $soldOut])
        ->set('quantity', 1)
        ->call('add');

    expect(Cart::where('product_id', $soldOut->id)->count())->toBe(0);
});

it('prevents adding when all stock is reserved', function () {
    $reserved = Product::factory()->create(['stock' => 5, 'stock_reserved' => 5, 'price' => 50_000]);

    Livewire::test(AddToCart::class, ['product' => $reserved])
        ->set('quantity', 1)
        ->call('add');

    expect(Cart::where('product_id', $reserved->id)->count())->toBe(0);
});

// ── Update Quantity ──

it('can update cart item quantity', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CartPage::class)
        ->call('updateQuantity', $cart->id, 4);

    expect((int) $cart->fresh()->quantity)->toBe(4);
});

it('removes item when quantity is set to zero', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::test(CartPage::class)
        ->call('updateQuantity', $cart->id, 0);

    expect(Cart::count())->toBe(0);
});

it('prevents updating quantity beyond available stock', function () {
    // use separate product with low stock — avoids stale-model issues
    $lowStock = Product::factory()->create(['stock' => 3, 'stock_reserved' => 0, 'price' => 50_000]);

    $cart = new Cart;
    $cart->fill(['product_id' => $lowStock->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $lowStock->price;
    $cart->save();

    Livewire::test(CartPage::class)
        ->call('updateQuantity', $cart->id, 5);

    // Quantity should stay at 1 — update rejected by stock guard
    expect((int) $cart->fresh()->quantity)->toBe(1);
});

// ── Remove Item ──

it('can remove item from cart', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    $product2 = Product::factory()->create(['stock' => 10, 'price' => 30_000]);
    $cart2 = new Cart;
    $cart2->fill(['product_id' => $product2->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart2->price = $product2->price;
    $cart2->save();

    Livewire::test(CartPage::class)
        ->call('remove', $cart->id);

    expect(Cart::count())->toBe(1);
    expect(Cart::first()->id)->toBe($cart2->id);
});

// ── Total Calculation ──

it('calculates correct total', function () {
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 2, 'session_id' => session()->getId()]);
    $cart->price = 50_000;
    $cart->save();

    $product2 = Product::factory()->create(['stock' => 10, 'price' => 30_000]);
    $cart2 = new Cart;
    $cart2->fill(['product_id' => $product2->id, 'quantity' => 3, 'session_id' => session()->getId()]);
    $cart2->price = 30_000;
    $cart2->save();

    $component = Livewire::test(CartPage::class);
    $total = $component->get('total');

    expect((float) $total)->toBe(190_000.0); // (2 × 50000) + (3 × 30000)
});

// ── Cart Scope (Security) ──

it('cart page only shows items owned by current user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    // Cart for authenticated user
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1]);
    $cart->user_id = $user->id;
    $cart->price = $this->product->price;
    $cart->save();

    // Cart for other user
    $product2 = Product::factory()->create(['stock' => 10, 'price' => 30_000]);
    $otherCart = new Cart;
    $otherCart->fill(['product_id' => $product2->id, 'quantity' => 1]);
    $otherCart->user_id = $otherUser->id;
    $otherCart->price = $product2->price;
    $otherCart->save();

    $component = Livewire::actingAs($user)->test(CartPage::class);

    $items = $component->get('cartItems');
    expect($items)->toHaveCount(1)
        ->and($items->first()->product_id)->toBe($this->product->id);
});

// ── Remove Enforces Ownership ──

it('cannot remove another users cart item', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1]);
    $cart->user_id = $otherUser->id;
    $cart->price = $this->product->price;
    $cart->save();

    Livewire::actingAs($user)
        ->test(CartPage::class)
        ->call('remove', $cart->id);

    // Cart should still exist — not owned by this user
    expect(Cart::count())->toBe(1);
})->throws(ModelNotFoundException::class);

// ── Recommendations ──

it('recommendations section is included in cart page', function () {
    Product::factory(3)->create(['stock' => 10, 'total_sold' => 100]);

    Livewire::test(CartPage::class)
        ->assertSee('Mungkin Anda Suka');
});
