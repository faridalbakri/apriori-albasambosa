<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders kebijakan cookie page', function () {
    $response = $this->get(route('pages.cookie'));

    $response->assertOk()
        ->assertSee('Kebijakan Cookie')
        ->assertSee('Apa Itu Cookie?');
});

it('renders kebijakan privasi page', function () {
    $response = $this->get(route('pages.privacy'));

    $response->assertOk()
        ->assertSee('Kebijakan Privasi')
        ->assertSee('UU PDP');
});

it('renders syarat dan ketentuan page', function () {
    $response = $this->get(route('pages.terms'));

    $response->assertOk()
        ->assertSee('Syarat & Ketentuan')
        ->assertSee('Penerimaan Ketentuan');
});

it('shows cookie consent banner on first visit', function () {
    $response = $this->get(route('catalog.index'));

    $response->assertOk()
        ->assertSee('cookie_consent')
        ->assertSee('Kami menggunakan cookie');
});
