<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use App\Models\PersonaRoomie;
use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Usuario;
use App\Services\ModeracionAuditor;
use App\Services\SesionUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReporteController extends Controller
{
    /**
     * GET /api/admin/reportes?estado=pendiente|escalado|resuelto|descartado|todos
     * Compartido por el panel de administración y el de moderación (la ruta
     * lleva `can:gestionar-reportes`; aquí se vuelve a comprobar). El motivo y
     * el título del objetivo se guardan como snapshot en el propio reporte
     * (objetivo_titulo), así que el listado sigue siendo legible aunque la
     * publicación, el usuario reportado o el perfil de roomie ya no existan.
     *
     * Cada reporte trae 'acciones': lo que el rol ACTIVO puede hacer con él
     * (resolver, descartar, escalar, devolver), calculado con la ReportePolicy.
     * El frontend solo lo usa para mostrar u ocultar botones; el servidor
     * vuelve a validar cada acción.
     */
    public function index(Request $request)
    {
        Gate::authorize('gestionar-reportes');

        $query = Reporte::query()
            ->with(['autor', 'administrador', 'escaladoPor'])
            ->orderByDesc('fecha_reporte');

        $estado = $request->input('estado', 'pendiente');
        if ($estado !== 'todos') {
            $query->where('estado_reporte', $estado);
        }

        return response()->json([
            'reportes' => $query->get()->map(fn (Reporte $r) => $this->formatear($r)),
            'pendientes' => Reporte::pendientes()->count(),
            'escalados' => Reporte::escalados()->count(),
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
        Gate::authorize('descartar', $reporte);

        $this->cambiarEstado($reporte, 'descartado', $request->user());

        ModeracionAuditor::registrar('reporte_descartado', 'reporte', $reporte->id_reporte, $reporte->objetivo_titulo, [
            'tipo' => $reporte->tipo,
            'motivo' => $reporte->motivo,
        ]);

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
     *   - usuario     -> bloquea al usuario reportado (SOLO administrador: la
     *                    ReportePolicy lo niega a un moderador, que debe escalar)
     *   - persona     -> elimina (soft delete) el perfil de roomie reportado
     *
     * Un reporte escalado solo lo puede cerrar un administrador.
     */
    public function resolver(Request $request, Reporte $reporte)
    {
        Gate::authorize('resolver', $reporte);

        $accion = null;
        $detalleAccion = [];

        if ($reporte->tipo === 'publicacion' && $reporte->id_publicacion) {
            $accion = 'publicacion_bajada';
            $detalleAccion = ['via' => 'reporte', 'id_publicacion' => $reporte->id_publicacion];
            $this->eliminarPublicacionReportada($reporte->id_publicacion);
        } elseif ($reporte->tipo === 'usuario' && $reporte->id_usuario_reportado) {
            $reportado = Usuario::find($reporte->id_usuario_reportado);

            // Mismo resguardo que UsuarioController::bloquear(): bloquear una
            // cuenta admin (superadmin o no) solo lo puede hacer un superadmin,
            // y nadie puede bloquearse a sí mismo. Se corta ANTES de cambiar
            // nada, así el reporte queda pendiente y se puede descartar o
            // gestionar por otra vía.
            $actor = $request->user();

            if ($reportado && $reportado->id_usuario === $actor->id_usuario) {
                return response()->json([
                    'mensaje' => 'No puedes bloquear tu propia cuenta. Descarta el reporte o gestiónalo por otra vía.',
                ], 422);
            }

            if ($reportado?->esAdmin() && ! $actor->esSuperadmin()) {
                return response()->json([
                    'mensaje' => 'Solo un superadmin puede bloquear a un administrador. Descarta el reporte o gestiónalo por otra vía.',
                ], 422);
            }

            if ($reportado) {
                Usuario::whereKey($reportado->id_usuario)->update([
                    'bloqueado' => true,
                    'bloqueado_motivo' => "Reporte: {$reporte->motivo}",
                    'bloqueado_fecha' => now(),
                ]);

                // Cierra las sesiones abiertas del usuario bloqueado (el
                // middleware 'cuenta.activa' cubre el resto, ver SesionUsuario).
                SesionUsuario::cerrarSesionesDe($reportado);

                $accion = 'usuario_bloqueado';
                $detalleAccion = ['via' => 'reporte', 'id_usuario' => $reportado->id_usuario, 'motivo' => $reporte->motivo];
            }
        } elseif ($reporte->tipo === 'persona' && $reporte->id_persona) {
            $accion = 'persona_bajada';
            $detalleAccion = ['via' => 'reporte', 'id_persona' => $reporte->id_persona];
            $this->eliminarPersonaReportada($reporte->id_persona);
        }

        $this->cambiarEstado($reporte, 'resuelto', $request->user());

        ModeracionAuditor::registrar('reporte_resuelto', 'reporte', $reporte->id_reporte, $reporte->objetivo_titulo, [
            'tipo' => $reporte->tipo,
            'motivo' => $reporte->motivo,
            'accion_aplicada' => $accion,
            'escalado_por' => $reporte->escaladoPor?->nombre_completo,
        ] + $detalleAccion);

        $this->notificar(
            $reporte,
            'reporte_resuelto',
            "Revisamos tu reporte sobre \"{$reporte->objetivo_titulo}\" y tomamos una acción al respecto. Gracias por ayudarnos a mantener la comunidad segura."
        );

        return response()->json(['mensaje' => 'Reporte resuelto.']);
    }

    /**
     * PATCH /api/admin/reportes/{reporte}/escalar
     * El moderador pasa un reporte a los administradores (típicamente porque
     * la acción necesaria es bloquear a un usuario). La nota es obligatoria:
     * es lo que lee el administrador para decidir.
     */
    public function escalar(Request $request, Reporte $reporte)
    {
        Gate::authorize('escalar', $reporte);

        $datos = $request->validate([
            'nota' => ['required', 'string', 'min:10', 'max:500'],
        ], [
            'nota.required' => 'Escribe una nota para el administrador.',
            'nota.min' => 'La nota debe tener al menos 10 caracteres.',
            'nota.max' => 'La nota no puede superar los 500 caracteres.',
        ]);

        $moderador = $request->user();

        $reporte->update([
            'estado_reporte' => 'escalado',
            'id_escalado_por' => $moderador->id_usuario,
            'nota_escalado' => $datos['nota'],
            'fecha_escalado' => now(),
        ]);

        Notificacion::notificarAdminsReporteEscalado($reporte, $moderador);

        ModeracionAuditor::registrar('reporte_escalado', 'reporte', $reporte->id_reporte, $reporte->objetivo_titulo, [
            'tipo' => $reporte->tipo,
            'motivo' => $reporte->motivo,
            'nota' => $datos['nota'],
        ]);

        return response()->json(['mensaje' => 'Reporte escalado a los administradores.']);
    }

    /**
     * PATCH /api/admin/reportes/{reporte}/devolver
     * Un administrador devuelve a moderación un reporte escalado (por ejemplo
     * porque no amerita bloqueo y el moderador puede cerrarlo). El reporte
     * vuelve a 'pendiente'; quién lo escaló y qué nota dejó queda en la
     * auditoría.
     */
    public function devolver(Request $request, Reporte $reporte)
    {
        Gate::authorize('devolver', $reporte);

        $datos = $request->validate([
            'nota' => ['nullable', 'string', 'max:300'],
        ]);

        $idModerador = $reporte->id_escalado_por;
        $notaEscalado = $reporte->nota_escalado;

        $reporte->update([
            'estado_reporte' => 'pendiente',
            'id_escalado_por' => null,
            'nota_escalado' => null,
            'fecha_escalado' => null,
        ]);

        Notificacion::notificarModeradorReporteDevuelto($idModerador, $reporte, $datos['nota'] ?? null);

        ModeracionAuditor::registrar('reporte_devuelto', 'reporte', $reporte->id_reporte, $reporte->objetivo_titulo, [
            'nota' => $datos['nota'] ?? null,
            'nota_escalado_original' => $notaEscalado,
            'id_moderador' => $idModerador,
        ]);

        return response()->json(['mensaje' => 'Reporte devuelto a moderación.']);
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

    /** $gestor: el administrador o moderador que cierra el reporte. */
    private function cambiarEstado(Reporte $reporte, string $estado, ?Usuario $gestor = null): void
    {
        $reporte->update([
            'estado_reporte' => $estado,
            'id_administrador' => $gestor?->id_usuario,
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
            'gestionado_por' => $r->administrador->nombre_completo ?? null,
            'escalado_por' => $r->escaladoPor->nombre_completo ?? null,
            'nota_escalado' => $r->nota_escalado,
            'fecha_escalado' => optional($r->fecha_escalado)->toIso8601String(),
            // Qué puede hacer con ESTE reporte el rol activo (ver ReportePolicy).
            'acciones' => [
                'resolver' => Gate::allows('resolver', $r),
                'descartar' => Gate::allows('descartar', $r),
                'escalar' => Gate::allows('escalar', $r),
                'devolver' => Gate::allows('devolver', $r),
            ],
        ];
    }
}
