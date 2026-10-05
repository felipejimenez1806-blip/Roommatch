<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege POST /api/seleccionar-rol. El token limitado que emite login()
 * cuando el usuario tiene más de un rol solo trae la ability
 * 'seleccionar-rol' (nunca 'rol:cliente' ni 'rol:admin'), así que este
 * middleware es el único gate que deja pasar hacia ese endpoint — un
 * token real de sesión (rol:cliente / rol:admin) no debería poder
 * volver a llamarlo.
 *
 * Va DESPUÉS de 'auth:sanctum' en la cadena (necesita $request->user()
 * ya resuelto). Mismo patrón que EsAdmin.
 */
class RequiereSeleccionRol
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->tokenCan('seleccionar-rol')) {
            abort(403, 'Este token no requiere selección de rol.');
        }

        return $next($request);
    }
}
