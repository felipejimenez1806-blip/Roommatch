<?php

namespace App\Services;

use App\Models\AccionModeracion;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Punto único para dejar registro de lo que hace el equipo de moderación y
 * administración. Lo llaman los controladores justo después de cada acción:
 *
 *   ModeracionAuditor::registrar('publicacion_bajada', 'publicacion', $p->id_publicacion, $p->titulo, ['motivo' => $motivo]);
 *
 * El actor y su rol activo salen siempre de la sesión del servidor, nunca de
 * lo que mande el cliente.
 */
class ModeracionAuditor
{
    public static function registrar(
        string $accion,
        string $entidadTipo,
        ?int $entidadId = null,
        ?string $entidadTitulo = null,
        array $detalle = [],
    ): void {
        try {
            AccionModeracion::create([
                'id_usuario' => Auth::guard('web')->id(),
                'rol_activo' => SesionUsuario::rolActivo() ?? 'desconocido',
                'accion' => $accion,
                'entidad_tipo' => $entidadTipo,
                'entidad_id' => $entidadId,
                'entidad_titulo' => $entidadTitulo !== null ? mb_substr($entidadTitulo, 0, 255) : null,
                'detalle' => $detalle ?: null,
                'fecha' => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría no debe tumbar la acción ya realizada, pero un
            // fallo aquí tiene que quedar a la vista en el log.
            Log::error('No se pudo registrar la acción de moderación', [
                'accion' => $accion,
                'entidad' => "{$entidadTipo}#{$entidadId}",
                'error' => $e->getMessage(),
            ]);
        }
    }
}
