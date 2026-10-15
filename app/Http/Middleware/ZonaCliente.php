<?php

namespace App\Http\Middleware;

use App\Services\SesionUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas del sitio de cliente (todas las que usan layouts.app: públicas y
 * privadas). Reemplaza dos chequeos que antes hacía JavaScript en el navegador:
 *  - partials/admin-guard: un admin que navega como admin va a /admin.
 *  - nav.js (redirigirSiPerfilIncompleto): un usuario con el perfil
 *    incompleto va a /onboarding.
 *
 * Un visitante sin sesión pasa sin más (las páginas privadas añaden además
 * el middleware 'auth'). Va DESPUÉS de 'cuenta.activa'.
 */
class ZonaCliente
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if ($usuario) {
            if (SesionUsuario::rolActivo() === 'admin') {
                return redirect()->route('admin');
            }

            if (! $usuario->perfilCompleto()) {
                return redirect()->route('onboarding');
            }
        }

        $respuesta = $next($request);

        // La página lleva datos del usuario (window.APP): que el botón
        // "atrás" tras cerrar sesión no la muestre desde la caché.
        if ($usuario) {
            $respuesta->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');
        }

        return $respuesta;
    }
}
