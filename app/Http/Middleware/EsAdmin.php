<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege las rutas /api/admin/*. Debe ir DESPUÉS de 'auth:sanctum' en la
 * cadena de middleware (necesita $request->user() ya resuelto).
 *
 * Con el cambio a roles N:N, exige DOS cosas a la vez:
 *  1. Que el token actual traiga la ability 'rol:admin' — es decir, que el
 *     usuario haya entrado activamente "como administrador" (vía login
 *     directo, seleccionar-rol o cambiar-rol), no solo que la tenga
 *     asignada en la BD.
 *  2. Que el rol 'admin' siga vigente en usuario_rol — por si el token
 *     sigue vivo pero un admin le revocó el rol en medio de la sesión.
 *
 * Equivalente en el servidor a la verificación que hacía admin.js en el
 * cliente (usuarioActual.tipoUsuario !== "admin" -> redirect). Esa
 * verificación en el cliente se mantiene (para no mostrar el panel), pero
 * el backend es quien de verdad decide.
 */
class EsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || ! $usuario->tokenCan('rol:admin') || ! $usuario->esAdmin()) {
            abort(403, 'Acceso restringido: se requiere una cuenta de administrador.');
        }

        return $next($request);
    }
}
