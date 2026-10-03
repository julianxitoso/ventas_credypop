<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Signature('ventas:limpiar {--solo-ventas : Borra solo las ventas y conserva todos los usuarios}')]
#[Description('Borra los datos de prueba: todas las ventas y los usuarios que no son administradores')]
class LimpiarVentas extends Command
{
    private const CONFIRMACION = 'BORRAR';

    /**
     * Borra las ventas (y, salvo --solo-ventas, los usuarios no administradores)
     * dentro de una transacción, y reinicia el consecutivo de ventas.
     */
    public function handle(): int
    {
        $soloVentas = (bool) $this->option('solo-ventas');
        $usuarios = $soloVentas ? collect() : User::query()->where('rol', '!=', Rol::Admin)->get(['id', 'usuario']);

        if (! $soloVentas && ! User::query()->where('rol', Rol::Admin)->exists()) {
            $this->error('No hay ningún usuario administrador. No se borra nada para no dejar el sistema sin acceso.');

            return self::FAILURE;
        }

        $this->warn('Se va a borrar de forma permanente:');
        $this->line('  · '.Venta::query()->count().' ventas (el consecutivo vuelve a VENTA #000001)');

        if (! $soloVentas) {
            $this->line('  · '.$usuarios->count().' usuarios que no son administradores: '.$usuarios->pluck('usuario')->join(', '));
            $this->line('Se conservan los usuarios administradores y los convenios.');
        } else {
            $this->line('Se conservan todos los usuarios y los convenios.');
        }

        $respuesta = $this->ask('Escribe '.self::CONFIRMACION.' para confirmar');

        if ($respuesta !== self::CONFIRMACION) {
            $this->info('Cancelado. No se borró nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($usuarios): void {
            Venta::query()->delete();

            if ($usuarios->isNotEmpty()) {
                $this->borrarUsuarios($usuarios->pluck('id'));
            }
        });

        $this->reiniciarConsecutivo();

        $this->info('Listo. Datos de prueba borrados.');

        return self::SUCCESS;
    }

    /**
     * Borra los usuarios con sus equipos personales y sus sesiones.
     *
     * @param  Collection<int, int>  $ids
     */
    private function borrarUsuarios(Collection $ids): void
    {
        $equiposPersonales = DB::table('team_members')
            ->join('teams', 'teams.id', '=', 'team_members.team_id')
            ->whereIn('team_members.user_id', $ids)
            ->where('teams.is_personal', true)
            ->pluck('teams.id');

        DB::table('sessions')->whereIn('user_id', $ids)->delete();
        DB::table('teams')->whereIn('id', $equiposPersonales)->delete();
        DB::table('users')->whereIn('id', $ids)->delete();
    }

    /**
     * Hace que la próxima venta vuelva a ser la número 1.
     */
    private function reiniciarConsecutivo(): void
    {
        match (DB::getDriverName()) {
            'mysql', 'mariadb' => DB::statement('ALTER TABLE ventas AUTO_INCREMENT = 1'),
            'sqlite' => DB::table('sqlite_sequence')->where('name', 'ventas')->delete(),
            'pgsql' => DB::statement('ALTER SEQUENCE ventas_id_seq RESTART WITH 1'),
            default => null,
        };
    }
}
