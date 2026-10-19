<?php

namespace App\Http\Middleware;

use App\Services\SesionUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas del panel de moderación (/moderador). Hermano de ZonaAdmin:
 *  - sin sesión -> /login
 *  - con sesión pero no activa como moderador -> a la zona que le corresponde
 *    (/admin si está como admin, /dashboard si está como cliente)
 *
 * Mira el rol ACTIVO de la sesión, no solo si el usuario tiene el rol
 * asignado. No sustituye al middleware `can:` de la API, que es el que
 * protege los DATOS. Va DESPUÉS de 'cuenta.activa'.
 */
class ZonaModerador
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (SesionUsuario::rolActivo() !== 'moderador' || ! $usuario->esModerador()) {
            return redirect()->route(SesionUsuario::rolActivo() === 'admin' ? 'admin' : 'dashboard');
        }

        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');

        return $respuesta;
    }
}
