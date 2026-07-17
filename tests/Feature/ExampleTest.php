<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('home redirects to catalog', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('catalog.index'));
});

test('catalog page loads successfully', function () {
    $this->seed();

    $response = $this->get(route('catalog.index'));

    $response->assertStatus(200);
});
