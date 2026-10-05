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
        $query = MensajeContacto::query()->orderByDesc('created_at');

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
    public function atender(MensajeContacto $mensajeContacto)
    {
        $mensajeContacto->update(['atendido' => true]);

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
            'fecha' => optional($m->created_at)->toIso8601String(),
        ];
    }
}
