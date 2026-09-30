<?php

use App\Actions\CrearUsuario;
use App\Enums\Rol;
use App\Enums\TeamRole;
use Illuminate\Support\Facades\Hash;

test('crea el usuario activo con su equipo personal como equipo actual', function () {
    $user = app(CrearUsuario::class)->handle([
        'name' => 'Ana Pérez',
        'usuario' => 'A-014',
        'rol' => Rol::Asesor,
        'password' => 'clave-segura-123',
    ]);

    $user->refresh();
    $team = $user->personalTeam();

    expect($user->rol)->toBe(Rol::Asesor)
        ->and($user->activo)->toBeTrue()
        ->and($user->email)->toBeNull()
        ->and(Hash::check('clave-segura-123', $user->password))->toBeTrue()
        ->and($team)->not->toBeNull()
        ->and($user->current_team_id)->toBe($team->id)
        ->and($user->teamRole($team))->toBe(TeamRole::Owner);
});
