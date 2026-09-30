<?php

namespace App\Actions;

use App\Actions\Teams\CreateTeam;
use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CrearUsuario
{
    public function __construct(private CreateTeam $createTeam) {}

    /**
     * Crea un usuario del sistema de ventas con su equipo personal,
     * de la misma forma en que lo haría el kit de Laravel.
     *
     * @param  array{name: string, usuario: string, rol: Rol, password: string, email?: string|null}  $datos
     */
    public function handle(array $datos): User
    {
        return DB::transaction(function () use ($datos) {
            $user = User::create([
                'name' => $datos['name'],
                'usuario' => $datos['usuario'],
                'rol' => $datos['rol'],
                'email' => $datos['email'] ?? null,
                'password' => $datos['password'],
                'activo' => true,
            ]);

            $this->createTeam->handle($user, 'Equipo de '.$user->name, isPersonal: true);

            return $user;
        });
    }
}
