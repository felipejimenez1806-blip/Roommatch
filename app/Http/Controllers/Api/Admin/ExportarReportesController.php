<?php

namespace App\Http\Controllers\Api\Admin;

use App\Exceptions\ServicioJavaNoDisponibleException;
use App\Http\Controllers\Controller;
use App\Models\Reporte;
use App\Services\ModeracionAuditor;
use App\Services\ServicioJavaClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * POST /api/admin/reportes/exportar  (solo administrador: grupo 'es.admin')
 *
 * Laravel lee TODOS los reportes de su base y se los manda a Java junto con los
 * criterios elegidos. Java filtra en memoria, arma el Excel o el PDF y lo
 * devuelve; aquí solo se le pasa el archivo al navegador.
 */
class ExportarReportesController extends Controller
{
    public function __invoke(Request $request, ServicioJavaClient $java)
    {
        Gate::authorize('gestionar-reportes');

        $datos = $request->validate([
            'formato' => ['required', Rule::in(['xlsx', 'pdf'])],
            'estado' => ['nullable', Rule::in(['todos', 'pendiente', 'escalado', 'resuelto', 'descartado'])],
            'tipo' => ['nullable', Rule::in(['todos', 'publicacion', 'persona', 'usuario'])],
            'motivo' => ['nullable', 'string', 'max:150'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('desde'), ['after_or_equal:desde'])],
        ], [
            'formato.required' => 'Elige el formato de exportación (Excel o PDF).',
            'formato.in' => 'El formato debe ser Excel o PDF.',
            'estado.in' => 'El estado elegido no es válido.',
            'tipo.in' => 'El tipo de reporte elegido no es válido.',
            'motivo.max' => 'El motivo es demasiado largo.',
            'desde.date_format' => 'La fecha "desde" no es válida.',
            'hasta.date_format' => 'La fecha "hasta" no es válida.',
            'hasta.after_or_equal' => 'La fecha "hasta" no puede ser anterior a la fecha "desde".',
        ]);

        $filtros = array_filter([
            'estado' => $datos['estado'] ?? null,
            'tipo' => $datos['tipo'] ?? null,
            'motivo' => $datos['motivo'] ?? null,
            'desde' => $datos['desde'] ?? null,
            'hasta' => $datos['hasta'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        $reportes = Reporte::query()
            ->with(['autor', 'administrador', 'escaladoPor'])
            ->orderByDesc('fecha_reporte')
            ->get()
            ->map(fn (Reporte $r) => $this->paraJava($r))
            ->all();

        if (! $reportes) {
            return $this->error('Todavía no hay reportes para exportar.', 404);
        }

        try {
            $respuesta = $java->exportarReportes($datos['formato'], $filtros, $reportes);
        } catch (ServicioJavaNoDisponibleException $e) {
            return $this->error($e->getMessage(), 503);
        }

        if (! $respuesta->successful()) {
            return $this->errorDeJava($respuesta->status(), $respuesta->json('mensaje'));
        }

        $total = (int) $respuesta->header('X-Total-Reportes');

        ModeracionAuditor::registrar('reportes_exportados', 'reporte', null, 'Exportación de reportes', array_merge(
            ['formato' => $datos['formato'], 'cantidad' => $total],
            $filtros
        ));

        return response($respuesta->body(), 200, [
            'Content-Type' => $respuesta->header('Content-Type') ?: 'application/octet-stream',
            'Content-Disposition' => $respuesta->header('Content-Disposition') ?: 'attachment; filename="reportes.'.$datos['formato'].'"',
            'X-Total-Reportes' => (string) $total,
            'Cache-Control' => 'no-store',
        ]);
    }

    /** Traduce lo que respondió Java a un mensaje claro para el administrador. */
    private function errorDeJava(int $status, ?string $mensaje): JsonResponse
    {
        if ($status === 404) {
            return $this->error($mensaje ?: 'No hay reportes que coincidan con los filtros elegidos.', 404);
        }
        if ($status === 400) {
            return $this->error($mensaje ?: 'El servicio de exportación rechazó los datos enviados.', 422);
        }
        if ($status === 401 || $status === 403) {
            Log::error('Exportar reportes: Java rechazó la clave de servicio (revisa ROOMMATCH_SERVICE_KEY en Laravel y en Spring).');

            return $this->error('No se pudo autenticar con el servicio de exportación. Avisa al equipo técnico.', 502);
        }

        Log::error('Exportar reportes: Java respondió con error', ['status' => $status, 'mensaje' => $mensaje]);

        return $this->error('El servicio de exportación falló al generar el archivo. Inténtalo de nuevo.', 502);
    }

    private function error(string $mensaje, int $status): JsonResponse
    {
        return response()->json(['mensaje' => $mensaje], $status);
    }

    /** Solo lo que Java necesita; los nombres coinciden con el ReporteDto de Java. */
    private function paraJava(Reporte $r): array
    {
        $fmt = fn ($fecha) => $fecha ? $fecha->format('Y-m-d\TH:i:s') : null;

        return [
            'id' => $r->id_reporte,
            'tipo' => $r->tipo,
            'estado' => $r->estado_reporte,
            'objetivoTitulo' => $r->objetivo_titulo,
            'motivo' => $r->motivo,
            'descripcion' => $r->descripcion,
            'reportante' => $r->autor->nombre_completo ?? 'Usuario eliminado',
            'gestionadoPor' => $r->administrador->nombre_completo ?? null,
            'escaladoPor' => $r->escaladoPor->nombre_completo ?? null,
            'notaEscalado' => $r->nota_escalado,
            'fecha' => $fmt($r->fecha_reporte),
            'fechaGestion' => $fmt($r->fecha_gestion),
            'fechaEscalado' => $fmt($r->fecha_escalado),
        ];
    }
}
