<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Favorito;
use Illuminate\Http\Request;

class FavoritoController extends Controller
{
    /**
     * GET /api/mis-favoritos
     * Solo IDs de publicaciones favoritas (para pintar los corazones
     * ♥/♡ en dashboard.js / index.js).
     */
    public function index(Request $request)
    {
        $ids = $request->user()
            ->favoritos()
            ->where('tipo', 'publicacion')
            ->pluck('id_publicacion');

        return response()->json(['idsPublicaciones' => $ids]);
    }

    /**
     * GET /api/mis-favoritos/habitaciones
     * Detalle completo (tarjetas) para el panel "Favoritos" de perfil.js.
     */
    public function misHabitaciones(Request $request)
    {
        $favoritos = $request->user()->favoritos()
            ->where('tipo', 'publicacion')
            ->with('publicacion')
            ->get()
            ->filter(fn (Favorito $f) => $f->publicacion)
            ->map(fn (Favorito $f) => [
                'id' => $f->publicacion->id_publicacion,
                'tipo' => $f->publicacion->tipo_espacio,
                'zona' => $f->publicacion->zona,
                'precio' => (float) $f->publicacion->precio,
                'img' => $f->publicacion->img,
            ])
            ->values();

        return response()->json(['favoritos' => $favoritos]);
    }

    /**
     * POST /api/publicaciones/{id}/favorito
     * Alterna (agrega/quita) una publicación como favorita.
     */
    public function toggle(Request $request, int $idPublicacion)
    {
        $usuario = $request->user();

        $favorito = Favorito::where('id_usuario', $usuario->id_usuario)
            ->where('tipo', 'publicacion')
            ->where('id_publicacion', $idPublicacion)
            ->first();

        if ($favorito) {
            $favorito->delete();
            return response()->json(['favorito' => false]);
        }

        Favorito::create([
            'id_usuario' => $usuario->id_usuario,
            'tipo' => 'publicacion',
            'id_publicacion' => $idPublicacion,
        ]);

        return response()->json(['favorito' => true]);
    }

    /**
     * GET /api/mis-favoritos/roomies
     * Detalle completo (tarjetas) para la pestaña "Roomies" del panel
     * Favoritos de perfil.js. Mismo shape/patrón que misHabitaciones().
     */
    public function misPersonas(Request $request)
    {
        $favoritos = $request->user()->favoritos()
            ->where('tipo', 'persona')
            ->with('persona.usuario')
            ->get()
            ->filter(fn (Favorito $f) => $f->persona)
            ->map(fn (Favorito $f) => [
                'id' => $f->persona->id_persona,
                'nombre' => $f->persona->usuario->nombre_completo ?? 'Usuario',
                'zona' => $f->persona->zona,
                'ciudad' => $f->persona->ciudad,
                'presupuesto' => (float) $f->persona->presupuesto,
                'img' => $f->persona->img,
            ])
            ->values();

        return response()->json(['favoritos' => $favoritos]);
    }

    /**
     * POST /api/roomies/{idPersona}/favorito
     * Alterna (agrega/quita) un perfil de roomie como favorito.
     */
    public function togglePersona(Request $request, int $idPersona)
    {
        $usuario = $request->user();

        $favorito = Favorito::where('id_usuario', $usuario->id_usuario)
            ->where('tipo', 'persona')
            ->where('id_persona', $idPersona)
            ->first();

        if ($favorito) {
            $favorito->delete();
            return response()->json(['favorito' => false]);
        }

        Favorito::create([
            'id_usuario' => $usuario->id_usuario,
            'tipo' => 'persona',
            'id_persona' => $idPersona,
        ]);

        return response()->json(['favorito' => true]);
    }
}
