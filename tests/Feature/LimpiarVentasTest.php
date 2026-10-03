<?php

use App\Enums\Rol;
use App\Models\Team;
use App\Models\User;
use App\Models\Venta;

beforeEach(function () {
    $this->admin = User::factory()->create(['rol' => Rol::Admin, 'usuario' => 'admin']);
    $this->asesor = User::factory()->create(['usuario' => 'A-001']);
    $this->facturador = User::factory()->create(['rol' => Rol::Facturador, 'usuario' => 'facturacion']);

    Venta::factory()->count(3)->for($this->asesor, 'asesor')->create([
        'facturada_por' => $this->facturador->id,
    ]);
});

test('it deletes every sale and every non admin user with their personal teams', function () {
    $equipoDelAsesor = $this->asesor->personalTeam();

    $this->artisan('ventas:limpiar')
        ->expectsQuestion('Escribe BORRAR para confirmar', 'BORRAR')
        ->expectsOutputToContain('Listo')
        ->assertSuccessful();

    expect(Venta::count())->toBe(0)
        ->and(User::pluck('usuario')->all())->toBe(['admin'])
        ->and(Team::withTrashed()->find($equipoDelAsesor->id))->toBeNull()
        ->and($this->admin->refresh()->personalTeam())->not->toBeNull();
});

test('the next sale starts again at number 000001', function () {
    $this->artisan('ventas:limpiar')
        ->expectsQuestion('Escribe BORRAR para confirmar', 'BORRAR')
        ->assertSuccessful();

    $nueva = Venta::factory()->for($this->admin, 'asesor')->create();

    expect($nueva->consecutivo)->toBe('000001');
});

test('the sales only mode keeps every user', function () {
    $this->artisan('ventas:limpiar', ['--solo-ventas' => true])
        ->expectsQuestion('Escribe BORRAR para confirmar', 'BORRAR')
        ->assertSuccessful();

    expect(Venta::count())->toBe(0)
        ->and(User::count())->toBe(3);
});

test('nothing is deleted without typing the confirmation word', function () {
    $this->artisan('ventas:limpiar')
        ->expectsQuestion('Escribe BORRAR para confirmar', 'si')
        ->expectsOutputToContain('Cancelado')
        ->assertSuccessful();

    expect(Venta::count())->toBe(3)
        ->and(User::count())->toBe(3);
});

test('it refuses to run when there is no administrator left', function () {
    $this->admin->update(['rol' => Rol::Gerente]);

    $this->artisan('ventas:limpiar')
        ->expectsOutputToContain('No hay ningún usuario administrador')
        ->assertFailed();

    expect(Venta::count())->toBe(3)
        ->and(User::count())->toBe(3);
});
