<?php

namespace App\Policies;

use App\Models\PersonaRoomie;
use App\Models\Usuario;
use App\Support\Permisos;
use Illuminate\Auth\Access\Response;

/**
 * "Bajar" un perfil de roomie es el soft delete (deleted_at) que ya existía
 * en el panel admin. Mismas reglas que PublicacionPolicy: el dueño sigue
 * eliminando el suyo por Api\PersonaRoomieController::destroy().
 */
class PersonaRoomiePolicy
{
    public function bajar(Usuario $usuario, PersonaRoomie $persona): Response
    {
        if (! Permisos::permitido($usuario, 'bajar-roomies')) {
            return Response::deny('Tu rol activo no puede bajar perfiles de roomie.');
        }

        if ($persona->trashed()) {
            return Response::deny('Este perfil ya está bajado.');
        }

        return Response::allow();
    }

    public function restaurar(Usuario $usuario, PersonaRoomie $persona): Response
    {
        if (! Permisos::permitido($usuario, 'bajar-roomies')) {
            return Response::deny('Tu rol activo no puede restaurar perfiles de roomie.');
        }

        if (! $persona->trashed()) {
            return Response::deny('Este perfil no está bajado.');
        }

        return Response::allow();
    }
}
