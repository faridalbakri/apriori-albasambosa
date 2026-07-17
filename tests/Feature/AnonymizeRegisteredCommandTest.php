<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::forever('retention.registered_months', 36);
});

test('dry run displays qualifying users without modifying data', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subMonths(40),
        'last_login_at' => now()->subMonths(37)->subDays(8),
        'anonymized_at' => null,
    ]);

    $this->artisan('privacy:anonymize-registered --dry-run')
        ->expectsOutputToContain($user->email)
        ->assertExitCode(0);

    $user->refresh();
    expect($user->anonymized_at)->toBeNull();
});

test('execution anonymizes qualifying registered users', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subMonths(40),
        'last_login_at' => now()->subMonths(37)->subDays(8),
        'anonymized_at' => null,
    ]);

    $this->artisan('privacy:anonymize-registered')->assertExitCode(0);

    $user->refresh();
    expect($user->anonymized_at)->not->toBeNull();
    expect($user->name)->toBe('Anonymized User');
});

test('skips admin users', function () {
    $admin = User::factory()->create([
        'role' => 'admin',
        'email_verified_at' => now()->subMonths(50),
        'last_login_at' => now()->subMonths(50),
        'anonymized_at' => null,
    ]);

    $this->artisan('privacy:anonymize-registered');

    $admin->refresh();
    expect($admin->anonymized_at)->toBeNull();
});

test('skips already anonymized users', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subMonths(50),
        'last_login_at' => now()->subMonths(50),
        'anonymized_at' => now()->subMonth(),
    ]);

    $this->artisan('privacy:anonymize-registered');

    $user->refresh();
    expect($user->anonymized_at)->not->toBeNull();
});

test('uses created_at fallback when last_login_at is null', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subMonths(50),
        'last_login_at' => null,
        'created_at' => now()->subMonths(40),
        'anonymized_at' => null,
    ]);

    $this->artisan('privacy:anonymize-registered');

    $user->refresh();
    expect($user->anonymized_at)->not->toBeNull();
});

test('skips users with active orders even if past retention', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subMonths(50),
        'last_login_at' => now()->subMonths(50),
        'anonymized_at' => null,
    ]);

    Order::factory()->create([
        'user_id' => $user->id,
        'status' => OrderStatus::Pending,
    ]);

    $this->artisan('privacy:anonymize-registered');

    $user->refresh();
    expect($user->anonymized_at)->toBeNull();
});

test('inserts anonymization log with correct action type', function () {
    $user = User::factory()->create([
        'email_verified_at' => now()->subMonths(40),
        'last_login_at' => now()->subMonths(37)->subDays(8),
        'anonymized_at' => null,
    ]);

    $this->artisan('privacy:anonymize-registered');

    $this->assertDatabaseHas('anonymization_logs', [
        'user_id' => $user->id,
        'action_type' => 'auto_anonymize_registered',
    ]);
});
