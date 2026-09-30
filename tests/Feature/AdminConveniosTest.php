<?php

use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->admin = User::factory()->create(['rol' => Rol::Admin]);
});

test('admins see every convenio including inactive ones', function () {
    Convenio::factory()->create();
    Convenio::factory()->inactivo()->create();

    $this->actingAs($this->admin)
        ->get(route('admin.convenios.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/convenios')
            ->has('convenios', 2),
        );
});

test('admins create convenios', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.convenios.store'), ['nombre' => 'Crediexpress', 'requiere_gasto' => true])
        ->assertSessionHasNoErrors();

    expect(Convenio::where('nombre', 'Crediexpress')->sole())
        ->requiere_gasto->toBeTrue()
        ->activo->toBeTrue();
});

test('convenio names can not be repeated regardless of case', function () {
    Convenio::factory()->create(['nombre' => 'Addi']);

    $this->actingAs($this->admin)
        ->post(route('admin.convenios.store'), ['nombre' => 'ADDI'])
        ->assertSessionHasErrors(['nombre' => 'Ya existe un convenio con ese nombre.']);
});

test('admins toggle a convenio status and expense requirement', function () {
    $convenio = Convenio::factory()->create();

    $this->actingAs($this->admin)
        ->patch(route('admin.convenios.update', $convenio), ['requiere_gasto' => true])
        ->assertSessionHasNoErrors();

    $this->actingAs($this->admin)
        ->patch(route('admin.convenios.update', $convenio), ['activo' => false])
        ->assertSessionHasNoErrors();

    expect($convenio->refresh())
        ->requiere_gasto->toBeTrue()
        ->activo->toBeFalse();
});

test('only admins can manage convenios', function (Rol $rol) {
    $user = User::factory()->create(['rol' => $rol]);
    $convenio = Convenio::factory()->create();

    $this->actingAs($user)->get(route('admin.convenios.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.convenios.store'), ['nombre' => 'Nuevo'])->assertForbidden();
    $this->actingAs($user)->patch(route('admin.convenios.update', $convenio), ['activo' => false])->assertForbidden();

    expect($convenio->refresh()->activo)->toBeTrue();
})->with([Rol::Asesor, Rol::Facturador, Rol::Gerente]);
