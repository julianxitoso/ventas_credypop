<?php

namespace Database\Seeders;

use App\Actions\CrearUsuario;
use App\Enums\Rol;
use App\Models\Convenio;
use App\Models\User;
use Illuminate\Database\Seeder;

class InicialSeeder extends Seeder
{
    /**
     * Convenios iniciales y si requieren gasto administrativo.
     *
     * @var array<string, bool>
     */
    private const CONVENIOS = [
        'Contado' => false,
        'Addi' => false,
        'Alcanos' => false,
        'Brilla' => false,
        'Krediya' => true,
        'Sistecredito' => false,
    ];

    private const LONGITUD_MINIMA_CLAVE = 10;

    /**
     * Carga los convenios iniciales y el usuario administrador.
     * Se puede ejecutar varias veces sin duplicar datos.
     */
    public function run(CrearUsuario $crearUsuario): void
    {
        foreach (self::CONVENIOS as $nombre => $requiereGasto) {
            Convenio::firstOrCreate(['nombre' => $nombre], ['requiere_gasto' => $requiereGasto]);
        }

        $this->command->info('Convenios iniciales listos.');

        if (User::where('usuario', 'admin')->exists()) {
            $this->command->warn('El usuario "admin" ya existe; no se modificó.');

            return;
        }

        $crearUsuario->handle([
            'name' => 'Administrador',
            'usuario' => 'admin',
            'rol' => Rol::Admin,
            'password' => $this->pedirClave(),
        ]);

        $this->command->info('Usuario "admin" creado.');
    }

    /**
     * Pide la contraseña del administrador (oculta y confirmada).
     */
    private function pedirClave(): string
    {
        while (true) {
            $clave = (string) $this->command->secret('Contraseña para el usuario "admin" (mínimo '.self::LONGITUD_MINIMA_CLAVE.' caracteres)');

            if (mb_strlen($clave) < self::LONGITUD_MINIMA_CLAVE) {
                $this->command->error('La contraseña debe tener al menos '.self::LONGITUD_MINIMA_CLAVE.' caracteres.');

                continue;
            }

            if ($clave !== (string) $this->command->secret('Repite la contraseña')) {
                $this->command->error('Las contraseñas no coinciden.');

                continue;
            }

            return $clave;
        }
    }
}
