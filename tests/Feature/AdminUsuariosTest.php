<?php

use App\Enums\Rol;
use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['rol' => Rol::Admin]);
});

/**
 * @param  array<string, mixed>  $cambios
 * @return array<string, mixed>
 */
function datosDeUsuario(array $cambios = []): array
{
    return [
        'name' => 'Laura Gómez',
        'usuario' => 'A-014',
        'rol' => 'asesor',
        'email' => '',
        'password' => 'clave-segura-10',
        'password_confirmation' => 'clave-segura-10',
        ...$cambios,
    ];
}

test('admins see every user with its sales count', function () {
    User::factory()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.usuarios.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/usuarios')
            ->has('usuarios', 2)
            ->has('roles', 4),
        );
});

test('admins create users with their personal team', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.usuarios.store'), datosDeUsuario())
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast.type', 'success');

    $user = User::where('usuario', 'A-014')->sole();

    expect($user->rol)->toBe(Rol::Asesor)
        ->and($user->activo)->toBeTrue()
        ->and($user->email)->toBeNull()
        ->and(Hash::check('clave-segura-10', $user->password))->toBeTrue()
        ->and($user->teamRole($user->personalTeam()))->toBe(TeamRole::Owner);
});

test('user creation is validated', function (array $cambios, string $campo) {
    User::factory()->create(['usuario' => 'A-014']);

    $this->actingAs($this->admin)
        ->post(route('admin.usuarios.store'), datosDeUsuario($cambios))
        ->assertSessionHasErrors($campo);
})->with([
    'usuario repetido con otras mayúsculas' => [['usuario' => 'a-014'], 'usuario'],
    'usuario con espacios' => [['usuario' => 'a 015'], 'usuario'],
    'contraseña corta' => [['usuario' => 'A-015', 'password' => 'corta', 'password_confirmation' => 'corta'], 'password'],
    'confirmación distinta' => [['usuario' => 'A-015', 'password_confirmation' => 'otra-clave-10'], 'password'],
    'rol inexistente' => [['usuario' => 'A-015', 'rol' => 'jefe'], 'rol'],
]);

test('admins deactivate a user and close their sessions', function () {
    $asesor = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'sesion-del-asesor',
        'user_id' => $asesor->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('admin.usuarios.estado', $asesor), ['activo' => false])
        ->assertSessionHasNoErrors();

    expect($asesor->refresh()->activo)->toBeFalse();
    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-del-asesor']);

    $this->actingAs($this->admin)
        ->patch(route('admin.usuarios.estado', $asesor), ['activo' => true]);

    expect($asesor->refresh()->activo)->toBeTrue();
});

test('admins can not deactivate themselves', function () {
    $this->actingAs($this->admin)
        ->patch(route('admin.usuarios.estado', $this->admin), ['activo' => false])
        ->assertSessionHasErrors(['activo' => 'No puedes desactivar tu propio usuario.']);

    expect($this->admin->refresh()->activo)->toBeTrue();
});

test('admins reset a password and the old sessions are closed', function () {
    $asesor = User::factory()->create();
    DB::table('sessions')->insert([
        'id' => 'sesion-vieja',
        'user_id' => $asesor->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    $this->actingAs($this->admin)
        ->put(route('admin.usuarios.contrasena', $asesor), [
            'password' => 'nueva-clave-10',
            'password_confirmation' => 'nueva-clave-10',
        ])
        ->assertSessionHasNoErrors();

    expect(Hash::check('nueva-clave-10', $asesor->refresh()->password))->toBeTrue();
    $this->assertDatabaseMissing('sessions', ['id' => 'sesion-vieja']);
});

test('only admins can manage users', function (Rol $rol) {
    $user = User::factory()->create(['rol' => $rol]);
    $otro = User::factory()->create();

    $this->actingAs($user)->get(route('admin.usuarios.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.usuarios.store'), datosDeUsuario())->assertForbidden();
    $this->actingAs($user)->patch(route('admin.usuarios.estado', $otro), ['activo' => false])->assertForbidden();
    $this->actingAs($user)->put(route('admin.usuarios.contrasena', $otro), [
        'password' => 'nueva-clave-10',
        'password_confirmation' => 'nueva-clave-10',
    ])->assertForbidden();

    expect($otro->refresh()->activo)->toBeTrue();
})->with([Rol::Asesor, Rol::Facturador, Rol::Gerente]);
