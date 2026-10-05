<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\Publicacion;
use Illuminate\Http\Request;

class PublicacionController extends Controller
{
    /**
     * GET /api/admin/publicaciones?q=...&estado=activas|eliminadas|todas
     *
     * 'estado' controla cómo se aplica (o no) el scope de SoftDeletes:
     *   - activas   (default): comportamiento normal, excluye eliminadas.
     *   - eliminadas: SOLO las que un oferente o un admin ya eliminó
     *     (soft-deleted) — para auditarlas o restaurarlas si fue un error.
     *   - todas: sin filtrar por deleted_at.
     */
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'activas');

        $query = Publicacion::query();

        if ($estado === 'eliminadas') {
            $query->onlyTrashed();
        } elseif ($estado === 'todas') {
            $query->withTrashed();
        }

        $query->with('propietario')->withAvg('calificaciones as rating', 'puntuacion');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('titulo', 'like', "%{$q}%")
                    ->orWhere('zona', 'like', "%{$q}%")
                    ->orWhereHas('propietario', fn ($p) => $p->where('nombre_completo', 'like', "%{$q}%"));
            });
        }

        $publicaciones = $query->orderByDesc('fecha_publicacion')->get()->map(fn (Publicacion $p) => [
            'id' => $p->id_publicacion,
            'titulo' => $p->titulo,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'precio' => (float) $p->precio,
            'rating' => round($p->rating ?? 0, 1),
            'img' => $p->img,
            'propietario' => $p->propietario->nombre_completo ?? '—',
            'eliminada' => $p->trashed(),
        ]);

        return response()->json(['publicaciones' => $publicaciones]);
    }

    /**
     * DELETE /api/admin/publicaciones/{publicacion}
     * A diferencia de Api\PublicacionController::destroy() (el del dueño),
     * este NO verifica propietario: el admin puede eliminar cualquier
     * publicación por contenido inapropiado o falso.
     *
     * Borrado lógico (soft delete): $publicacion->delete() ya NO ejecuta
     * un DELETE físico, solo marca deleted_at. El ON DELETE CASCADE de la
     * FK no se dispara, así que — igual que en el destroy() del dueño —
     * rechazamos a mano las reservas que sigan pendientes antes de
     * eliminar, para no dejarlas colgadas. Los reportes ligados a la
     * publicación sobreviven con id_publicacion en null (ver migración
     * de reporte, FK con 'set null').
     */
    public function destroy(Publicacion $publicacion)
    {
        $pendientes = $publicacion->reservas()->where('estado_reserva', 'pendiente')->get();

        foreach ($pendientes as $reserva) {
            $reserva->update(['estado_reserva' => 'rechazada', 'fecha_respuesta' => now()]);

            Notificacion::create([
                'id_usuario' => $reserva->id_usuario,
                'tipo' => 'reserva_rechazada',
                'mensaje' => "\"{$publicacion->titulo}\" fue eliminada por un administrador, así que tu solicitud de reserva fue rechazada automáticamente.",
                'objetivo_titulo' => $publicacion->titulo,
                'link' => '/perfil#panelReservas',
                'leida' => false,
            ]);
        }

        $publicacion->delete(); // soft delete: solo marca deleted_at, el registro sigue en BD

        return response()->json(['mensaje' => 'Publicación eliminada.']);
    }

    /**
     * PATCH /api/admin/publicaciones/{id}/restaurar
     * Revierte un soft delete (por ejemplo, si un admin eliminó por
     * error, o si tras revisar un reporte se concluye que la publicación
     * no incumplía nada). No se usa route model binding aquí porque, por
     * defecto, Laravel excluye los registros trashed al resolverlo — por
     * eso se recibe el id "en bruto" y se busca con withTrashed().
     */
    public function restaurar(string $id)
    {
        $publicacion = Publicacion::withTrashed()->findOrFail($id);

        if (! $publicacion->trashed()) {
            return response()->json(['mensaje' => 'Esta publicación no está eliminada.'], 422);
        }

        $publicacion->restore();

        return response()->json(['mensaje' => 'Publicación restaurada.']);
    }
}
