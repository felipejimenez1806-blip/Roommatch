<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\ServicioJavaNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Services\PaseAgendaService;
use App\Services\SesionUsuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/agenda/pase  (sesión abierta; grupo 'auth:sanctum' + 'cuenta.activa')
 *
 * Devuelve la dirección con el pase firmado que el perfil carga en el marco de "Mi agenda".
 * No guarda nada: el pase vive solo en la respuesta (60 s, un solo uso).
 */
class AgendaController extends Controller
{
    public function pase(Request $request, PaseAgendaService $pases): JsonResponse
    {
        try {
            $pase = $pases->generar($request->user(), SesionUsuario::rolActivo());
        } catch (AuthorizationException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 403);
        } catch (ServicioJavaNoDisponibleException $e) {
            return response()->json(['mensaje' => $e->getMessage()], 503);
        }

        // El pase es de un solo uso: que ningún navegador ni proxy lo guarde.
        return response()->json($pase)->header('Cache-Control', 'no-store, private');
    }
}
