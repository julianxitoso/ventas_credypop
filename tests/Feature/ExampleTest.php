<?php

use App\Enums\Rol;
use App\Models\User;

test('the home address sends guests to the login page', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('the home address sends users to their home screen', function () {
    $user = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($user)->get(route('home'))->assertRedirect(route('panel'));
});

test('the removed kit screens are no longer available', function (string $url) {
    $user = User::factory()->create(['rol' => Rol::Admin]);

    $this->actingAs($user)->get($url)->assertNotFound();
})->with([
    'equipos' => '/settings/teams',
    'dashboard de ejemplo' => '/equipo-de-prueba/dashboard',
]);
