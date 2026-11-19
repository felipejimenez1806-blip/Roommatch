<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege los endpoints internos que llaman otros servicios (no el navegador).
 * Exige el header X-Service-Key igual a ROOMMATCH_SERVICE_KEY (config/servicios.php).
 *
 * Si la clave no está configurada en Laravel, se rechaza TODO: nunca se acepta
 * una clave vacía. Se compara en tiempo constante (hash_equals).
 *
 * Se usa por clase en las rutas, sin alias en bootstrap/app.php:
 *   ->middleware(VerificarServiceKey::class)
 */
class VerificarServiceKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $esperada = (string) config('servicios.clave');
        $recibida = (string) $request->header('X-Service-Key', '');

        if ($esperada === '' || ! hash_equals($esperada, $recibida)) {
            return response()->json(['mensaje' => 'No autorizado.'], 401);
        }

        return $next($request);
    }
}
