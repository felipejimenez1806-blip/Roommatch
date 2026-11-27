<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\PaseJavaService;
use App\Services\SesionUsuario;
use Illuminate\Http\Request;

/**
 * Entrada de los CLIENTES que inician sesión en Java (Spring).
 *
 * Java verifica el correo y la contraseña (o el login con Google/Facebook) y, si la persona es cliente,
 * la manda aquí con un pase firmado de un solo uso (ver Modelpase.java / PaseJavaService). Este
 * controlador lo comprueba, abre la sesión de Laravel (la misma cookie que el login normal, vía
 * SesionUsuario) y manda a la persona al dashboard. Como la sesión ya existe, el dashboard se pinta
 * con los datos de esa persona (window.APP.user lo arma el servidor, ver partials/app-config).
 *
 * Solo entra con el rol 'cliente': los administradores y moderadores usan los paneles de Java.
 */
class AuthJavaController extends Controller
{
    /** GET /auth/java/entrar?pase=... */
    public function entrar(Request $request, PaseJavaService $pases)
    {
        $idUsuario = $pases->verificar($request->query('pase'));

        if ($idUsuario === null) {
            return $this->noPudoEntrar('El enlace de ingreso no es válido o ya venció. Inicia sesión de nuevo.');
        }

        // El pase solo dice QUIÉN es. Todo lo demás se lee de la base de datos, igual que en un login normal.
        $usuario = Usuario::with('roles')->find($idUsuario);

        if (! $usuario) {
            return $this->noPudoEntrar('No encontramos tu cuenta. Inicia sesión de nuevo.');
        }

        // Misma regla que el login con contraseña y el social: una cuenta bloqueada no entra por ningún camino.
        if ($usuario->bloqueado) {
            return $this->noPudoEntrar(
                $usuario->bloqueado_motivo
                    ? "Tu cuenta fue bloqueada: {$usuario->bloqueado_motivo}"
                    : 'Tu cuenta ha sido bloqueada. Contacta al soporte.'
            );
        }

        if (! $usuario->tieneRol('cliente')) {
            return $this->noPudoEntrar('Esta entrada es solo para clientes.');
        }

        // Si el navegador traía la sesión de otra persona, se cierra por completo antes de abrir esta.
        SesionUsuario::cerrar($request);
        SesionUsuario::abrir($request, $usuario, 'cliente');

        // Igual que SocialAuthController: sin teléfono, primero el onboarding.
        return redirect()->to(url($usuario->perfilCompleto() ? '/dashboard' : '/onboarding'));
    }

    private function noPudoEntrar(string $mensaje)
    {
        return response()->view('auth.oauth-error', ['mensaje' => $mensaje], 403);
    }
}
