<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\PersonaRoomie;
use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Usuario;
use Illuminate\Http\Request;

class ReporteController extends Controller
{
    /**
     * GET /api/admin/reportes?estado=pendiente|resuelto|descartado|todos
     * Reemplaza a renderReportes() de admin.js. El motivo y el título del
     * objetivo se guardan como snapshot en el propio reporte (objetivo_titulo),
     * así que el listado sigue siendo legible aunque la publicación, el
     * usuario reportado o el perfil de roomie ya no existan.
     */
    public function index(Request $request)
    {
        $query = Reporte::query()->with('autor')->orderByDesc('fecha_reporte');

        $estado = $request->input('estado', 'pendiente');
        if ($estado !== 'todos') {
            $query->where('estado_reporte', $estado);
        }

        return response()->json([
            'reportes' => $query->get()->map(fn (Reporte $r) => $this->formatear($r)),
            'pendientes' => Reporte::pendientes()->count(),
        ]);
    }

    /**
     * GET /api/admin/reportes/grafica
     * Conteo de reportes por día (últimos 14 días) para la gráfica de
     * barras del panel de resumen: publicaciones vs. usuarios/roomies.
     */
    public function grafica()
    {
        $desde = now()->subDays(13)->startOfDay();

        $dias = [];
        for ($i = 13; $i >= 0; $i--) {
            $dias[now()->subDays($i)->toDateString()] = ['publicacion' => 0, 'otros' => 0];
        }

        Reporte::where('fecha_reporte', '>=', $desde)
            ->get(['tipo', 'fecha_reporte'])
            ->each(function (Reporte $r) use (&$dias) {
                $fecha = $r->fecha_reporte->toDateString();
                if (! isset($dias[$fecha])) {
                    return;
                }
                $dias[$fecha][$r->tipo === 'publicacion' ? 'publicacion' : 'otros']++;
            });

        return response()->json(['dias' => $dias]);
    }

    /**
     * PATCH /api/admin/reportes/{reporte}/descartar
     */
    public function descartar(Request $request, Reporte $reporte)
    {
        $this->cambiarEstado($reporte, 'descartado', $request->user());

        $this->notificar(
            $reporte,
            'reporte_descartado',
            "Revisamos tu reporte sobre \"{$reporte->objetivo_titulo}\" y no encontramos elementos suficientes para tomar una acción. Gracias por ayudarnos a mantener la comunidad segura."
        );

        return response()->json(['mensaje' => 'Reporte descartado.']);
    }

    /**
     * PATCH /api/admin/reportes/{reporte}/resolver
     * Aplica la acción correspondiente según el tipo de reporte:
     *   - publicacion -> elimina (soft delete) la publicación reportada
     *   - usuario     -> bloquea al usuario reportado
     *   - persona     -> elimina (soft delete) el perfil de roomie reportado
     */
    public function resolver(Request $request, Reporte $reporte)
    {
        if ($reporte->tipo === 'publicacion' && $reporte->id_publicacion) {
            $this->eliminarPublicacionReportada($reporte->id_publicacion);
        } elseif ($reporte->tipo === 'usuario' && $reporte->id_usuario_reportado) {
            Usuario::whereKey($reporte->id_usuario_reportado)->update([
                'bloqueado' => true,
                'bloqueado_motivo' => "Reporte: {$reporte->motivo}",
                'bloqueado_fecha' => now(),
            ]);
        } elseif ($reporte->tipo === 'persona' && $reporte->id_persona) {
            $this->eliminarPersonaReportada($reporte->id_persona);
        }

        $this->cambiarEstado($reporte, 'resuelto', $request->user());

        $this->notificar(
            $reporte,
            'reporte_resuelto',
            "Revisamos tu reporte sobre \"{$reporte->objetivo_titulo}\" y tomamos una acción al respecto. Gracias por ayudarnos a mantener la comunidad segura."
        );

        return response()->json(['mensaje' => 'Reporte resuelto.']);
    }

    /**
     * Elimina (soft delete) la publicación reportada. Se carga el modelo
     * en vez de usar Publicacion::whereKey($id)->delete() directo porque,
     * con SoftDeletes, eso solo actualiza deleted_at — no dispara el ON
     * DELETE CASCADE de la FK, así que las reservas asociadas se
     * quedarían "pendiente" para siempre, apuntando a una publicación que
     * ya no se puede ver. Mismo patrón que
     * Api\PublicacionController::destroy() y
     * Admin\PublicacionController::destroy(): rechazar a mano lo
     * pendiente y notificar, y solo entonces eliminar.
     */
    private function eliminarPublicacionReportada(int $idPublicacion): void
    {
        $publicacion = Publicacion::find($idPublicacion);

        if (! $publicacion) {
            return;
        }

        $pendientes = $publicacion->reservas()->where('estado_reserva', 'pendiente')->get();

        foreach ($pendientes as $reserva) {
            $reserva->update(['estado_reserva' => 'rechazada', 'fecha_respuesta' => now()]);

            Notificacion::create([
                'id_usuario' => $reserva->id_usuario,
                'tipo' => 'reserva_rechazada',
                'mensaje' => "\"{$publicacion->titulo}\" fue eliminada tras revisar un reporte, así que tu solicitud de reserva fue rechazada automáticamente.",
                'objetivo_titulo' => $publicacion->titulo,
                'link' => '/perfil#panelReservas',
                'leida' => false,
            ]);
        }

        $publicacion->delete();
    }

    /**
     * Elimina (soft delete) el perfil de roomie reportado. Mismo motivo y
     * mismo patrón que eliminarPublicacionReportada(): se carga el
     * modelo para poder rechazar a mano las citas pendientes (con
     * notificación) antes de eliminar, porque el ON DELETE CASCADE ya no
     * se dispara con SoftDeletes. Ver también
     * Admin\PersonaRoomieController::destroy(), que hace exactamente esto
     * mismo cuando el admin elimina un perfil manualmente (no vía reporte).
     */
    private function eliminarPersonaReportada(int $idPersona): void
    {
        $persona = PersonaRoomie::with('usuario')->find($idPersona);

        if (! $persona) {
            return;
        }

        $pendientes = $persona->citas()->where('estado_cita', 'pendiente')->get();

        foreach ($pendientes as $cita) {
            $cita->update(['estado_cita' => 'rechazada', 'fecha_respuesta' => now()]);

            Notificacion::create([
                'id_usuario' => $cita->id_usuario,
                'tipo' => 'cita_rechazada',
                'mensaje' => 'Este perfil de roomie fue eliminado tras revisar un reporte, así que tu solicitud de cita fue rechazada automáticamente.',
                'objetivo_titulo' => $persona->usuario->nombre_completo ?? 'Perfil de roomie',
                'link' => '/perfil#panelCitas',
                'leida' => false,
            ]);
        }

        $persona->delete();
    }

    private function cambiarEstado(Reporte $reporte, string $estado, ?Usuario $admin = null): void
    {
        $reporte->update([
            'estado_reporte' => $estado,
            'id_administrador' => $admin?->id_usuario,
            'fecha_gestion' => now(),
        ]);
    }

    /**
     * Si la cuenta de quien reportó ya no existe (id_usuario_reporta quedó
     * en null por el 'set null' de la FK), simplemente no hay a quién
     * notificar — el reporte igual se resuelve con normalidad.
     */
    private function notificar(Reporte $reporte, string $tipo, string $mensaje): void
    {
        if (! $reporte->id_usuario_reporta) {
            return;
        }

        Notificacion::create([
            'id_usuario' => $reporte->id_usuario_reporta,
            'tipo' => $tipo,
            'mensaje' => $mensaje,
            'objetivo_titulo' => $reporte->objetivo_titulo,
            'leida' => false,
        ]);
    }

    private function formatear(Reporte $r): array
    {
        return [
            'id' => $r->id_reporte,
            'tipo' => $r->tipo,
            'motivo' => $r->motivo,
            'descripcion' => $r->descripcion,
            'objetivo_titulo' => $r->objetivo_titulo,
            'estado' => $r->estado_reporte,
            'reportante' => $r->autor->nombre_completo ?? 'Usuario eliminado',
            'fecha' => optional($r->fecha_reporte)->toIso8601String(),
        ];
    }
}
