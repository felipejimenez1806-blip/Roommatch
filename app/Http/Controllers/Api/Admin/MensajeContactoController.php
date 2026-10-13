<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MensajeContacto;
use Illuminate\Http\Request;

class MensajeContactoController extends Controller
{
    /**
     * GET /api/admin/mensajes-contacto?estado=pendiente|atendido|todos
     * Mismo patrón que AdminReporteController@index.
     */
    public function index(Request $request)
    {
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
     */
    public function atender(Request $request, MensajeContacto $mensajeContacto)
    {
        // Idempotente: si ya estaba atendido no se pisa quién ni cuándo lo
        // atendió originalmente (id_administrador / fecha_atencion).
        if (! $mensajeContacto->atendido) {
            $mensajeContacto->update([
                'atendido' => true,
                'id_administrador' => $request->user()->id_usuario,
                'fecha_atencion' => now(),
            ]);
        }

        return response()->json(['mensaje' => 'Mensaje marcado como atendido.']);
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
            'atendido_por' => $m->administrador->nombre_completo ?? null,
            'fecha_atencion' => optional($m->fecha_atencion)->toIso8601String(),
            'fecha' => optional($m->created_at)->toIso8601String(),
        ];
    }
}
