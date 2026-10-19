<?php

namespace App\Policies;

use App\Models\Publicacion;
use App\Models\Usuario;
use App\Support\Permisos;
use Illuminate\Auth\Access\Response;

/**
 * "Bajar" una publicación es el soft delete que ya existía en el panel admin
 * (deleted_at). Estas reglas son las de moderación: el dueño sigue
 * eliminando las suyas por Api\PublicacionController::destroy().
 */
class PublicacionPolicy
{
    public function bajar(Usuario $usuario, Publicacion $publicacion): Response
    {
        if (! Permisos::permitido($usuario, 'bajar-publicaciones')) {
            return Response::deny('Tu rol activo no puede bajar publicaciones.');
        }

        if ($publicacion->trashed()) {
            return Response::deny('Esta publicación ya está bajada.');
        }

        return Response::allow();
    }

    public function restaurar(Usuario $usuario, Publicacion $publicacion): Response
    {
        if (! Permisos::permitido($usuario, 'bajar-publicaciones')) {
            return Response::deny('Tu rol activo no puede restaurar publicaciones.');
        }

        if (! $publicacion->trashed()) {
            return Response::deny('Esta publicación no está bajada.');
        }

        return Response::allow();
    }
}
