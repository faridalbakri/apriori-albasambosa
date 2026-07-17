<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('make:admin creates admin with correct role', function () {
    $this->artisan('make:admin', [
        '--name' => 'Admin Baru',
        '--email' => 'baru@albasambosa.com',
        '--password' => 'password123',
    ])->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'name' => 'Admin Baru',
        'email' => 'baru@albasambosa.com',
        'role' => 'admin',
    ]);

    expect(User::where('email', 'baru@albasambosa.com')->first()->email_verified_at)->not->toBeNull();
});

test('make:admin rejects duplicate email', function () {
    User::factory()->create(['email' => 'ada@albasambosa.com']);

    $this->artisan('make:admin', [
        '--name' => 'Admin Baru',
        '--email' => 'ada@albasambosa.com',
        '--password' => 'password123',
    ])->assertFailed();
});

test('make:admin rejects invalid email format', function () {
    $this->artisan('make:admin', [
        '--name' => 'Admin Baru',
        '--email' => 'bukan-email',
        '--password' => 'password123',
    ])->assertFailed();
});

test('make:admin rejects short password', function () {
    $this->artisan('make:admin', [
        '--name' => 'Admin Baru',
        '--email' => 'baru@albasambosa.com',
        '--password' => '1234567',
    ])->assertFailed();
});
