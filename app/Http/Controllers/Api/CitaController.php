<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CitaRequest;
use App\Models\Calificacion;
use App\Models\Cita;
use App\Models\Notificacion;
use App\Models\PersonaRoomie;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CitaController extends Controller
{
    /**
     * POST /api/roomies/{persona}/citas
     * Crea una solicitud de cita para conocerse y notifica al roomie.
     * Reemplaza a activarEnvio() de cita-roomie.js (guardado +
     * notificarNuevaCita() de la versión vanilla).
     */
    public function store(CitaRequest $request, PersonaRoomie $persona)
    {
        $usuario = $request->user();

        if ($persona->id_usuario === $usuario->id_usuario) {
            abort(403, 'No puedes agendar una cita contigo mismo/a.');
        }

        if ($persona->estado_busqueda !== 'buscando') {
            // ValidationException (no abort()) para que la respuesta traiga
            // el mismo shape {errors: {...}} que ya sabe leer cita-roomie.js.
            throw ValidationException::withMessages([
                'estado_busqueda' => 'Este roomie ya no está buscando.',
            ]);
        }

        $datos = $request->validated();

        // lockForUpdate() serializa el chequeo+creación contra cualquier
        // otra solicitud que esté intentando tomar el mismo horario al
        // mismo tiempo con este mismo roomie (mismo mecanismo que
        // ReservaController::store()).
        $cita = DB::transaction(function () use ($datos, $persona, $usuario) {
            $horarioOcupado = Cita::where('id_persona', $persona->id_persona)
                ->where('fecha_cita', $datos['fecha_cita'])
                ->where('hora_cita', $datos['hora_cita'])
                ->whereIn('estado_cita', ['pendiente', 'aceptada'])
                ->lockForUpdate()
                ->exists();

            if ($horarioOcupado) {
                throw ValidationException::withMessages([
                    'hora_cita' => 'Ese horario ya fue tomado por otra persona con este roomie. Elige otro.',
                ]);
            }

            return Cita::create([
                'id_usuario' => $usuario->id_usuario,
                'id_persona' => $persona->id_persona,
                'fecha_cita' => $datos['fecha_cita'],
                'hora_cita' => $datos['hora_cita'],
                'tipo_encuentro' => $datos['tipo_encuentro'],
                'lugar_encuentro' => $datos['lugar_encuentro'],
                'mensaje' => $datos['mensaje'] ?? null,
                'contacto_nombre' => $datos['contacto_nombre'],
                'contacto_correo' => $datos['contacto_correo'],
                'contacto_telefono' => $datos['contacto_telefono'],
            ]);
        });

        // Avisa al roomie para que vaya a "Mis publicaciones" a aceptar
        // o rechazar la cita (mismo panel que las solicitudes de reserva).
        Notificacion::create([
            'id_usuario' => $persona->id_usuario,
            'tipo' => 'cita_nueva',
            'mensaje' => "{$datos['contacto_nombre']} te envió una solicitud de cita. Ve a Mis publicaciones para aceptarla o rechazarla.",
            'objetivo_titulo' => "Cita con {$datos['contacto_nombre']}",
            'link' => "/mis-publicaciones#request-cita-{$cita->id_cita}",
            'leida' => false,
        ]);

        return response()->json(['cita' => ['id' => $cita->id_cita]], 201);
    }

    /**
     * GET /api/roomies/{persona}/horarios-ocupados?fecha=YYYY-MM-DD
     * Pública (como show() de PersonaRoomieController): solo devuelve
     * qué horas ya están tomadas con este roomie, sin datos de quién
     * las tomó. cita-roomie.js la usa para deshabilitar en vivo las
     * horas ya ocupadas al elegir fecha.
     */
    public function horariosOcupados(Request $request, PersonaRoomie $persona)
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        $horariosOcupados = Cita::where('id_persona', $persona->id_persona)
            ->where('fecha_cita', $datos['fecha'])
            ->whereIn('estado_cita', ['pendiente', 'aceptada'])
            ->pluck('hora_cita');

        return response()->json(['horarios_ocupados' => $horariosOcupados]);
    }

    /**
     * GET /api/citas/{cita}
     * Detalle de UNA cita propia, con los datos del perfil de roomie
     * que necesita la página de confirmación (reemplaza el lookup en
     * `personas` + `usuarioActual.citas` de confirmacion-cita.js).
     * Solo el solicitante que la creó puede verla.
     */
    public function show(Request $request, Cita $cita)
    {
        if ($cita->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes ver una cita que no es tuya.');
        }

        $cita->load('persona.usuario');

        return response()->json(['cita' => $this->formatearCita($cita)]);
    }

    /**
     * PATCH /api/citas/{cita}/cancelar
     * Reemplaza a confirmarCancelacion() de confirmacion-cita.js. La
     * ejecuta el SOLICITANTE (quien pidió la cita), igual que
     * ReservaController::cancelar().
     */
    public function cancelar(Request $request, Cita $cita)
    {
        if ($cita->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes cancelar una cita que no es tuya.');
        }

        if ($cita->estado_cita !== 'pendiente') {
            abort(422, 'Esta cita ya no se puede cancelar.');
        }

        $cita->update([
            'estado_cita' => 'cancelada',
            'fecha_respuesta' => now(),
        ]);

        return response()->json(['mensaje' => 'Cita cancelada.']);
    }

    /**
     * POST /api/citas/{cita}/reportar
     * Lado solicitante: reportar al roomie de una cita ya aceptada.
     * Mismo patrón que ReservaController::reportar(), pero tipo 'persona'
     * en vez de 'publicacion'.
     */
    public function reportar(Request $request, Cita $cita)
    {
        if ($cita->id_usuario !== $request->user()->id_usuario) {
            abort(403);
        }
        if ($cita->estado_cita !== 'aceptada') {
            return response()->json(['mensaje' => 'Solo puedes reportar citas aceptadas.'], 422);
        }

        $datos = $request->validate([
            'motivo' => 'required|string|max:150',
            'descripcion' => 'required|string|min:15',
        ]);

        $cita->loadMissing('persona.usuario');

        $reporte = Reporte::create([
            'id_usuario_reporta' => $request->user()->id_usuario,
            'tipo' => 'persona',
            'id_persona' => $cita->id_persona,
            'objetivo_titulo' => $cita->persona->usuario->nombre_completo ?? null,
            'motivo' => $datos['motivo'],
            'descripcion' => $datos['descripcion'],
        ]);

        Notificacion::notificarAdminsNuevoReporte($reporte);

        return response()->json(['mensaje' => 'Reporte enviado.']);
    }

    /**
     * GET /api/mis-citas
     * Lado solicitante: TODAS las citas que YO agendé con roomies —
     * panel "Mis citas" de perfil.js. Mismo shape que
     * ReservaController::misReservas().
     */
    public function misCitas(Request $request)
    {
        $idUsuario = $request->user()->id_usuario;

        $citas = Cita::where('id_usuario', $idUsuario)
            ->with('persona.usuario')
            ->orderByDesc('fecha_solicitud')
            ->get()
            ->map(function (Cita $c) use ($idUsuario) {
                $p = $c->persona;

                $yaCalifico = $p && Calificacion::where('id_usuario', $idUsuario)
                    ->where('tipo', 'persona')->where('id_persona', $p->id_persona)->exists();

                $yaReporto = $p && Reporte::where('id_usuario_reporta', $idUsuario)
                    ->where('tipo', 'persona')->where('id_persona', $p->id_persona)->exists();

                return [
                    'id' => $c->id_cita,
                    'nombre' => $p->usuario->nombre_completo ?? 'Roomie eliminado',
                    'zona' => $p->zona ?? '',
                    'img' => $p->img ?? '',
                    'personaId' => $c->id_persona,
                    'estado' => $c->estado_cita,
                    'fecha_cita' => optional($c->fecha_cita)->format('Y-m-d'),
                    'hora_cita' => $c->hora_cita,
                    'tipo_encuentro' => $c->tipo_encuentro,
                    'lugar_encuentro' => $c->lugar_encuentro,
                    'fecha_solicitud' => $c->fecha_solicitud,
                    'calificada' => (bool) $yaCalifico,
                    'reportada' => (bool) $yaReporto,
                ];
            });

        return response()->json(['citas' => $citas]);
    }

    /**
     * GET /api/mis-citas-recibidas
     * Lado roomie: citas que le agendaron a MI perfil de roomie —
     * pensado para vivir en "Mis publicaciones" junto a las solicitudes
     * de reserva, con los mismos botones Aceptar/Rechazar.
     */
    public function citasRecibidas(Request $request)
    {
        $idUsuario = $request->user()->id_usuario;

        $citas = Cita::whereHas('persona', fn ($q) => $q->where('id_usuario', $idUsuario))
            ->with('solicitante')
            ->orderByDesc('fecha_solicitud')
            ->get()
            ->map(fn (Cita $c) => [
                'id' => $c->id_cita,
                'solicitante' => $c->contacto_nombre ?? $c->solicitante->nombre_completo ?? 'Usuario',
                'contactoCorreo' => $c->contacto_correo,
                'contactoTelefono' => $c->contacto_telefono,
                'estado' => $c->estado_cita,
                'fecha_cita' => optional($c->fecha_cita)->format('Y-m-d'),
                'hora_cita' => $c->hora_cita,
                'tipo_encuentro' => $c->tipo_encuentro,
                'lugar_encuentro' => $c->lugar_encuentro,
                'mensaje' => $c->mensaje,
                'fecha_solicitud' => $c->fecha_solicitud,
            ]);

        return response()->json(['citas' => $citas]);
    }

    /**
     * PATCH /api/citas/{cita}/estado
     * Lado roomie: aceptar/rechazar una cita agendada sobre MI perfil.
     * Equivalente exacto a ReservaController::actualizarEstado(), pero
     * verificando dueño vía persona.id_usuario en vez de publicacion.id_usuario.
     * Sin este método, ninguna cita podía llegar nunca a 'aceptada', así
     * que calificar/reportar quedaban bloqueados en la práctica.
     */
    public function actualizarEstado(Request $request, Cita $cita)
    {
        $request->validate([
            'estado' => 'required|in:aceptada,rechazada',
        ]);

        $cita->load('persona.usuario');

        if ($cita->persona->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes gestionar citas de un perfil de roomie que no es tuyo.');
        }

        $cita->update([
            'estado_cita' => $request->estado,
            'fecha_respuesta' => now(),
        ]);

        $nombreRoomie = $cita->persona->usuario->nombre_completo ?? 'El roomie';

        Notificacion::create([
            'id_usuario' => $cita->id_usuario,
            'tipo' => $request->estado === 'aceptada' ? 'cita_aceptada' : 'cita_rechazada',
            'mensaje' => $request->estado === 'aceptada'
                ? "{$nombreRoomie} aceptó tu solicitud de cita. Ve a Mis citas para ver el detalle."
                : "{$nombreRoomie} rechazó tu solicitud de cita.",
            'objetivo_titulo' => $nombreRoomie,
            'link' => '/perfil#panelCitas',
            'leida' => false,
        ]);

        return response()->json(['mensaje' => 'Estado actualizado.']);
    }

    /**
     * POST /api/citas/{cita}/calificar
     * Lado solicitante: calificar al roomie de una cita ya aceptada.
     */
    public function calificar(Request $request, Cita $cita)
    {
        if ($cita->id_usuario !== $request->user()->id_usuario) {
            abort(403);
        }
        if ($cita->estado_cita !== 'aceptada') {
            return response()->json(['mensaje' => 'Solo puedes calificar citas aceptadas.'], 422);
        }

        $datos = $request->validate([
            'puntuacion' => 'required|integer|min:1|max:10',
            'comentario' => 'required|string|min:10',
        ]);

        Calificacion::create([
            'id_usuario' => $request->user()->id_usuario,
            'tipo' => 'persona',
            'id_persona' => $cita->id_persona,
            'autor_nombre' => $request->user()->nombre_completo,
            'puntuacion' => $datos['puntuacion'],
            'comentario' => $datos['comentario'],
        ]);

        return response()->json(['mensaje' => 'Reseña enviada.']);
    }

    // ------------------------------------------------------------

    private function formatearCita(Cita $c): array
    {
        $p = $c->persona;

        return [
            'id' => $c->id_cita,
            'estado' => $c->estado_cita,
            'fecha_cita' => optional($c->fecha_cita)->format('Y-m-d'),
            'hora_cita' => $c->hora_cita,
            'tipo_encuentro' => $c->tipo_encuentro,
            'lugar_encuentro' => $c->lugar_encuentro,
            'mensaje' => $c->mensaje,
            'persona' => [
                'id' => $p->id_persona,
                'nombre' => $p->usuario->nombre_completo ?? 'Usuario',
                'zona' => $p->zona,
                'ciudad' => $p->ciudad,
                'presupuesto' => (float) $p->presupuesto,
                'rating' => $p->promedio_calificacion,
                'img' => $p->img,
                'telefono' => $p->telefono,
            ],
        ];
    }
}
