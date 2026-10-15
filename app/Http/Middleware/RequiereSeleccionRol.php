<?php

namespace App\Http\Middleware;

use App\Services\SesionUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege POST /api/seleccionar-rol. Esa ruta NO va detrás de 'auth:sanctum':
 * en ese momento el usuario todavía no está autenticado, solo hay un login
 * "pendiente" en la sesión (la contraseña ya fue verificada pero falta elegir
 * con qué rol entrar; ver SesionUsuario::iniciar()). Este middleware es el
 * único gate: si no hay un login pendiente vigente, no deja pasar.
 *
 * Deja el usuario pendiente en $request->attributes['usuario_pendiente'] para
 * que AuthController::seleccionarRol() lo use.
 */
class RequiereSeleccionRol
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = SesionUsuario::pendiente($request);

        if (! $usuario) {
            return response()->json([
                'mensaje' => 'No hay un inicio de sesión pendiente. Vuelve a iniciar sesión.',
            ], 403);
        }

        $request->attributes->set('usuario_pendiente', $usuario);

        return $next($request);
    }
}
