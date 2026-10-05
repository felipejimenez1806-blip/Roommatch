<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notificacion;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * GET /api/notificaciones
     */
    public function index(Request $request)
    {
        $notificaciones = Notificacion::where('id_usuario', $request->user()->id_usuario)
            ->orderByDesc('fecha')
            ->get();

        return response()->json(['notificaciones' => $notificaciones]);
    }

    /**
     * POST /api/notificaciones/marcar-leidas
     */
    public function marcarLeidas(Request $request)
    {
        Notificacion::where('id_usuario', $request->user()->id_usuario)
            ->where('leida', false)
            ->update(['leida' => true]);

        return response()->json(['mensaje' => 'Notificaciones marcadas como leídas.']);
    }
}
