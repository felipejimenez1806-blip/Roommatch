<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Avisos que llegan desde el servicio de Java (no desde el navegador). Las rutas van con el middleware
 * VerificarServiceKey (header X-Service-Key = ROOMMATCH_SERVICE_KEY). Java no escribe en la base de
 * Laravel: solo avisa, y aquí se arma el mensaje y se crea la notificación de la campanita.
 */
class ServicioNotificacionController extends Controller
{
    /**
     * POST /api/servicio/notificaciones/agenda-nota
     *
     * Alguien agregó, cambió o quitó su nota en una reserva o cita de Mi agenda: se avisa a la OTRA persona.
     * Cuerpo: { idUsuario (destinatario), autor, evento (título que ve el destinatario), accion }
     */
    public function agendaNota(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'idUsuario' => ['required', 'integer', 'exists:usuario,id_usuario'],
            'autor' => ['required', 'string', 'max:150'],
            'evento' => ['required', 'string', 'max:150'],
            'accion' => ['required', 'in:AGREGADA,MODIFICADA,ELIMINADA'],
        ]);

        Notificacion::notificarNotaAgenda(
            (int) $datos['idUsuario'],
            $datos['autor'],
            $datos['evento'],
            $datos['accion'],
        );

        return response()->json(['mensaje' => 'Aviso registrado.'], 201);
    }
}
