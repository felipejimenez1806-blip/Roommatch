<?php

namespace App\Services;

use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Única fuente de verdad de la sesión del usuario (cookie de sesión de
 * Laravel). La usan AuthController (login, registro, selección y cambio
 * de rol, logout), SocialAuthController (login social), los middleware de
 * zona y los controladores de administración (al bloquear una cuenta).
 *
 * Qué vive en la sesión del servidor:
 *  - La identidad: la guarda Auth::login() (guard 'web'), no este servicio.
 *  - 'rol_activo': con qué rol entró el usuario ('cliente' | 'admin'). Antes
 *    vivía como ability del token Sanctum (rol:cliente / rol:admin).
 *  - 'login.pendiente': el usuario ya puso bien su contraseña pero tiene más
 *    de un rol y falta que elija con cuál entrar. En ese estado NO está
 *    autenticado: no puede llamar a ninguna ruta protegida. Antes era el
 *    token limitado con ability 'seleccionar-rol'. Vence a los 5 minutos.
 */
class SesionUsuario
{
    public const ROL_ACTIVO = 'rol_activo';
    public const PENDIENTE = 'login.pendiente';
    private const MINUTOS_PENDIENTE = 5;

    // ------------------------------------------------------------------
    // Apertura de sesión
    // ------------------------------------------------------------------

    /**
     * Se llama cuando las credenciales ya fueron verificadas (login,
     * registro, login social).
     *
     * 1 solo rol  -> abre la sesión directamente con ese rol.
     * 2+ roles    -> deja el login "pendiente" hasta que el usuario elija
     *                (POST /api/seleccionar-rol, ver abrir()).
     *
     * Devuelve el fragmento de respuesta que el frontend espera:
     * requiereSeleccionRol, roles y (si ya entró) rolActivo.
     */
    public static function iniciar(Request $request, Usuario $usuario): array
    {
        self::exigirSesionHttp($request);

        $usuario->loadMissing('roles');
        $roles = $usuario->roles->pluck('nombre')->values();

        if ($roles->count() > 1) {
            // Por si el navegador traía otra sesión abierta: mientras el
            // login esté pendiente no debe haber nadie autenticado.
            Auth::guard('web')->logout();

            $request->session()->regenerate();
            $request->session()->put(self::PENDIENTE, [
                'id' => $usuario->getKey(),
                'hasta' => now()->addMinutes(self::MINUTOS_PENDIENTE)->timestamp,
            ]);

            return ['requiereSeleccionRol' => true, 'roles' => $roles];
        }

        $rol = $roles->first() ?? 'cliente';
        self::abrir($request, $usuario, $rol);

        return ['requiereSeleccionRol' => false, 'roles' => $roles, 'rolActivo' => $rol];
    }

    /**
     * Autentica al usuario con el rol elegido. Regenera el id de sesión
     * (evita fijación de sesión). El token CSRF de la página no cambia.
     */
    public static function abrir(Request $request, Usuario $usuario, string $rol): void
    {
        self::exigirSesionHttp($request);

        Auth::guard('web')->login($usuario);

        $request->session()->regenerate();
        $request->session()->forget(self::PENDIENTE);
        $request->session()->put(self::ROL_ACTIVO, $rol);
    }

    /** Cierra la sesión por completo (y rota el token CSRF). */
    public static function cerrar(Request $request): void
    {
        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    // ------------------------------------------------------------------
    // Lectura
    // ------------------------------------------------------------------

    /** Rol con el que entró el usuario en esta sesión, o null. */
    public static function rolActivo(): ?string
    {
        return session(self::ROL_ACTIVO);
    }

    /**
     * Usuario que ya pasó la contraseña pero falta que elija rol, o null si
     * no hay un login pendiente (o venció, o la cuenta ya no es válida).
     */
    public static function pendiente(Request $request): ?Usuario
    {
        if (! $request->hasSession() || ! $request->session()->has(self::PENDIENTE)) {
            return null;
        }

        $pendiente = $request->session()->get(self::PENDIENTE);

        if (! is_array($pendiente) || ($pendiente['hasta'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::PENDIENTE);

            return null;
        }

        $usuario = Usuario::with('roles')->find($pendiente['id'] ?? null);

        if (! $usuario || $usuario->bloqueado) {
            $request->session()->forget(self::PENDIENTE);

            return null;
        }

        return $usuario;
    }

    /** Nombres de los roles entre los que debe elegir (para window.APP). */
    public static function rolesPendientes(Request $request): array
    {
        $usuario = self::pendiente($request);

        return $usuario ? $usuario->roles->pluck('nombre')->values()->all() : [];
    }

    // ------------------------------------------------------------------
    // Validación en cada petición autenticada
    // ------------------------------------------------------------------

    /**
     * Revisa que la cuenta con sesión abierta siga siendo válida. La llama el
     * middleware 'cuenta.activa'. Devuelve null si todo está bien o el
     * mensaje del problema (la sesión ya quedó cerrada).
     *
     *  - Cuenta bloqueada: el bloqueo surte efecto en la siguiente petición,
     *    sin depender de qué driver de sesión se use.
     *  - Rol activo que ya no corresponde (p. ej. un admin le revocó el rol
     *    admin en medio de la sesión): si queda un solo rol se usa ese; si
     *    no, se cierra la sesión.
     */
    public static function problemaDeCuenta(Request $request): ?string
    {
        $usuario = Auth::guard('web')->user();

        if (! $usuario || ! $request->hasSession()) {
            return null;
        }

        if ($usuario->bloqueado) {
            self::cerrar($request);

            return 'Tu cuenta fue bloqueada. Contacta al soporte.';
        }

        $usuario->loadMissing('roles');
        $rol = $request->session()->get(self::ROL_ACTIVO);

        if (! $rol || ! $usuario->tieneRol($rol)) {
            $roles = $usuario->roles->pluck('nombre');

            if ($roles->count() === 1) {
                $request->session()->put(self::ROL_ACTIVO, $roles->first());

                return null;
            }

            self::cerrar($request);

            return 'Tu sesión ya no es válida. Inicia sesión de nuevo.';
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Cierre forzado de las sesiones de otra persona
    // ------------------------------------------------------------------

    /**
     * Cierra de inmediato todas las sesiones abiertas de un usuario (al
     * bloquearlo). Con el driver 'database' borra sus filas de la tabla de
     * sesiones. Con otros drivers no se puede listar sesiones por usuario,
     * pero da igual: 'cuenta.activa' ya lo expulsa en su siguiente petición.
     */
    public static function cerrarSesionesDe(Usuario $usuario): void
    {
        if (config('session.driver') !== 'database') {
            return;
        }

        DB::connection(config('session.connection'))
            ->table(config('session.table', 'sessions'))
            ->where('user_id', $usuario->getKey())
            ->delete();
    }

    // ------------------------------------------------------------------

    /**
     * Las rutas /api/* solo tienen sesión cuando la petición viene del propio
     * sitio (Origin/Referer en SANCTUM_STATEFUL_DOMAINS). Una llamada suelta
     * (curl, Postman) no la tiene: se responde con un error claro en vez de
     * un 500 "Session store not set on request".
     */
    private static function exigirSesionHttp(Request $request): void
    {
        if (! $request->hasSession()) {
            abort(400, 'Esta operación solo está disponible desde la aplicación web.');
        }
    }
}
