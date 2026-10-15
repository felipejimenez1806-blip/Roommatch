<?php

namespace App\Http\Middleware;

use App\Services\SesionUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Páginas de administración (/admin/*). Reemplaza a partials/admin-guard:
 *  - sin sesión -> /login
 *  - con sesión pero no activa como administrador -> /dashboard
 *
 * Mira el rol ACTIVO de la sesión, no solo si el usuario tiene el rol
 * admin: un usuario con ambos roles puede estar navegando como cliente.
 * No sustituye a 'es.admin', que es el que protege los datos de la API.
 * Va DESPUÉS de 'cuenta.activa'.
 */
class ZonaAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (SesionUsuario::rolActivo() !== 'admin' || ! $usuario->esAdmin()) {
            return redirect()->route('dashboard');
        }

        $respuesta = $next($request);
        $respuesta->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, private');

        return $respuesta;
    }
}
