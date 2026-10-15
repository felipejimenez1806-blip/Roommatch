<?php

namespace App\Http\Middleware;

use App\Services\SesionUsuario;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege las rutas /api/admin/*. Debe ir DESPUÉS de 'auth:sanctum' en la
 * cadena de middleware (necesita $request->user() ya resuelto).
 *
 * Exige DOS cosas a la vez:
 *  1. Que la sesión actual esté activa "como administrador" (rol_activo =
 *     'admin', fijado por login directo, seleccionar-rol o cambiar-rol), no
 *     solo que el usuario tenga el rol asignado en la BD: un usuario con los
 *     dos roles puede estar navegando como cliente.
 *  2. Que el rol 'admin' siga vigente en usuario_rol — por si la sesión sigue
 *     viva pero otro admin le revocó el rol en medio de la sesión.
 *
 * Nota: con cookies de sesión, Sanctum le pone al usuario un token
 * "transitorio" para el que tokenCan() responde true a TODO. Por eso el rol
 * activo se lee de la sesión y nunca de las abilities del token.
 *
 * El panel (/admin) además está protegido por 'zona.admin' en web.php; este
 * middleware es el que protege los DATOS.
 */
class EsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if (! $usuario || SesionUsuario::rolActivo() !== 'admin' || ! $usuario->esAdmin()) {
            abort(403, 'Acceso restringido: se requiere una cuenta de administrador.');
        }

        return $next($request);
    }
}
