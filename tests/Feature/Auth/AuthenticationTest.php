<?php

use App\Http\Middleware\CerrarSesionSiInactivo;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'usuario' => $user->usuario,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('ventas.create'));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'usuario' => $user->usuario,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'usuario' => $user->usuario,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest();
});

test('users can authenticate regardless of case and surrounding spaces', function () {
    $user = User::factory()->create(['usuario' => 'A-014']);

    $this->post(route('login.store'), [
        'usuario' => ' a-014 ',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
});

test('inactive users can not authenticate', function () {
    $user = User::factory()->inactivo()->create();

    $response = $this->post(route('login.store'), [
        'usuario' => $user->usuario,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['usuario' => CerrarSesionSiInactivo::MENSAJE]);
    $this->assertGuest();
});

test('inactive users do not learn they are inactive with a wrong password', function () {
    $user = User::factory()->inactivo()->create();

    $response = $this->post(route('login.store'), [
        'usuario' => $user->usuario,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['usuario' => 'El usuario o la contraseña no son correctos.']);
    $this->assertGuest();
});

test('users deactivated during their session are logged out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('ventas.create'))->assertOk();

    $user->update(['activo' => false]);

    $this->get(route('ventas.create'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['usuario' => CerrarSesionSiInactivo::MENSAJE]);

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->usuario, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'usuario' => $user->usuario,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
