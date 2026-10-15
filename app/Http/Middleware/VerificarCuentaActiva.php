<?php

namespace App\Http\Middleware;

use App\Services\SesionUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Revisa en CADA petición con sesión abierta que la cuenta siga siendo
 * válida: que no la hayan bloqueado y que el rol activo siga correspondiendo
 * a un rol que el usuario tiene (ver SesionUsuario::problemaDeCuenta()).
 *
 * Va en las rutas /api protegidas (después de 'auth:sanctum') y en las
 * páginas web. Si no hay sesión, no hace nada: de eso se encarga 'auth'.
 * Es lo que hace que bloquear a un usuario surta efecto de inmediato sin
 * depender del driver de sesión.
 */
class VerificarCuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        $problema = SesionUsuario::problemaDeCuenta($request);

        if ($problema === null) {
            return $next($request);
        }

        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['mensaje' => $problema], 401);
        }

        return redirect()->route('login');
    }
}
