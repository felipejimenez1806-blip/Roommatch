<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\PersonaRoomie;
use Illuminate\Http\Request;

class PersonaRoomieController extends Controller
{
    /**
     * GET /api/admin/roomies?q=...&estado=activos|eliminados|todos
     * Mismo patrón que Admin\PublicacionController::index().
     *
     * 'estado' controla cómo se aplica (o no) el scope de SoftDeletes:
     *   - activos   (default): comportamiento normal, excluye eliminados.
     *   - eliminados: SOLO los que un usuario o un admin ya eliminó
     *     (soft-deleted) — para auditarlos o restaurarlos si fue un error.
     *   - todos: sin filtrar por deleted_at.
     */
    public function index(Request $request)
    {
        $estado = $request->input('estado', 'activos');

        $query = PersonaRoomie::query();

        if ($estado === 'eliminados') {
            $query->onlyTrashed();
        } elseif ($estado === 'todos') {
            $query->withTrashed();
        }

        $query->with('usuario')->withAvg('calificaciones as rating', 'puntuacion');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('zona', 'like', "%{$q}%")
                    ->orWhereHas('usuario', fn ($u) => $u->where('nombre_completo', 'like', "%{$q}%"));
            });
        }

        $personas = $query->orderByDesc('fecha_publicacion')->get()->map(fn (PersonaRoomie $p) => [
            'id' => $p->id_persona,
            'nombre' => $p->usuario->nombre_completo ?? '—',
            'edad' => $p->edad,
            'ocupacion' => $p->ocupacion,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'presupuesto' => (float) $p->presupuesto,
            'rating' => round($p->rating ?? 0, 1),
            'img' => $p->img,
            'eliminado' => $p->trashed(),
        ]);

        return response()->json(['personas' => $personas]);
    }

    /**
     * DELETE /api/admin/roomies/{persona}
     * A diferencia de Api\PersonaRoomieController::destroy() (el del
     * dueño), este NO verifica propietario: el admin puede despublicar
     * cualquier perfil por contenido inapropiado o falso.
     *
     * Borrado lógico (soft delete): $persona->delete() ya NO ejecuta un
     * DELETE físico, solo marca deleted_at. El ON DELETE CASCADE de la FK
     * no se dispara, así que — igual que en el destroy() del dueño —
     * rechazamos a mano las citas que sigan pendientes antes de eliminar,
     * para no dejarlas colgadas. Los reportes ligados al perfil sobreviven
     * con id_persona en null (ver migración de reporte, FK con 'set null').
     */
    public function destroy(PersonaRoomie $persona)
    {
        $persona->loadMissing('usuario');

        $pendientes = $persona->citas()->where('estado_cita', 'pendiente')->get();

        foreach ($pendientes as $cita) {
            $cita->update(['estado_cita' => 'rechazada', 'fecha_respuesta' => now()]);

            Notificacion::create([
                'id_usuario' => $cita->id_usuario,
                'tipo' => 'cita_rechazada',
                'mensaje' => 'Este perfil de roomie fue eliminado por un administrador, así que tu solicitud de cita fue rechazada automáticamente.',
                'objetivo_titulo' => $persona->usuario->nombre_completo ?? 'Perfil de roomie',
                'link' => '/perfil#panelCitas',
                'leida' => false,
            ]);
        }

        $persona->delete(); // soft delete: solo marca deleted_at, el registro sigue en BD

        return response()->json(['mensaje' => 'Perfil de roomie eliminado.']);
    }

    /**
     * PATCH /api/admin/roomies/{id}/restaurar
     * Revierte un soft delete. No se usa route model binding aquí porque,
     * por defecto, Laravel excluye los registros trashed al resolverlo —
     * por eso se recibe el id "en bruto" y se busca con withTrashed().
     *
     * NOTA: como el unique(id_usuario) de persona_roomie se quitó (ver
     * migración de soft delete), si el usuario ya creó un perfil NUEVO
     * después de eliminar el viejo, restaurar el viejo dejaría dos
     * perfiles activos para el mismo usuario. Se valida ese caso aquí.
     */
    public function restaurar(string $id)
    {
        $persona = PersonaRoomie::withTrashed()->findOrFail($id);

        if (! $persona->trashed()) {
            return response()->json(['mensaje' => 'Este perfil no está eliminado.'], 422);
        }

        $yaTieneOtroActivo = PersonaRoomie::where('id_usuario', $persona->id_usuario)->exists();
        if ($yaTieneOtroActivo) {
            return response()->json([
                'mensaje' => 'Este usuario ya tiene otro perfil de roomie activo; no se puede restaurar este sin eliminar el actual primero.',
            ], 422);
        }

        $persona->restore();

        return response()->json(['mensaje' => 'Perfil de roomie restaurado.']);
    }
}
