<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CrearUsuario;
use App\Enums\Rol;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\GuardarUsuarioRequest;
use App\Http\Requests\Admin\RestablecerContrasenaRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/usuarios', [
            'usuarios' => User::query()
                ->withCount('ventas')
                ->orderByDesc('activo')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'usuario' => $user->usuario,
                    'email' => $user->email,
                    'rol' => $user->rol->value,
                    'rol_etiqueta' => $user->rol->etiqueta(),
                    'activo' => $user->activo,
                    'ventas_count' => $user->ventas_count,
                    'es_actual' => $user->is($request->user()),
                ]),
            'roles' => collect(Rol::cases())->map(fn (Rol $rol): array => [
                'value' => $rol->value,
                'etiqueta' => $rol->etiqueta(),
            ]),
        ]);
    }

    public function store(GuardarUsuarioRequest $request, CrearUsuario $crearUsuario): RedirectResponse
    {
        $user = $crearUsuario->handle([
            'name' => $request->string('name')->value(),
            'usuario' => $request->string('usuario')->value(),
            'rol' => Rol::from($request->string('rol')->value()),
            'email' => $request->filled('email') ? $request->string('email')->value() : null,
            'password' => $request->string('password')->value(),
        ]);

        return $this->volverConAviso("Usuario {$user->usuario} creado.");
    }

    /**
     * Activa o desactiva un usuario. Al desactivarlo se cierran sus sesiones.
     */
    public function actualizarEstado(Request $request, User $usuario): RedirectResponse
    {
        $activo = $request->validate(['activo' => ['required', 'boolean']])['activo'];

        if (! $activo && $usuario->is($request->user())) {
            throw ValidationException::withMessages([
                'activo' => 'No puedes desactivar tu propio usuario.',
            ]);
        }

        $usuario->update(['activo' => $activo]);

        if (! $activo) {
            $this->cerrarSesiones($usuario);
        }

        return $this->volverConAviso(
            $activo ? "Usuario {$usuario->usuario} activado." : "Usuario {$usuario->usuario} desactivado.",
        );
    }

    /**
     * Cambia la contraseña y cierra las demás sesiones abiertas del usuario.
     */
    public function restablecerContrasena(RestablecerContrasenaRequest $request, User $usuario): RedirectResponse
    {
        $usuario->forceFill([
            'password' => $request->validated('password'),
            'remember_token' => Str::random(60),
        ])->save();

        $this->cerrarSesiones($usuario, excepto: $request->session()->getId());

        return $this->volverConAviso("Contraseña de {$usuario->usuario} restablecida.");
    }

    private function cerrarSesiones(User $usuario, ?string $excepto = null): void
    {
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->id)
            ->when($excepto, fn ($query) => $query->where('id', '!=', $excepto))
            ->delete();
    }

    private function volverConAviso(string $mensaje): RedirectResponse
    {
        return Inertia::flash('toast', ['type' => 'success', 'message' => $mensaje])->back();
    }
}
