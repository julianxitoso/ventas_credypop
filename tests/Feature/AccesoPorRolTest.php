<?php

use App\Enums\Rol;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('after login each role is sent to its home screen', function (Rol $rol, string $ruta) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->post(route('login.store'), [
        'usuario' => $user->usuario,
        'password' => 'password',
    ])->assertRedirect(route($ruta));
})->with([
    'asesor' => [Rol::Asesor, 'ventas.create'],
    'bodega' => [Rol::Bodega, 'bodega'],
    'facturador' => [Rol::Facturador, 'panel'],
    'gerente' => [Rol::Gerente, 'resumen'],
    'admin' => [Rol::Admin, 'resumen'],
]);

test('authenticated users visiting the login page are sent to their home screen', function () {
    $user = User::factory()->create(['rol' => Rol::Facturador]);

    $this->actingAs($user)->get(route('login'))->assertRedirect(route('panel'));
});

test('only asesores can open the sale form', function (Rol $rol, int $estado) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)->get(route('ventas.create'))->assertStatus($estado);
})->with([
    'asesor' => [Rol::Asesor, 200],
    'bodega' => [Rol::Bodega, 403],
    'facturador' => [Rol::Facturador, 403],
    'gerente' => [Rol::Gerente, 403],
    'admin' => [Rol::Admin, 403],
]);

test('only bodega can open the bodega screen', function (Rol $rol, int $estado) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)->get(route('bodega'))->assertStatus($estado);
})->with([
    'asesor' => [Rol::Asesor, 403],
    'bodega' => [Rol::Bodega, 200],
    'facturador' => [Rol::Facturador, 403],
    'gerente' => [Rol::Gerente, 403],
    'admin' => [Rol::Admin, 403],
]);

test('only facturadores and admins can open the sales panel and its report', function (Rol $rol, int $estado) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)->get(route('panel'))->assertStatus($estado);
    $this->actingAs($user)->get(route('panel.informe'))->assertStatus($estado);
})->with([
    'asesor' => [Rol::Asesor, 403],
    'bodega' => [Rol::Bodega, 403],
    'facturador' => [Rol::Facturador, 200],
    'gerente' => [Rol::Gerente, 403],
    'admin' => [Rol::Admin, 200],
]);

test('only gerentes and admins can open the dashboard', function (Rol $rol, int $estado) {
    $user = User::factory()->create(['rol' => $rol]);

    $this->actingAs($user)->get(route('resumen'))->assertStatus($estado);
})->with([
    'asesor' => [Rol::Asesor, 403],
    'bodega' => [Rol::Bodega, 403],
    'facturador' => [Rol::Facturador, 403],
    'gerente' => [Rol::Gerente, 200],
    'admin' => [Rol::Admin, 200],
]);

test('each role receives its menu permissions', function (Rol $rol, array $permisos) {
    $user = User::factory()->create(['rol' => $rol]);

    $ninguno = ['registrarVentas' => false, 'asignarSeries' => false, 'verVentas' => false, 'facturar' => false, 'verDashboard' => false, 'administrar' => false];

    $this->actingAs($user)
        ->get($user->rutaDeInicio())
        ->assertInertia(fn (Assert $page) => $page->where('auth.permisos', [...$ninguno, ...$permisos]));
})->with([
    'asesor' => [Rol::Asesor, ['registrarVentas' => true]],
    'bodega' => [Rol::Bodega, ['asignarSeries' => true]],
    'facturador' => [Rol::Facturador, ['verVentas' => true, 'facturar' => true]],
    'gerente' => [Rol::Gerente, ['verDashboard' => true]],
    'admin' => [Rol::Admin, ['verVentas' => true, 'verDashboard' => true, 'administrar' => true]],
]);

test('guests are sent to the login page', function (string $ruta) {
    $this->get(route($ruta))->assertRedirect(route('login'));
})->with(['ventas.create', 'bodega', 'panel', 'resumen']);
