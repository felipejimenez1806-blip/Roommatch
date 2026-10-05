<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MensajeContacto;
use App\Models\PersonaRoomie;
use App\Models\Publicacion;
use App\Models\Reporte;
use App\Models\Usuario;

class AdminController extends Controller
{
    /**
     * GET /api/admin/resumen
     * Reemplaza a renderResumen() (la parte de las tarjetas de stats)
     * de admin.js. La gráfica vive aparte en ReporteController::grafica().
     *
     * Con el ENUM tipo_usuario reducido a cliente/admin, 'buscadores' y
     * 'oferentes' ya no existen como categorías: se reemplazan por
     * 'clientes' y 'administradores'.
     *
     * 'publicaciones_activas' y 'roomies_publicados' usan Model::count()
     * sin scope extra: con SoftDeletes en Publicacion/PersonaRoomie, el
     * global scope ya excluye los eliminados automáticamente, así que
     * estos conteos son "activos" por definición sin tener que filtrar
     * deleted_at a mano.
     */
    public function resumen()
    {
        return response()->json([
            'usuarios_registrados' => Usuario::count(),
            'clientes' => Usuario::clientes()->count(),
            'administradores' => Usuario::admins()->count(),
            'usuarios_bloqueados' => Usuario::bloqueados()->count(),
            'publicaciones_activas' => Publicacion::count(),
            'roomies_publicados' => PersonaRoomie::count(),
            'reportes_pendientes' => Reporte::pendientes()->count(),
            'mensajes_contacto_pendientes' => MensajeContacto::where('atendido', false)->count(),
        ]);
    }
}
