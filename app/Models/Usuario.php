<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $table = 'usuario';
    protected $primaryKey = 'id_usuario';
    public $timestamps = false;

    const CREATED_AT = 'fecha_registro';

    protected $fillable = [
        'area',
        'cargo',
        'nombre_completo',
        'correo',
        'contrasena',
        'proveedor',
        'proveedor_id',
        'telefono',
        'direccion',
        'genero',
        'foto_perfil',
        'preferencias_convivencia',
        'bloqueado',
        'bloqueado_motivo',
        'bloqueado_fecha',
        'intentos_fallidos',
        'terminos_aceptados_en',

    ];

    protected $hidden = [
        'contrasena',
    ];

    protected $casts = [
        'preferencias_convivencia' => 'array',
        'bloqueado' => 'boolean',
        'bloqueado_fecha' => 'datetime',
        'fecha_registro' => 'datetime',
        'terminos_aceptados_en' => 'datetime',

    ];

    /**
     * Laravel Auth espera getAuthPassword(); nuestra columna se llama 'contrasena'.
     */
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    // ------------------- Relaciones -------------------

    public function publicaciones()
    {
        return $this->hasMany(Publicacion::class, 'id_usuario', 'id_usuario');
    }

    public function perfilRoomie()
    {
        return $this->hasOne(PersonaRoomie::class, 'id_usuario', 'id_usuario');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'id_usuario', 'id_usuario');
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'id_usuario', 'id_usuario');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_usuario', 'id_usuario');
    }

    public function citas()
    {
        return $this->hasMany(Cita::class, 'id_usuario', 'id_usuario');
    }

    public function reportesRealizados()
    {
        return $this->hasMany(Reporte::class, 'id_usuario_reporta', 'id_usuario');
    }

    public function reportesRecibidos()
    {
        return $this->hasMany(Reporte::class, 'id_usuario_reportado', 'id_usuario');
    }

    public function reportesGestionados()
    {
        return $this->hasMany(Reporte::class, 'id_administrador', 'id_usuario');
    }

    public function notificaciones()
    {
        return $this->hasMany(Notificacion::class, 'id_usuario', 'id_usuario');
    }

    /**
     * Roles N:N (cliente/admin). Un usuario puede tener uno o ambos a la
     * vez (ej. un cliente promovido a administrador).
     */
    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'usuario_rol', 'id_usuario', 'id_rol')
            ->withPivot(['fecha_asignacion', 'asignado_por']);
    }

    // ------------------- Helpers de rol -------------------

    public function tieneRol(string $nombreRol): bool
    {
        return $this->roles->contains('nombre', $nombreRol);
    }

    public function esAdmin(): bool
    {
        return $this->tieneRol('admin');
    }

    public function esCliente(): bool
    {
        return $this->tieneRol('cliente');
    }

    /**
     * Única fuente de emisión de token de sesión: la usan tanto
     * AuthController (login/registro) como SocialAuthController
     * (login social), para no duplicar esta lógica ni arriesgar que
     * una de las dos rutas emita un token sin abilities de rol (lo que
     * pasaba antes en el login social: createToken() sin abilities
     * emite un token con ability '*', que EsAdmin habría aceptado como
     * 'rol:admin' sin serlo de verdad).
     *
     * 1 solo rol -> token real directo con ability rol:<rol>.
     * 2 roles -> token limitado con ability seleccionar-rol; el
     * frontend debe mostrar "Ingresar como…" y llamar a
     * POST /api/seleccionar-rol para canjearlo por el real.
     */
    public function emitirTokenSesion(): array
    {
        $this->loadMissing('roles');
        $roles = $this->roles->pluck('nombre')->values();

        if ($roles->count() > 1) {
            $token = $this->createToken('roommatch-seleccion-rol', ['seleccionar-rol'])->plainTextToken;

            return [
                'token' => $token,
                'requiereSeleccionRol' => true,
                'roles' => $roles,
            ];
        }

        $rolUnico = $roles->first() ?? 'cliente';
        $token = $this->createToken('roommatch-token', ["rol:{$rolUnico}"])->plainTextToken;

        return [
            'token' => $token,
            'requiereSeleccionRol' => false,
            'roles' => $roles,
            'rolActivo' => $rolUnico,
        ];
    }

    // ------------------- Scopes útiles -------------------

    public function scopeAdmins($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('nombre', 'admin'));
    }

    public function scopeClientes($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('nombre', 'cliente'));
    }

    public function scopeBloqueados($query)
    {
        return $query->where('bloqueado', true);
    }

    /**
     * 'roles' es el arreglo completo de roles del usuario (para el
     * "Cambiar de rol" en nav.js). El rol ACTIVO de la sesión no vive
     * aquí: viaja aparte como 'rolActivo' en la respuesta de
     * login/registro/seleccionar-rol/cambiar-rol, porque depende del
     * token con el que se hizo la petición, no del usuario en sí.
     */
    public function paraFrontend(): array
    {
        return [
            'id' => $this->id_usuario,
            'nombre' => $this->nombre_completo,
            'email' => $this->correo,
            'roles' => $this->roles->pluck('nombre')->values(),
            'area' => $this->area,
            'cargo' => $this->cargo,
            'telefono' => $this->telefono,
            'direccion' => $this->direccion,
            'genero' => $this->genero,
            'fotoPerfil' => $this->foto_perfil,
            'preferenciasConvivencia' => $this->preferencias_convivencia ?? [],
        ];
    }

    /**
     * Única fuente de verdad de "perfil completo". Un usuario con el rol
     * admin (lo tenga asignado, tenga o no también 'cliente', esté o no
     * activo como admin en este momento) siempre se considera completo:
     * se crea desde el panel o por seeder, nunca pasa por onboarding.
     * Para cualquier otro caso, lo único que puede faltar tras un login
     * social es el teléfono; género sigue siendo opcional.
     */
    public function perfilCompleto(): bool
    {
        if ($this->esAdmin()) {
            return true;
        }

        return filled($this->telefono);
    }
}
