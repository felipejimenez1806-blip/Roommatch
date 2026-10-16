<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccionModeracion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Registro de auditoría de moderación y administración (solo lectura, solo
 * administradores: Gate 'ver-auditoria').
 */
class AuditoriaController extends Controller
{
    private const ETIQUETAS = [
        'reporte_resuelto' => 'Resolvió un reporte',
        'reporte_descartado' => 'Descartó un reporte',
        'reporte_escalado' => 'Escaló un reporte',
        'reporte_devuelto' => 'Devolvió un reporte a moderación',
        'publicacion_bajada' => 'Bajó una publicación',
        'publicacion_restaurada' => 'Restauró una publicación',
        'persona_bajada' => 'Eliminó un perfil de roomie',
        'mensaje_respondido' => 'Respondió un mensaje de contacto',
        'mensaje_atendido' => 'Marcó un mensaje como atendido',
        'usuario_bloqueado' => 'Bloqueó a un usuario',
        'usuario_desbloqueado' => 'Desbloqueó a un usuario',
        'rol_promovido' => 'Asignó un rol',
        'rol_revocado' => 'Revocó un rol',
        'administrador_creado' => 'Creó un administrador',
    ];

    /**
     * GET /api/admin/auditoria?accion=&rol=&q=&desde=&hasta=&page=
     *  - accion: una clave de ETIQUETAS.
     *  - rol: moderador | admin (el rol con el que actuó).
     *  - q: busca en el nombre de quien actuó y en el título de la entidad.
     *  - desde / hasta: fechas YYYY-MM-DD.
     */
    public function index(Request $request)
    {
        Gate::authorize('ver-auditoria');

        $filtros = $request->validate([
            'accion' => ['nullable', 'string', 'max:50'],
            'rol' => ['nullable', 'in:moderador,admin'],
            'q' => ['nullable', 'string', 'max:100'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $query = AccionModeracion::query()->with('actor:id_usuario,nombre_completo')->orderByDesc('fecha')->orderByDesc('id_accion');

        if (! empty($filtros['accion'])) {
            $query->where('accion', $filtros['accion']);
        }
        if (! empty($filtros['rol'])) {
            $query->where('rol_activo', $filtros['rol']);
        }
        if (! empty($filtros['desde'])) {
            $query->where('fecha', '>=', $filtros['desde'].' 00:00:00');
        }
        if (! empty($filtros['hasta'])) {
            $query->where('fecha', '<=', $filtros['hasta'].' 23:59:59');
        }
        if (! empty($filtros['q'])) {
            $q = $filtros['q'];
            $query->where(function ($w) use ($q) {
                $w->where('entidad_titulo', 'like', "%{$q}%")
                    ->orWhereHas('actor', fn ($a) => $a->where('nombre_completo', 'like', "%{$q}%"));
            });
        }

        $pagina = $query->paginate(25);

        return response()->json([
            'acciones' => collect($pagina->items())->map(fn (AccionModeracion $a) => [
                'id' => $a->id_accion,
                'actor' => $a->actor->nombre_completo ?? 'Cuenta eliminada',
                'rol' => $a->rol_activo,
                'accion' => $a->accion,
                'accion_label' => self::ETIQUETAS[$a->accion] ?? $a->accion,
                'entidad_tipo' => $a->entidad_tipo,
                'entidad_titulo' => $a->entidad_titulo,
                'detalle' => $a->detalle,
                'fecha' => optional($a->fecha)->toIso8601String(),
            ])->values(),
            'etiquetas' => self::ETIQUETAS,
            'pagina' => $pagina->currentPage(),
            'ultima_pagina' => $pagina->lastPage(),
            'total' => $pagina->total(),
        ]);
    }
}
