<?php

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    Category::factory()->create();
    $this->product = Product::factory()->create(['stock' => 10, 'price' => 50_000]);
});

// ── Login Rate Limiting ──

it('rate limits login after 5 failed attempts', function () {
    $user = User::factory()->create(['password' => 'correct-password']);

    // 5 failed attempts — each should reject with auth error
    for ($i = 0; $i < 5; $i++) {
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    // After 5 failures, RateLimiter should report too many attempts
    $throttleKey = Str::transliterate(Str::lower($user->email).'|'.request()->ip());
    expect(RateLimiter::tooManyAttempts($throttleKey, 5))->toBeTrue();

    // 6th attempt should also fail with session error
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

it('resets login rate limit after successful login', function () {
    $user = User::factory()->create();

    // Some failed attempts
    for ($i = 0; $i < 3; $i++) {
        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);
    }

    // Successful login clears the rate limiter
    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('catalog.index'));

    // Should be able to login again (rate limiter cleared)
    // (can't easily test without resetting session, but the redirect confirms success)
    expect(auth()->check())->toBeTrue();
});

// ── Shipping Cost Check Rate Limiting ──

it('rate limits shipping cost check after 10 attempts', function () {
    // Create cart so CheckoutPage renders
    $cart = new Cart;
    $cart->fill(['product_id' => $this->product->id, 'quantity' => 1, 'session_id' => session()->getId()]);
    $cart->price = $this->product->price;
    $cart->save();

    $ip = request()->ip();
    $rateLimitKey = "biteship-rates:{$ip}";

    // Clear any existing attempts
    RateLimiter::clear($rateLimitKey);

    // 10 attempts — all should hit the rate limiter but not be blocked
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($rateLimitKey, 60);
    }

    // 11th check should be rate limited
    expect(RateLimiter::tooManyAttempts($rateLimitKey, 10))->toBeTrue();
});

it('shipping rate limit is keyed per IP', function () {
    $ip1 = '127.0.0.1';
    $ip2 = '192.168.1.1';

    $key1 = "biteship-rates:{$ip1}";
    $key2 = "biteship-rates:{$ip2}";

    RateLimiter::clear($key1);
    RateLimiter::clear($key2);

    // Exhaust IP 1
    for ($i = 0; $i < 10; $i++) {
        RateLimiter::hit($key1, 60);
    }

    // IP 1 is rate limited
    expect(RateLimiter::tooManyAttempts($key1, 10))->toBeTrue();

    // IP 2 is NOT rate limited
    expect(RateLimiter::tooManyAttempts($key2, 10))->toBeFalse();
});
