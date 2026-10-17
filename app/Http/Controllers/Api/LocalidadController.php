<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Localidad;
use App\Services\LocalidadPopularService;
use Illuminate\Http\JsonResponse;

class LocalidadController extends Controller
{
    /**
     * GET /api/localidades/populares
     *
     * Las 6 localidades más agendadas de los últimos 30 días (completadas con
     * las por defecto). Pública: el index la usa sin sesión. index/dashboard
     * ya reciben estos mismos datos renderizados en Blade (mismo servicio);
     * el endpoint queda para otros clientes (app móvil, widgets, pruebas).
     */
    public function populares(LocalidadPopularService $servicio): JsonResponse
    {
        $data = collect($servicio->populares())->map(fn (array $l) => [
            'nombre' => $l['nombre'],
            'slug' => $l['slug'],
            'imagen' => Localidad::urlImagen($l['slug']),
            'url' => Localidad::urlHabitaciones($l['slug']),
        ])->all();

        return response()->json(['data' => $data]);
    }
}
