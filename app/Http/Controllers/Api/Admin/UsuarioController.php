<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ModeracionAuditor;
use App\Services\SesionUsuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UsuarioController extends Controller
{
    /**
     * Correo de la cuenta de sistema sembrada por AdminUsuarioSeeder.
     * No se puede revocar su rol admin desde el panel.
     */
    private const CORREO_ADMIN_SISTEMA = 'roommatch.admin2026@gmail.com';

    /**
     * GET /api/admin/usuarios?filtro=todos|cliente|moderador|admin|bloqueado&q=...
     * Reemplaza a usuariosFiltrados()/renderUsuarios() de admin.js, pero
     * filtrando en el servidor en vez de sobre el array completo.
     *
     * Con roles N:N, 'cliente' y 'admin' ya no son excluyentes: el filtro
     * ahora significa "tiene ese rol asignado" (ver Usuario::scopeClientes/
     * scopeAdmins), no "su único rol es ese".
     */
    public function index(Request $request)
    {
        $query = Usuario::query()->with('roles');

        $filtro = $request->input('filtro', 'todos');
        if ($filtro === 'bloqueado') {
            $query->bloqueados();
        } elseif ($filtro === 'cliente') {
            $query->clientes();
        } elseif ($filtro === 'moderador') {
            $query->moderadores();
        } elseif ($filtro === 'admin') {
            $query->admins();
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($w) use ($q) {
                $w->where('nombre_completo', 'like', "%{$q}%")
                    ->orWhere('correo', 'like', "%{$q}%");
            });
        }

        $usuarios = $query->orderBy('nombre_completo')->get()
            ->map(fn (Usuario $u) => $this->paraTablaAdmin($u));

        return response()->json(['usuarios' => $usuarios]);
    }

    /**
     * POST /api/admin/usuarios
     * Única vía (junto con AdminUsuarioSeeder) para crear una cuenta que
     * nace con un rol de equipo ya asignado ('admin' o 'moderador', según
     * 'tipo_usuario') — no existe ningún formulario público que lo permita.
     * Por ser una acción que otorga privilegios elevados, tiene dos capas
     * de rigor extra frente al registro normal:
     *
     *   1. Pide 'area' y 'cargo' (obligatorios) — contexto organizacional
     *      que no aplica a un cliente y por eso no está en RegistroRequest.
     *   2. Exige que el admin que está creando la cuenta reingrese SU
     *      PROPIA contraseña actual ('password_actual'). Esto evita que
     *      una sesión robada (sin conocer la contraseña real) sea
     *      suficiente para mintar cuentas con privilegios.
     *
     * La contraseña de la cuenta nueva también es más exigente que la de un
     * registro normal: mínimo 10 caracteres (vs. 8) además de mayúscula,
     * minúscula, número y símbolo.
     *
     * La cuenta nueva SOLO recibe el rol elegido (no 'cliente'): es una
     * cuenta creada específicamente para administrar o moderar, igual que
     * la sembrada por AdminUsuarioSeeder. Si después necesita el sitio de
     * cliente, usa "Usar también como cliente" (agregar-rol-cliente).
     *
     * Solo administradores: la ruta lleva 'es.admin' y 'can:gestionar-roles'
     * (crear una cuenta con rol equivale a asignar ese rol).
     */
    public function crearUsuario(Request $request)
    {
        Gate::authorize('gestionar-roles');

        $admin = $request->user();

        $datos = $request->validate([
            'tipo_usuario' => ['required', 'in:admin,moderador'],
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'correo' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'area' => ['required', 'string', 'min:2', 'max:100'],
            'cargo' => ['required', 'string', 'min:2', 'max:100'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->mixedCase()->numbers()->symbols()],
            'password_actual' => ['required', 'string'],
        ], [
            'tipo_usuario.required' => 'Selecciona el tipo de usuario.',
            'tipo_usuario.in' => 'Selecciona un tipo de usuario válido.',
            'nombre.required' => 'Ingresa el nombre completo.',
            'nombre.min' => 'Ingresa el nombre completo.',
            'correo.required' => 'Ingresa un correo.',
            'correo.email' => 'Ingresa un correo válido.',
            'correo.unique' => 'Ya existe una cuenta con este correo.',
            'area.required' => 'Ingresa el área en la que trabaja.',
            'cargo.required' => 'Ingresa el cargo.',
            'password.required' => 'Ingresa una contraseña para la nueva cuenta.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password_actual.required' => 'Confirma tu propia contraseña para autorizar esta acción.',
        ]);

        if (! Hash::check($datos['password_actual'], $admin->contrasena)) {
            return response()->json([
                'mensaje' => 'Tu contraseña actual no es correcta.',
                'errors' => ['password_actual' => ['Tu contraseña actual no es correcta.']],
            ], 422);
        }

        $nombreRol = $datos['tipo_usuario'];

        $nuevoUsuario = DB::transaction(function () use ($datos, $admin, $nombreRol) {
            $nuevoUsuario = Usuario::create([
                'nombre_completo' => $datos['nombre'],
                'correo' => $datos['correo'],
                'area' => $datos['area'],
                'cargo' => $datos['cargo'],
                'contrasena' => Hash::make($datos['password']),
                'proveedor' => 'local',
            ]);

            $rol = Rol::where('nombre', $nombreRol)->firstOrFail();

            $nuevoUsuario->roles()->attach($rol->id_rol, [
                'fecha_asignacion' => now(),
                'asignado_por' => $admin->id_usuario,
            ]);

            return $nuevoUsuario;
        });

        $esAdmin = $nombreRol === 'admin';

        ModeracionAuditor::registrar(
            $esAdmin ? 'administrador_creado' : 'moderador_creado',
            'usuario',
            $nuevoUsuario->id_usuario,
            $nuevoUsuario->nombre_completo,
            ['area' => $datos['area'], 'cargo' => $datos['cargo']]
        );

        $etiqueta = $esAdmin ? 'administrador' : 'moderador';

        return response()->json([
            'mensaje' => "{$nuevoUsuario->nombre_completo} fue creado como {$etiqueta}.",
            'usuario' => $this->paraTablaAdmin($nuevoUsuario),
        ], 201);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/promover-admin
     * Asigna el rol 'admin' a un usuario ya existente (que puede seguir
     * teniendo 'cliente' a la vez — no se le quita). Exige reingresar la
     * contraseña del admin que autoriza, mismo criterio que
     * crearUsuario().
     *
     * Las cuentas de login social sin contraseña local (proveedor
     * google/facebook, 'contrasena' NULL) no pueden promoverse: no hay
     * forma de que esa cuenta use luego el flujo de "Cambiar de rol"
     * hacia admin, que exige reingresar contraseña.
     */
    public function promoverAdmin(Request $request, Usuario $usuario)
    {
        Gate::authorize('gestionar-roles');

        $admin = $request->user();

        $datos = $request->validate([
            'password_actual' => ['required', 'string'],
        ], [
            'password_actual.required' => 'Confirma tu propia contraseña para autorizar esta acción.',
        ]);

        if (! Hash::check($datos['password_actual'], $admin->contrasena)) {
            return response()->json([
                'mensaje' => 'Tu contraseña actual no es correcta.',
                'errors' => ['password_actual' => ['Tu contraseña actual no es correcta.']],
            ], 422);
        }

        if ($usuario->esAdmin()) {
            return response()->json([
                'mensaje' => "{$usuario->nombre_completo} ya es administrador.",
            ], 409);
        }

        if (! $usuario->contrasena) {
            return response()->json([
                'mensaje' => 'Las cuentas de acceso social sin contraseña local no pueden promoverse a administrador.',
            ], 422);
        }

        $rolAdmin = Rol::where('nombre', 'admin')->firstOrFail();

        $usuario->roles()->syncWithoutDetaching([
            $rolAdmin->id_rol => [
                'fecha_asignacion' => now(),
                'asignado_por' => $admin->id_usuario,
            ],
        ]);

        ModeracionAuditor::registrar('rol_promovido', 'usuario', $usuario->id_usuario, $usuario->nombre_completo, ['rol' => 'admin']);

        return response()->json([
            'mensaje' => "{$usuario->nombre_completo} ahora es administrador.",
            'usuario' => $this->paraTablaAdmin($usuario->fresh()),
        ]);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/revocar-admin
     * Quita el rol 'admin' (si tenía también 'cliente', lo conserva).
     * No pide contraseña: quitar un privilegio es una acción de menor
     * riesgo que otorgarlo (mismo criterio que cambiar-rol, que solo
     * pide contraseña al ir HACIA admin, no al salir).
     *
     * Dos resguardos:
     *   - Un admin no puede revocarse el rol a sí mismo (evita quedar
     *     bloqueado fuera del panel por accidente).
     *   - No se puede revocar el rol de la cuenta de sistema sembrada
     *     ("Administrador RoomMatch"): siempre debe quedar al menos un
     *     admin garantizado.
     */
    public function revocarAdmin(Request $request, Usuario $usuario)
    {
        Gate::authorize('gestionar-roles');

        $admin = $request->user();

        if ($usuario->id_usuario === $admin->id_usuario) {
            abort(422, 'No puedes revocarte el rol de administrador a ti mismo.');
        }

        if ($usuario->correo === self::CORREO_ADMIN_SISTEMA) {
            abort(422, 'No se puede revocar el rol de la cuenta de sistema "Administrador RoomMatch".');
        }

        if (! $usuario->esAdmin()) {
            return response()->json([
                'mensaje' => "{$usuario->nombre_completo} no tiene el rol de administrador.",
            ], 409);
        }

        $rolAdmin = Rol::where('nombre', 'admin')->firstOrFail();
        $usuario->roles()->detach($rolAdmin->id_rol);

        ModeracionAuditor::registrar('rol_revocado', 'usuario', $usuario->id_usuario, $usuario->nombre_completo, ['rol' => 'admin']);

        return response()->json([
            'mensaje' => "Se revocó el rol de administrador a {$usuario->nombre_completo}.",
            'usuario' => $this->paraTablaAdmin($usuario->fresh()),
        ]);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/promover-moderador
     * Asigna el rol 'moderador' a un usuario ya existente. Conserva los roles
     * que ya tenga (cliente y/o admin). Solo administradores (ruta con
     * `can:gestionar-roles`). Mismo rigor que promoverAdmin(): exige la
     * contraseña del admin que autoriza, y rechaza cuentas de acceso social
     * sin contraseña local (no podrían reingresar la contraseña al cambiar
     * de rol hacia moderador).
     */
    public function promoverModerador(Request $request, Usuario $usuario)
    {
        Gate::authorize('gestionar-roles');

        $admin = $request->user();

        $request->validate([
            'password_actual' => ['required', 'string'],
        ], [
            'password_actual.required' => 'Confirma tu propia contraseña para autorizar esta acción.',
        ]);

        if (! Hash::check($request->input('password_actual'), $admin->contrasena)) {
            return response()->json([
                'mensaje' => 'Tu contraseña actual no es correcta.',
                'errors' => ['password_actual' => ['Tu contraseña actual no es correcta.']],
            ], 422);
        }

        if ($usuario->esModerador()) {
            return response()->json([
                'mensaje' => "{$usuario->nombre_completo} ya es moderador.",
            ], 409);
        }

        if ($usuario->bloqueado) {
            return response()->json([
                'mensaje' => 'No se puede asignar un rol a una cuenta bloqueada.',
            ], 422);
        }

        if (! $usuario->contrasena) {
            return response()->json([
                'mensaje' => 'Las cuentas de acceso social sin contraseña local no pueden promoverse a moderador.',
            ], 422);
        }

        $rolModerador = Rol::where('nombre', 'moderador')->firstOrFail();

        $usuario->roles()->syncWithoutDetaching([
            $rolModerador->id_rol => [
                'fecha_asignacion' => now(),
                'asignado_por' => $admin->id_usuario,
            ],
        ]);

        ModeracionAuditor::registrar('rol_promovido', 'usuario', $usuario->id_usuario, $usuario->nombre_completo, ['rol' => 'moderador']);

        return response()->json([
            'mensaje' => "{$usuario->nombre_completo} ahora es moderador.",
            'usuario' => $this->paraTablaAdmin($usuario->fresh()),
        ]);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/revocar-moderador
     * Quita el rol 'moderador' (conserva cliente y/o admin). No pide
     * contraseña: quitar un privilegio es de menor riesgo que otorgarlo. Si la
     * persona tiene una sesión abierta como moderador, el middleware
     * 'cuenta.activa' la saca (o la pasa a su único rol restante) en la
     * siguiente petición.
     */
    public function revocarModerador(Request $request, Usuario $usuario)
    {
        Gate::authorize('gestionar-roles');

        if (! $usuario->esModerador()) {
            return response()->json([
                'mensaje' => "{$usuario->nombre_completo} no tiene el rol de moderador.",
            ], 409);
        }

        $rolModerador = Rol::where('nombre', 'moderador')->firstOrFail();
        $usuario->roles()->detach($rolModerador->id_rol);

        ModeracionAuditor::registrar('rol_revocado', 'usuario', $usuario->id_usuario, $usuario->nombre_completo, ['rol' => 'moderador']);

        return response()->json([
            'mensaje' => "Se revocó el rol de moderador a {$usuario->nombre_completo}.",
            'usuario' => $this->paraTablaAdmin($usuario->fresh()),
        ]);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/bloquear
     */
    public function bloquear(Request $request, Usuario $usuario)
    {
        Gate::authorize('bloquear-usuarios');

        if ($usuario->esAdmin()) {
            abort(422, 'No se puede bloquear una cuenta de administrador.');
        }

        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $usuario->update([
            'bloqueado' => true,
            'bloqueado_motivo' => $datos['motivo'] ?: 'Sin motivo especificado',
            'bloqueado_fecha' => now(),
        ]);

        // Cierra las sesiones abiertas de inmediato (con el driver 'database'
        // borra sus filas de la tabla de sesiones). Aunque el driver no lo
        // permita, el middleware 'cuenta.activa' lo expulsa en su siguiente
        // petición: el bloqueo no depende de este borrado.
        SesionUsuario::cerrarSesionesDe($usuario);

        ModeracionAuditor::registrar('usuario_bloqueado', 'usuario', $usuario->id_usuario, $usuario->nombre_completo, [
            'via' => 'panel',
            'motivo' => $usuario->bloqueado_motivo,
        ]);

        return response()->json(['mensaje' => "{$usuario->nombre_completo} fue bloqueado."]);
    }

    /**
     * PATCH /api/admin/usuarios/{usuario}/desbloquear
     */
    public function desbloquear(Usuario $usuario)
    {
        Gate::authorize('bloquear-usuarios');

        $usuario->update([
            'bloqueado' => false,
            'bloqueado_motivo' => null,
            'bloqueado_fecha' => null,
        ]);

        ModeracionAuditor::registrar('usuario_desbloqueado', 'usuario', $usuario->id_usuario, $usuario->nombre_completo);

        return response()->json(['mensaje' => "{$usuario->nombre_completo} fue desbloqueado."]);
    }

    // ------------------- Helper privado -------------------

    /**
     * Shape común para index()/crearUsuario()/promoverAdmin()/
     * revocarAdmin(). Incluye 'roles' (arreglo completo, para cuando
     * hagamos la Fase 4 de frontend) y además 'tipoUsuario' como shim
     * transicional: admin.js todavía pinta un solo badge y decide
     * mostrar el botón de bloquear/promover a partir de ese campo, así
     * que mientras no se actualice el frontend a mostrar ambos roles a
     * la vez, se prioriza 'admin' si el usuario lo tiene.
     */
    private function paraTablaAdmin(Usuario $usuario): array
    {
        $usuario->loadMissing('roles');
        $esAdmin = $usuario->esAdmin();

        return [
            'id' => $usuario->id_usuario,
            'nombre' => $usuario->nombre_completo,
            'email' => $usuario->correo,
            'roles' => $usuario->roles->pluck('nombre')->values(),
            'esModerador' => $usuario->esModerador(),
            'tipoUsuario' => $esAdmin ? 'admin' : 'cliente',
            'telefono' => $usuario->telefono,
            'fotoPerfil' => $usuario->foto_perfil,
            'bloqueado' => (bool) $usuario->bloqueado,
        ];
    }
}
