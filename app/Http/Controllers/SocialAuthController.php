<?php

namespace App\Http\Controllers;

use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    protected array $proveedoresPermitidos = ['google', 'facebook'];

    /**
     * Redirige al usuario al proveedor OAuth (Google/Facebook).
     */
    public function redirect(string $proveedor)
    {
        $this->validarProveedor($proveedor);

        return Socialite::driver($proveedor)->redirect();
    }

    /**
     * Recibe la respuesta del proveedor, crea o reutiliza el usuario,
     * genera un token Sanctum y entrega una vista puente que guarda
     * ese token en sessionStorage antes de continuar en el frontend.
     */
    public function callback(string $proveedor)
    {
        $this->validarProveedor($proveedor);

        try {
            $usuarioSocial = Socialite::driver($proveedor)->user();
        } catch (\Throwable $e) {
            Log::warning("Fallo en callback de {$proveedor}: {$e->getMessage()}");

            return view('auth.oauth-error', [
                'mensaje' => 'No se pudo completar el inicio de sesión. Intenta de nuevo.',
            ]);
        }

        $usuario = Usuario::where('proveedor', $proveedor)
            ->where('proveedor_id', $usuarioSocial->getId())
            ->first();

        if (!$usuario) {
            $usuario = Usuario::where('correo', $usuarioSocial->getEmail())->first();

            if ($usuario) {
                // Ya existe una cuenta (local o de otro proveedor) con este
                // correo: la vinculamos a este proveedor en vez de bloquear.
                // La contraseña (si tenía) se conserva tal cual, así que
                // esa persona puede seguir entrando con ella si tu login
                // por contraseña no filtra por proveedor = 'local'.
                $usuario->proveedor = $proveedor;
                $usuario->proveedor_id = $usuarioSocial->getId();
                $usuario->save();
            } else {
                $usuario = new Usuario();
                $usuario->nombre_completo = $usuarioSocial->getName() ?? $usuarioSocial->getNickname() ?? 'Usuario Roommatch';
                $usuario->correo = $usuarioSocial->getEmail();
                $usuario->proveedor = $proveedor;
                $usuario->proveedor_id = $usuarioSocial->getId();
                $usuario->contrasena = null;
                $usuario->save();
            }
        }

        $usuario->load('roles');

        // Salvavidas: cualquier cuenta que llegue hasta aquí sin NINGÚN
        // rol —cuenta nueva recién creada arriba, cuenta vieja de antes
        // de que existiera el sistema de roles, o cualquier otro caso
        // que no hayamos previsto— recibe 'cliente' aquí mismo, en vez
        // de depender de acertarle a cada rama del código de arriba.
        // Esto es lo mismo que causó el bug: cuentas sociales quedando
        // sin ningún rol asignado en usuario_rol.
        if ($usuario->roles->isEmpty()) {
            $rolCliente = Rol::where('nombre', 'cliente')->firstOrFail();

            $usuario->roles()->attach($rolCliente->id_rol, [
                'fecha_asignacion' => now(),
                'asignado_por' => null,
            ]);

            $usuario->load('roles');
        }

        $emision = $usuario->emitirTokenSesion();

        return view('auth.oauth-callback', array_merge($emision, [
            'usuario' => $usuario->paraFrontend(),
            'redirectTo' => $usuario->perfilCompleto() ? '/dashboard' : '/onboarding',
        ]));
    }

    protected function validarProveedor(string $proveedor): void
    {
        if (!in_array($proveedor, $this->proveedoresPermitidos, true)) {
            abort(404);
        }
    }
}
