<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambiarRolRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegistroRequest;
use App\Http\Requests\SeleccionarRolRequest;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    private const MAX_INTENTOS_LOGIN = 5;

    /**
     * POST /api/registro
     * Equivalente al submit de signup.js, pero validando y
     * guardando en MySQL real en vez de localStorage.
     *
     * El rol ya NO se recibe del formulario: todo registro público nace
     * con el rol 'cliente' únicamente (asignado aquí mismo tras crear la
     * fila en usuario). 'admin' solo se asigna manualmente en BD o desde
     * el panel de administración (Fase 3).
     *
     * terminos_aceptados_en: RegistroRequest ya exige que el checkbox
     * llegue marcado (acepta_terminos => accepted), así que si la petición
     * pasó la validación, aceptó los términos justo ahora.
     */
    public function registro(RegistroRequest $request)
    {
        $datos = $request->validated();

        $usuario = DB::transaction(function () use ($datos) {
            $usuario = Usuario::create([
                'nombre_completo' => $datos['nombre'],
                'correo' => $datos['email'],
                'telefono' => $datos['telefono'],
                'direccion' => $datos['direccion'],
                'contrasena' => Hash::make($datos['password']),
                'proveedor' => 'local',
                'terminos_aceptados_en' => now(),
            ]);

            $rolCliente = Rol::where('nombre', 'cliente')->firstOrFail();

            $usuario->roles()->attach($rolCliente->id_rol, [
                'fecha_asignacion' => now(),
                'asignado_por' => null,
            ]);

            return $usuario;
        });

        $usuario->load('roles');
        $emision = $usuario->emitirTokenSesion();

        return response()->json(array_merge([
            'mensaje' => '¡Cuenta creada con éxito!',
            'usuario' => $usuario->paraFrontend(),
        ], $emision), 201);
    }

    /**
     * POST /api/login
     * Equivalente al submit de login.js. No filtra por 'proveedor':
     * una cuenta que empezó local y luego se vinculó con Google/Facebook
     * (ver SocialAuthController) sigue pudiendo entrar aquí con su
     * contraseña mientras 'contrasena' no sea null.
     *
     * Bloqueo automático: tras MAX_INTENTOS_LOGIN contraseñas incorrectas
     * seguidas, la cuenta se marca bloqueado = true (mismo campo que usa
     * el panel de admin), y solo un admin puede desbloquearla desde ahí
     * (AdminUsuarioController@desbloquear). El mensaje de error se
     * mantiene genérico mientras la cuenta no esté bloqueada, para no
     * revelar por intentos si un correo existe o no en la base de datos.
     *
     * Selección de rol: ver Usuario::emitirTokenSesion().
     */
    public function login(LoginRequest $request)
    {
        $usuario = Usuario::where('correo', $request->email)->first();

        $credencialesValidas = $usuario
            && $usuario->contrasena
            && Hash::check($request->password, $usuario->contrasena);

        if (! $credencialesValidas) {
            // Solo contamos el intento fallido si es una cuenta local real
            // y todavía no está bloqueada (para no seguir incrementando
            // de más una vez ya bloqueada).
            if ($usuario && $usuario->contrasena && ! $usuario->bloqueado) {
                $usuario->increment('intentos_fallidos');

                if ($usuario->intentos_fallidos >= self::MAX_INTENTOS_LOGIN) {
                    $usuario->update([
                        'bloqueado' => true,
                        'bloqueado_motivo' => 'Bloqueo automático por demasiados intentos fallidos de inicio de sesión.',
                        'bloqueado_fecha' => now(),
                    ]);
                }
            }

            return response()->json([
                'mensaje' => 'Datos incorrectos vuelva a intentar.',
            ], 401);
        }

        if ($usuario->bloqueado) {
            return response()->json([
                'mensaje' => $usuario->bloqueado_motivo
                    ? "Tu cuenta fue bloqueada: {$usuario->bloqueado_motivo}"
                    : 'Tu cuenta ha sido bloqueada. Contacta al soporte.',
            ], 403);
        }

        // Login correcto: reiniciamos el contador si venía con intentos previos.
        if ($usuario->intentos_fallidos > 0) {
            $usuario->update(['intentos_fallidos' => 0]);
        }

        $usuario->load('roles');
        $emision = $usuario->emitirTokenSesion();

        return response()->json(array_merge([
            'mensaje' => '¡Bienvenido a RoomMatch!',
            'usuario' => $usuario->paraFrontend(),
        ], $emision));
    }

    /**
     * POST /api/seleccionar-rol
     * Canjea el token limitado (ability seleccionar-rol, emitido por
     * Usuario::emitirTokenSesion() cuando el usuario tiene más de un
     * rol) por el token real de la sesión. Protegido por el middleware
     * RequiereSeleccionRol.
     *
     * No vuelve a pedir contraseña: el usuario la acaba de escribir hace
     * segundos para llegar aquí desde login(). Comparar con cambiarRol(),
     * que sí la vuelve a pedir para pasar a admin porque puede ocurrir
     * mucho después, en medio de una sesión ya abierta.
     */
    public function seleccionarRol(SeleccionarRolRequest $request)
    {
        $usuario = $request->user();
        $rol = $request->validated('rol');

        if (! $usuario->tieneRol($rol)) {
            abort(403, 'No tienes asignado ese rol.');
        }

        // El token limitado ya cumplió su propósito; se revoca antes de
        // emitir el real para que no quede vivo un token de un solo uso.
        $request->user()->currentAccessToken()->delete();

        $token = $usuario->createToken('roommatch-token', ["rol:{$rol}"])->plainTextToken;

        return response()->json([
            'mensaje' => "Ingresaste como {$rol}.",
            'token' => $token,
            'rolActivo' => $rol,
            'usuario' => $usuario->paraFrontend(),
        ]);
    }

    /**
     * POST /api/cambiar-rol
     * Permite, desde una sesión ya abierta, cambiar entre los roles que
     * el usuario tenga asignados. Exige reingresar la contraseña
     * únicamente cuando el rol destino es 'admin' (ver CambiarRolRequest).
     */
    public function cambiarRol(CambiarRolRequest $request)
    {
        $usuario = $request->user();
        $rolDestino = $request->validated('rol');

        if (! $usuario->tieneRol($rolDestino)) {
            abort(403, 'No tienes asignado ese rol.');
        }

        if ($rolDestino === 'admin') {
            $contrasenaValida = $usuario->contrasena
                && Hash::check($request->validated('password'), $usuario->contrasena);

            if (! $contrasenaValida) {
                return response()->json([
                    'mensaje' => 'Contraseña incorrecta.',
                ], 401);
            }
        }

        $request->user()->currentAccessToken()->delete();

        $token = $usuario->createToken('roommatch-token', ["rol:{$rolDestino}"])->plainTextToken;

        return response()->json([
            'mensaje' => "Ahora estás como {$rolDestino}.",
            'token' => $token,
            'rolActivo' => $rolDestino,
            'usuario' => $usuario->paraFrontend(),
        ]);
    }

    /**
     * POST /api/agregar-rol-cliente
     * Autoservicio: cualquier usuario autenticado (en la práctica, un
     * admin creado sin rol cliente vía crearAdministrador()) puede
     * agregarse el rol 'cliente' a sí mismo, sin pedir contraseña ni
     * aprobación de otro admin — a diferencia de volverse admin, esto
     * NO es una elevación de privilegios, así que no necesita el mismo
     * rigor. No quita ningún rol que ya tenga (un admin que hace esto
     * sigue siendo admin, ahora también cliente).
     */
    public function agregarRolCliente(Request $request)
    {
        $usuario = $request->user();

        if ($usuario->tieneRol('cliente')) {
            return response()->json([
                'mensaje' => 'Ya tienes el rol de cliente.',
            ], 409);
        }

        $rolCliente = Rol::where('nombre', 'cliente')->firstOrFail();

        $usuario->roles()->attach($rolCliente->id_rol, [
            'fecha_asignacion' => now(),
            'asignado_por' => $usuario->id_usuario,
        ]);

        $usuario->load('roles');

        return response()->json([
            'mensaje' => 'Ahora también tienes el rol de cliente.',
            'usuario' => $usuario->paraFrontend(),
        ]);
    }

    /**
     * POST /api/logout
     * Revoca el token actual (requiere ir autenticado con Sanctum).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['mensaje' => 'Sesión cerrada correctamente.']);
    }

    /**
     * GET /api/usuario
     * Devuelve el usuario autenticado actual (para restaurar sesión al
     * recargar la página, verificando el token guardado en el frontend),
     * junto con el rol activo del token actual.
     */
    public function usuarioActual(Request $request)
    {
        $usuario = $request->user()->load('roles');

        return response()->json([
            'usuario' => $usuario->paraFrontend(),
            'rolActivo' => $this->rolActivo($usuario),
        ]);
    }

    // ------------------- Helper privado -------------------

    /**
     * Lee la ability rol:<rol> del token con el que llegó la petición
     * actual, para poder devolver el rol activo sin guardarlo aparte.
     */
    private function rolActivo(Usuario $usuario): ?string
    {
        $token = $usuario->currentAccessToken();

        if (! $token) {
            return null;
        }

        foreach ($token->abilities as $ability) {
            if (str_starts_with($ability, 'rol:')) {
                return substr($ability, 4);
            }
        }

        return null;
    }
}
