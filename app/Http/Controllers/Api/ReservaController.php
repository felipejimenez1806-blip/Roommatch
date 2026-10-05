<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ReservaRequest;
use App\Models\Calificacion;
use App\Models\Notificacion;
use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Reserva;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservaController extends Controller
{
    /**
     * POST /api/publicaciones/{publicacion}/reservas
     * Crea una solicitud de reserva y notifica al oferente.
     */
    public function store(ReservaRequest $request, Publicacion $publicacion)
    {
        $usuario = $request->user();

        if ($publicacion->id_usuario === $usuario->id_usuario) {
            abort(403, 'No puedes reservar tu propia publicación.');
        }

        if ($publicacion->estado_inmueble !== 'disponible') {
            // ValidationException (no abort()) para que la respuesta traiga
            // el mismo shape {errors: {...}} que ya sabe leer reserva.js,
            // en vez de {message: '...'} que quedaría sin mostrarse.
            throw ValidationException::withMessages([
                'estado_inmueble' => 'Esta publicación ya no está disponible.',
            ]);
        }

        $datos = $request->validated();

        // lockForUpdate() serializa el chequeo+creación contra cualquier
        // otra solicitud que esté intentando tomar el mismo horario al
        // mismo tiempo para esta publicación, para que dos personas no
        // puedan "ganarle" ambas a la validación y quedar las dos
        // agendadas en el mismo bloque.
        $reserva = DB::transaction(function () use ($datos, $publicacion, $usuario) {
            $horarioOcupado = Reserva::where('id_publicacion', $publicacion->id_publicacion)
                ->where('fecha_visita', $datos['fecha_visita'])
                ->where('hora_visita', $datos['hora_visita'])
                ->whereIn('estado_reserva', ['pendiente', 'aceptada'])
                ->lockForUpdate()
                ->exists();

            if ($horarioOcupado) {
                throw ValidationException::withMessages([
                    'hora_visita' => 'Ese horario ya fue tomado por otro usuario para esta publicación. Elige otro.',
                ]);
            }

            return Reserva::create([
                'id_usuario' => $usuario->id_usuario,
                'id_publicacion' => $publicacion->id_publicacion,
                'fecha_visita' => $datos['fecha_visita'],
                'hora_visita' => $datos['hora_visita'],
                'duracion_estimada' => $datos['duracion_estimada'],
                'contacto_nombre' => $datos['contacto_nombre'],
                'contacto_correo' => $datos['contacto_correo'],
                'contacto_telefono' => $datos['contacto_telefono'],
            ]);
        });

        Notificacion::create([
            'id_usuario' => $publicacion->id_usuario,
            'tipo' => 'reserva_nueva',
            'mensaje' => "{$datos['contacto_nombre']} solicitó reservar \"{$publicacion->titulo}\". Ve a Mis publicaciones para aceptar o rechazar la solicitud.",
            'objetivo_titulo' => $publicacion->titulo,
            'link' => "/mis-publicaciones#request-reserva-{$reserva->id_reserva}",
            'leida' => false,
        ]);

        return response()->json(['reserva' => ['id' => $reserva->id_reserva]], 201);
    }

    /**
     * GET /api/publicaciones/{publicacion}/horarios-ocupados?fecha=YYYY-MM-DD
     * Pública (como show()/index() de PublicacionController): solo
     * devuelve qué horas ya están tomadas, sin datos de quién las tomó,
     * así que no hace falta sesión para consultarla. reserva.js la usa
     * para deshabilitar en vivo las horas ya ocupadas al elegir fecha.
     */
    public function horariosOcupados(Request $request, Publicacion $publicacion)
    {
        $datos = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        $horariosOcupados = Reserva::where('id_publicacion', $publicacion->id_publicacion)
            ->where('fecha_visita', $datos['fecha'])
            ->whereIn('estado_reserva', ['pendiente', 'aceptada'])
            ->pluck('hora_visita');

        return response()->json(['horarios_ocupados' => $horariosOcupados]);
    }

    /**
     * GET /api/reservas/{reserva}
     * Detalle de UNA solicitud propia (para confirmacion.js).
     * Solo el solicitante que la creó puede verla.
     */
    public function show(Request $request, Reserva $reserva)
    {
        if ($reserva->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes ver una solicitud que no es tuya.');
        }

        $reserva->load(['publicacion.propietario', 'publicacion.calificaciones']);

        return response()->json(['reserva' => $this->formatearReserva($reserva)]);
    }

    /**
     * GET /api/mis-reservas
     * Listado de TODAS las reservas hechas por el usuario (lado
     * buscador) — panel "Reservas" de perfil.js.
     */
    public function misReservas(Request $request)
    {
        $idUsuario = $request->user()->id_usuario;

        $reservas = Reserva::where('id_usuario', $idUsuario)
            ->with('publicacion')
            ->orderByDesc('fecha_solicitud')
            ->get()
            ->map(function (Reserva $r) use ($idUsuario) {
                $pub = $r->publicacion;

                $yaCalifico = $pub && Calificacion::where('id_usuario', $idUsuario)
                    ->where('tipo', 'publicacion')->where('id_publicacion', $pub->id_publicacion)->exists();

                $yaReporto = $pub && Reporte::where('id_usuario_reporta', $idUsuario)
                    ->where('tipo', 'publicacion')->where('id_publicacion', $pub->id_publicacion)->exists();

                return [
                    'id' => $r->id_reserva,
                    'titulo' => $pub->titulo ?? 'Publicación eliminada',
                    'zona' => $pub->zona ?? '',
                    'img' => $pub->img ?? '',
                    'publicacionId' => $r->id_publicacion,
                    'estado' => $r->estado_reserva,
                    'fecha_visita' => optional($r->fecha_visita)->format('Y-m-d'),
                    'hora_visita' => $r->hora_visita,
                    'duracion' => $r->duracion_estimada,
                    'monto_total' => (float) ($pub->precio ?? 0),
                    'fecha_solicitud' => $r->fecha_solicitud,
                    'huboEdicion' => (bool) ($pub && $pub->fecha_actualizacion && $pub->fecha_actualizacion->gt($r->fecha_solicitud)),
                    'calificada' => (bool) $yaCalifico,
                    'reportada' => (bool) $yaReporto,
                ];
            });

        return response()->json(['reservas' => $reservas]);
    }

    /**
     * PATCH /api/reservas/{reserva}/estado
     * Lado oferente: aceptar/rechazar una solicitud sobre su publicación.
     */
    public function actualizarEstado(Request $request, Reserva $reserva)
    {
        $request->validate([
            'estado' => 'required|in:aceptada,rechazada',
        ]);

        $reserva->load('publicacion');

        if ($reserva->publicacion->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes gestionar solicitudes de una publicación que no es tuya.');
        }

        $reserva->update([
            'estado_reserva' => $request->estado,
            'fecha_respuesta' => now(),
        ]);

        Notificacion::create([
            'id_usuario' => $reserva->id_usuario,
            'tipo' => $request->estado === 'aceptada' ? 'reserva_aceptada' : 'reserva_rechazada',
            'mensaje' => $request->estado === 'aceptada'
                ? "Tu solicitud de reserva para \"{$reserva->publicacion->titulo}\" fue aceptada. Ve a Mis reservas para ver el detalle."
                : "Tu solicitud de reserva para \"{$reserva->publicacion->titulo}\" fue rechazada.",
            'objetivo_titulo' => $reserva->publicacion->titulo,
            'link' => '/perfil#panelReservas',
            'leida' => false,
        ]);

        return response()->json(['mensaje' => 'Estado actualizado.']);
    }

    /**
     * PATCH /api/reservas/{reserva}/cancelar
     * Lado solicitante: cancela su propia solicitud (solo si sigue pendiente).
     */
    public function cancelar(Request $request, Reserva $reserva)
    {
        if ($reserva->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes cancelar una solicitud que no es tuya.');
        }

        if ($reserva->estado_reserva !== 'pendiente') {
            abort(422, 'Esta solicitud ya no se puede cancelar.');
        }

        $reserva->update([
            'estado_reserva' => 'cancelada',
            'fecha_respuesta' => now(),
        ]);

        return response()->json(['mensaje' => 'Solicitud cancelada.']);
    }

    /**
     * POST /api/reservas/{reserva}/calificar
     * Lado buscador: calificar una reserva ya aceptada (panel "Reservas"
     * de perfil.js).
     */
    public function calificar(Request $request, Reserva $reserva)
    {
        if ($reserva->id_usuario !== $request->user()->id_usuario) {
            abort(403);
        }
        if ($reserva->estado_reserva !== 'aceptada') {
            return response()->json(['mensaje' => 'Solo puedes calificar reservas aceptadas.'], 422);
        }

        $datos = $request->validate([
            'puntuacion' => 'required|integer|min:1|max:10',
            'comentario' => 'required|string|min:10',
        ]);

        Calificacion::create([
            'id_usuario' => $request->user()->id_usuario,
            'tipo' => 'publicacion',
            'id_publicacion' => $reserva->id_publicacion,
            'autor_nombre' => $request->user()->nombre_completo,
            'puntuacion' => $datos['puntuacion'],
            'comentario' => $datos['comentario'],
        ]);

        return response()->json(['mensaje' => 'Reseña enviada.']);
    }

    /**
     * POST /api/reservas/{reserva}/reportar
     * Lado buscador: reportar una reserva ya aceptada.
     */
    public function reportar(Request $request, Reserva $reserva)
    {
        if ($reserva->id_usuario !== $request->user()->id_usuario) {
            abort(403);
        }
        if ($reserva->estado_reserva !== 'aceptada') {
            return response()->json(['mensaje' => 'Solo puedes reportar reservas aceptadas.'], 422);
        }

        $datos = $request->validate([
            'motivo' => 'required|string|max:150',
            'descripcion' => 'required|string|min:15',
        ]);

        $reporte = Reporte::create([
            'id_usuario_reporta' => $request->user()->id_usuario,
            'tipo' => 'publicacion',
            'id_publicacion' => $reserva->id_publicacion,
            'objetivo_titulo' => $reserva->publicacion->titulo ?? null,
            'motivo' => $datos['motivo'],
            'descripcion' => $datos['descripcion'],
        ]);

        Notificacion::notificarAdminsNuevoReporte($reporte);

        return response()->json(['mensaje' => 'Reporte enviado.']);
    }

    // ------------------------------------------------------------

    /**
     * Algunas publicaciones antiguas quedaron con 'imagenes' doblemente
     * codificado en JSON. Se normaliza aquí también porque
     * confirmacion.js necesita la imagen de portada.
     */
    private function normalizarImagenes($valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if (is_string($valor) && $valor !== '') {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        return [];
    }

    private function formatearReserva(Reserva $r): array
    {
        $p = $r->publicacion;
        $imagenes = $this->normalizarImagenes($p->imagenes);

        return [
            'id' => $r->id_reserva,
            'estado' => $r->estado_reserva,
            'fecha_visita' => optional($r->fecha_visita)->format('Y-m-d'),
            'hora_visita' => $r->hora_visita,
            'duracion_estimada' => $r->duracion_estimada,
            'publicacion' => [
                'id' => $p->id_publicacion,
                'titulo' => $p->titulo,
                'direccion' => $p->direccion,
                'zona' => $p->zona,
                'ciudad' => $p->ciudad,
                'precio' => (float) $p->precio,
                'rating' => $p->promedio_calificacion,
                'imagenes' => $imagenes ?: array_filter([$p->img]),
                'propietario' => $p->propietario ? [
                    'nombre' => $p->propietario->nombre_completo,
                    'telefono' => $p->propietario->telefono,
                ] : null,
            ],
        ];
    }
}
