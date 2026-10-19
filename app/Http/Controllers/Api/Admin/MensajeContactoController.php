<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\RespuestaContacto;
use App\Models\MensajeContacto;
use App\Services\ModeracionAuditor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Mensajes del formulario de Contáctanos. Compartido por el panel de
 * administración y el de moderación: las rutas llevan
 * `can:responder-mensajes` y cada método lo vuelve a comprobar.
 */
class MensajeContactoController extends Controller
{
    /**
     * GET /api/admin/mensajes-contacto?estado=pendiente|atendido|todos
     * Mismo patrón que AdminReporteController@index.
     */
    public function index(Request $request)
    {
        Gate::authorize('responder-mensajes');

        $query = MensajeContacto::query()->with('administrador')->orderByDesc('created_at');

        $estado = $request->input('estado', 'pendiente');
        if ($estado === 'pendiente') {
            $query->where('atendido', false);
        } elseif ($estado === 'atendido') {
            $query->where('atendido', true);
        }

        return response()->json([
            'mensajes' => $query->get()->map(fn (MensajeContacto $m) => $this->formatear($m)),
            'pendientes' => MensajeContacto::where('atendido', false)->count(),
        ]);
    }

    /**
     * PATCH /api/admin/mensajes-contacto/{mensajeContacto}/atender
     * Marca el mensaje como atendido SIN enviar correo (por ejemplo, si ya se
     * resolvió por teléfono o WhatsApp). Para contestar por correo está
     * responder().
     */
    public function atender(Request $request, MensajeContacto $mensajeContacto)
    {
        Gate::authorize('responder-mensajes');

        // Idempotente: si ya estaba atendido no se pisa quién ni cuándo lo
        // atendió originalmente (id_administrador / fecha_atencion).
        if (! $mensajeContacto->atendido) {
            $mensajeContacto->update([
                'atendido' => true,
                'id_administrador' => $request->user()->id_usuario,
                'fecha_atencion' => now(),
            ]);

            ModeracionAuditor::registrar(
                'mensaje_atendido',
                'mensaje_contacto',
                $mensajeContacto->id_mensaje_contacto,
                $mensajeContacto->asunto.' — '.$mensajeContacto->nombre,
                ['sin_respuesta_por_correo' => true],
            );
        }

        return response()->json(['mensaje' => 'Mensaje marcado como atendido.']);
    }

    /**
     * POST /api/admin/mensajes-contacto/{mensajeContacto}/responder
     * Envía la respuesta por correo al remitente y marca el mensaje como
     * atendido. Usa la misma configuración de correo (MAIL_*) y el mismo
     * estilo de plantilla que los correos de Contáctanos que ya existen.
     *
     * El correo se envía ANTES de marcar el mensaje como atendido: si el envío
     * falla, el mensaje sigue pendiente (no se pierde ni se da por contestado
     * sin que la persona haya recibido nada). Dos personas del equipo no
     * pueden contestar el mismo mensaje a la vez: la fila se bloquea durante
     * la operación y la segunda recibe un 409.
     */
    public function responder(Request $request, MensajeContacto $mensajeContacto)
    {
        Gate::authorize('responder-mensajes');

        $datos = $request->validate([
            'respuesta' => ['required', 'string', 'min:10', 'max:5000'],
        ], [
            'respuesta.required' => 'Escribe la respuesta.',
            'respuesta.min' => 'La respuesta debe tener al menos 10 caracteres.',
            'respuesta.max' => 'La respuesta no puede superar los 5000 caracteres.',
        ]);

        $gestor = $request->user();

        return DB::transaction(function () use ($mensajeContacto, $datos, $gestor) {
            $mensaje = MensajeContacto::lockForUpdate()->find($mensajeContacto->getKey());

            if (! $mensaje || $mensaje->atendido) {
                return response()->json(['mensaje' => 'Este mensaje ya fue atendido.'], 409);
            }

            try {
                Mail::to($mensaje->correo, $mensaje->nombre)->send(new RespuestaContacto([
                    'nombre' => $mensaje->nombre,
                    'asunto' => $mensaje->asunto,
                    'mensaje' => $mensaje->mensaje,
                    'respuesta' => $datos['respuesta'],
                    'atendido_por' => preg_split('/\s+/', trim($gestor->nombre_completo))[0],
                ]));
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar la respuesta de contacto', [
                    'id_mensaje_contacto' => $mensaje->id_mensaje_contacto,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'mensaje' => 'No se pudo enviar el correo de respuesta. El mensaje sigue pendiente; inténtalo de nuevo en un momento.',
                ], 502);
            }

            $mensaje->update([
                'atendido' => true,
                'id_administrador' => $gestor->id_usuario,
                'fecha_atencion' => now(),
                'respuesta' => $datos['respuesta'],
            ]);

            ModeracionAuditor::registrar(
                'mensaje_respondido',
                'mensaje_contacto',
                $mensaje->id_mensaje_contacto,
                $mensaje->asunto.' — '.$mensaje->nombre,
                ['correo' => $mensaje->correo],
            );

            return response()->json(['mensaje' => 'Respuesta enviada por correo.']);
        });
    }

    private function formatear(MensajeContacto $m): array
    {
        $etiquetas = [
            'soporte'    => 'Problema con reserva/cita',
            'cuenta'     => 'Problema con mi cuenta',
            'reporte'    => 'Quiero reportar algo',
            'sugerencia' => 'Sugerencia o idea',
            'otro'       => 'Otro',
        ];

        return [
            'id' => $m->id_mensaje_contacto,
            'nombre' => $m->nombre,
            'correo' => $m->correo,
            'asunto' => $m->asunto,
            'asunto_label' => $etiquetas[$m->asunto] ?? $m->asunto,
            'mensaje' => $m->mensaje,
            'atendido' => $m->atendido,
            'respuesta' => $m->respuesta,
            'atendido_por' => $m->administrador->nombre_completo ?? null,
            'fecha_atencion' => optional($m->fecha_atencion)->toIso8601String(),
            'fecha' => optional($m->created_at)->toIso8601String(),
        ];
    }
}
