<?php

use App\Enums\AnonymizationActionType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\AnonymizationLog;
use App\Models\Cart;
use App\Models\Order;
use App\Models\User;
use App\Services\AnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AnonymizationService;
});

test('canAnonymizeUser returns true for user past retention with no active orders', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subMonths(37)->subDays(8),
        'anonymized_at' => null,
    ]);

    Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Completed,
        'created_at' => now()->subMonths(40),
    ]);

    expect($this->service->canAnonymizeUser($user, 36))->toBeTrue();
});

test('canAnonymizeUser returns false for user with active pending order', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subMonths(40),
        'anonymized_at' => null,
    ]);

    Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);

    expect($this->service->canAnonymizeUser($user, 36))->toBeFalse();
});

test('canAnonymizeUser returns false for already anonymized user', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subMonths(40),
        'anonymized_at' => now()->subDay(),
    ]);

    expect($this->service->canAnonymizeUser($user, 36))->toBeFalse();
});

test('canAnonymizeUser returns false within grace period', function () {
    $user = User::factory()->create([
        'last_login_at' => now()->subMonths(36)->subDays(3),
        'anonymized_at' => null,
    ]);

    expect($this->service->canAnonymizeUser($user, 36))->toBeFalse();
});

test('anonymizeUser scrambles user identity data', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'old-password',
        'remember_token' => 'old-token',
        'anonymized_at' => null,
    ]);

    $this->service->anonymizeUser($user, AnonymizationActionType::AutoAnonymizeRegistered);

    $user->refresh();

    expect($user->name)->toBe('Anonymized User');
    expect($user->email)->toBe("anon_{$user->id}@deleted.local");
    expect($user->password)->not->toBe('old-password');
    expect($user->remember_token)->toBeNull();
    expect($user->anonymized_at)->not->toBeNull();
});

test('anonymizeUser inserts anonymization log', function () {
    $user = User::factory()->create(['anonymized_at' => null]);

    $this->service->anonymizeUser($user, AnonymizationActionType::AutoAnonymizeRegistered);

    $this->assertDatabaseHas('anonymization_logs', [
        'user_id' => $user->id,
        'action_type' => 'auto_anonymize_registered',
    ]);

    $log = AnonymizationLog::where('user_id', $user->id)->first();
    expect($log->anonymized_fields)->toBeArray();
    expect($log->anonymized_fields)->toContain('name', 'email', 'password');
});

test('anonymizeUser clears user carts', function () {
    $user = User::factory()->create(['anonymized_at' => null]);
    Cart::factory()->create(['user_id' => $user->id]);

    $this->service->anonymizeUser($user, AnonymizationActionType::AutoAnonymizeRegistered);

    $this->assertDatabaseMissing('carts', ['user_id' => $user->id]);
});

test('anonymizeUser anonymizes user addresses', function () {
    $user = User::factory()->create(['anonymized_at' => null]);
    $address = Address::factory()->create([
        'user_id' => $user->id,
        'recipient_name' => 'Real Name',
        'phone' => '+6281234567890',
    ]);

    $this->service->anonymizeUser($user, AnonymizationActionType::AutoAnonymizeRegistered);

    $address->refresh();
    expect($address->recipient_name)->toBe('Anonymized');
    expect($address->phone)->toBeNull();
});
