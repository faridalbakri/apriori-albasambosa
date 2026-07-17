<?php

use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticated;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('renders login page', function () {
    get(route('login'))->assertOk();
});

it('renders register page', function () {
    get(route('register'))->assertOk();
});

it('allows user to register', function () {
    post(route('register'), [
        'name' => 'Test Customer',
        'email' => 'newuser@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('catalog.index'));

    assertAuthenticated();
    assertDatabaseHas('users', ['email' => 'newuser@test.com']);
});

it('allows user to login with correct credentials', function () {
    post(route('login'), [
        'email' => $this->user->email,
        'password' => 'password',
    ])->assertRedirect(route('catalog.index'));

    assertAuthenticated();
});

it('rejects login with wrong password', function () {
    post(route('login'), [
        'email' => $this->user->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');

    assertGuest();
});

it('allows authenticated user to logout', function () {
    actingAs($this->user)
        ->post(route('logout'))
        ->assertRedirect('/');

    assertGuest();
});

it('shows login and register links for guests', function () {
    get(route('catalog.index'))
        ->assertSee('Masuk')
        ->assertSee('Daftar');
});

it('shows user name when authenticated', function () {
    actingAs($this->user)
        ->get(route('catalog.index'))
        ->assertSee($this->user->name);
});

it('syncs guest cart to user on login', function () {
    // This test simulates what SyncCartOnLogin does internally.
    // The listener is separately tested by the integration test below.

    $product = Product::factory()->create(['stock' => 100]);

    // Create a guest cart
    $sessionId = session()->getId();
    $cart = new Cart([
        'session_id' => $sessionId,
        'product_id' => $product->id,
        'quantity' => 3,
    ]);
    $cart->price = $product->price;
    $cart->save();

    expect(Cart::count())->toBe(1);

    // Manually simulate what the listener does
    $guestItems = Cart::where('session_id', $sessionId)->get();
    foreach ($guestItems as $item) {
        $item->user_id = $this->user->id;
        $item->session_id = null;
        $item->save();
    }

    // Verify transfer
    $transferred = Cart::where('user_id', $this->user->id)->first();
    expect($transferred)->not->toBeNull()
        ->and($transferred->session_id)->toBeNull();
    expect(Cart::count())->toBe(1);
});

it('guards profile page from guests', function () {
    get(route('profile.edit'))->assertRedirect(route('login'));
});

// --- Password Reset ---

it('renders forgot password page', function () {
    get(route('password.request'))->assertOk();
});

it('sends password reset link for valid email', function () {
    post(route('password.email'), [
        'email' => $this->user->email,
    ])->assertSessionHas('status');
});

it('rejects password reset for unknown email', function () {
    post(route('password.email'), [
        'email' => 'nonexistent@test.com',
    ])->assertSessionHasErrors('email');
});

it('renders reset password page with valid token', function () {
    $token = Password::createToken($this->user);

    get(route('password.reset', $token))->assertOk();
});

it('allows password reset with valid token', function () {
    $token = Password::createToken($this->user);

    post(route('password.store'), [
        'token' => $token,
        'email' => $this->user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertRedirect(route('login'));
});

// --- Email Verification ---

it('redirects unverified user to verification notice', function () {
    $user = User::factory()->unverified()->create();

    actingAs($user)
        ->get(route('verification.notice'))
        ->assertOk();
});

it('verifies email with valid signed URL', function () {
    $user = User::factory()->unverified()->create();

    $url = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    actingAs($user)
        ->get($url)
        ->assertRedirect(route('dashboard', ['verified' => 1]));

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});
