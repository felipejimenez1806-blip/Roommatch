<?php

namespace App\Policies;

use App\Models\Reporte;
use App\Models\Usuario;
use App\Support\Permisos;
use Illuminate\Auth\Access\Response;

/**
 * Reglas sobre un reporte concreto. El permiso "en general" (¿este rol puede
 * gestionar reportes?) vive en Permisos::MAPA; aquí se suma lo que depende del
 * estado del reporte y de su tipo.
 *
 * Flujo de estados:
 *   pendiente -> resuelto | descartado   (admin o moderador)
 *   pendiente -> escalado                (solo moderador)
 *   escalado  -> resuelto | descartado   (solo admin)
 *   escalado  -> pendiente (devolver)    (solo admin)
 */
class ReportePolicy
{
    public function resolver(Usuario $usuario, Reporte $reporte): Response
    {
        $puede = $this->puedeGestionar($usuario, $reporte);
        if (! $puede->allowed()) {
            return $puede;
        }

        // Resolver un reporte de usuario implica bloquear la cuenta, y eso es
        // solo de administradores. El moderador lo escala (o lo descarta).
        if ($reporte->tipo === 'usuario' && ! Permisos::permitido($usuario, 'bloquear-usuarios')) {
            return Response::deny('Solo un administrador puede bloquear usuarios. Escala este reporte a un administrador.');
        }

        return Response::allow();
    }

    public function descartar(Usuario $usuario, Reporte $reporte): Response
    {
        return $this->puedeGestionar($usuario, $reporte);
    }

    public function escalar(Usuario $usuario, Reporte $reporte): Response
    {
        if (! Permisos::permitido($usuario, 'escalar-reportes')) {
            return Response::deny('Tu rol activo no puede escalar reportes.');
        }

        if ($reporte->estado_reporte !== 'pendiente') {
            return Response::deny('Solo se pueden escalar reportes pendientes.');
        }

        return Response::allow();
    }

    public function devolver(Usuario $usuario, Reporte $reporte): Response
    {
        if (! Permisos::permitido($usuario, 'gestionar-escalados')) {
            return Response::deny('Solo un administrador puede devolver un reporte escalado.');
        }

        if ($reporte->estado_reporte !== 'escalado') {
            return Response::deny('Este reporte no está escalado.');
        }

        return Response::allow();
    }

    /**
     * Comparte la lógica de resolver() y descartar(): el reporte debe estar
     * abierto, y uno escalado solo lo cierra un administrador.
     */
    private function puedeGestionar(Usuario $usuario, Reporte $reporte): Response
    {
        if (! Permisos::permitido($usuario, 'gestionar-reportes')) {
            return Response::deny('Tu rol activo no puede gestionar reportes.');
        }

        return match ($reporte->estado_reporte) {
            'pendiente' => Response::allow(),
            'escalado' => Permisos::permitido($usuario, 'gestionar-escalados')
                ? Response::allow()
                : Response::deny('Este reporte fue escalado a un administrador: solo él puede cerrarlo.'),
            default => Response::deny('Este reporte ya fue gestionado.'),
        };
    }
}
