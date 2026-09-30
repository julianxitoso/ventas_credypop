<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CerrarSesionSiInactivo
{
    public const MENSAJE = 'Tu usuario está desactivado. Comunícate con el administrador.';

    /**
     * Cierra la sesión de un usuario que fue desactivado mientras estaba conectado.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->activo) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['usuario' => self::MENSAJE]);
        }

        return $next($request);
    }
}
