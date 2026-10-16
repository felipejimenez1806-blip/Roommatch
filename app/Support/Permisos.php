<?php

namespace App\Support;

use App\Models\Usuario;
use App\Services\SesionUsuario;

/**
 * Mapa único "rol -> permisos". Es la única fuente de verdad de quién puede
 * qué: los Gates (AppServiceProvider), las Policies, el middleware `can:` de
 * las rutas y el window.APP.permisos que ve el frontend salen de aquí.
 *
 * Los permisos se resuelven por el rol ACTIVO de la sesión (el que se eligió
 * en "Ingresar como…"), nunca por la suma de los roles que la cuenta tenga
 * asignados: una cuenta con moderador y admin, entrando como moderador, no
 * puede bloquear usuarios.
 *
 * Para dar o quitar un permiso a un rol solo se edita MAPA; no hay que tocar
 * controladores ni rutas.
 */
class Permisos
{
    public const MAPA = [
        'cliente' => [],

        'moderador' => [
            'gestionar-reportes',           // ver, resolver y descartar reportes
            'escalar-reportes',             // pasar un reporte a un administrador
            'ver-publicaciones-moderacion', // listar publicaciones desde el panel
            'bajar-publicaciones',          // bajar (soft delete) y restaurar publicaciones
            'responder-mensajes',           // responder los mensajes de Contáctanos
        ],

        'admin' => [
            'gestionar-reportes',
            'ver-publicaciones-moderacion',
            'bajar-publicaciones',
            'responder-mensajes',
            // Solo administradores:
            'gestionar-escalados',          // resolver reportes escalados o devolverlos al moderador
            'bloquear-usuarios',            // bloquear y desbloquear cuentas
            'gestionar-roles',              // promover o revocar admin y moderador
            'ver-auditoria',                // consultar el registro de auditoría
        ],
    ];

    /** Todos los permisos que existen (para registrar los Gates). */
    public static function todos(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::MAPA))));
    }

    /** Permisos del rol con el que entró la sesión actual ([] si no hay rol). */
    public static function delRolActivo(): array
    {
        $rol = SesionUsuario::rolActivo();

        return $rol ? (self::MAPA[$rol] ?? []) : [];
    }

    /**
     * ¿Puede este usuario, con el rol activo de la sesión, ejercer el permiso?
     * Además de mirar el rol activo comprueba que la cuenta SIGA teniéndolo en
     * usuario_rol: si otro admin se lo revocó en medio de la sesión, deja de
     * valer de inmediato, aunque la sesión siga abierta.
     */
    public static function permitido(?Usuario $usuario, string $permiso): bool
    {
        if (! $usuario) {
            return false;
        }

        $rol = SesionUsuario::rolActivo();

        if (! $rol || ! $usuario->tieneRol($rol)) {
            return false;
        }

        return in_array($permiso, self::MAPA[$rol] ?? [], true);
    }
}
