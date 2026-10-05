<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePerfilCompleto
{
    /**
     * Para rutas de la API (siempre llamadas por fetch(), nunca por
     * navegación de página completa) que requieran que el perfil ya
     * esté completo antes de dejar actuar al usuario — por ejemplo,
     * publicar una habitación o hacer una reserva.
     *
     * El chequeo de "¿lo mando a /onboarding?" para las páginas
     * normales vive en nav.js (ver redirigirSiPerfilIncompleto),
     * porque una navegación de página completa no manda el header
     * Authorization y este middleware no vería nada de todas formas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario && !$usuario->perfilCompleto()) {
            return response()->json([
                'mensaje' => 'Completa tu perfil antes de continuar.',
            ], 409);
        }

        return $next($request);
    }
}
