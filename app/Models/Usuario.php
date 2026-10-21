<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Usuario extends Authenticatable
{
    // Sin HasApiTokens a propósito: Sanctum ya no emite ni acepta tokens Bearer
    // para este modelo (su guard solo los acepta si el modelo usa ese trait).
    // La única forma de autenticarse es la cookie de sesión.
    use HasFactory, Notifiable;

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
        'es_superadmin' => 'boolean',
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
     * Roles N:N (cliente / moderador / admin). Un usuario puede tener varios
     * a la vez (ej. un cliente promovido a moderador, o a administrador).
     * Los permisos NO se calculan con todos estos roles juntos sino con el
     * rol activo de la sesión (ver App\Support\Permisos).
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

    public function esModerador(): bool
    {
        return $this->tieneRol('moderador');
    }

    /** Dominio de correo con el que una cuenta admin creada pasa a ser superadmin. */
    public const DOMINIO_SUPERADMIN = 'roommatch.com';

    public static function correoEsDeSuperadmin(?string $correo): bool
    {
        return $correo !== null
            && str_ends_with(mb_strtolower(trim($correo)), '@'.self::DOMINIO_SUPERADMIN);
    }

    /**
     * Superadmin: cuenta admin marcada con es_superadmin (solo el seeder o un
     * superadmin al crear la cuenta la marcan; un promovido nunca lo es, aunque
     * su correo sea @roommatch.com). Solo otros superadmin pueden bloquearla y
     * su único rol es 'admin': no cambia de rol ni agrega 'cliente'. Se exige
     * además el rol admin vigente, por si la marca quedara en una cuenta sin él.
     */
    public function esSuperadmin(): bool
    {
        return (bool) $this->es_superadmin && $this->esAdmin();
    }

    /**
     * La tabla usuario no tiene columna remember_token. Sin esto, el guard de
     * sesión intentaría leerla/escribirla (Auth::logout() la rota si existe).
     * Devolver '' desactiva el mecanismo "recordarme" por completo.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    // La emisión de la sesión (antes emitirTokenSesion(), que creaba tokens
    // Sanctum con abilities de rol) vive ahora en App\Services\SesionUsuario.

    // ------------------- Scopes útiles -------------------

    public function scopeAdmins($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('nombre', 'admin'));
    }

    public function scopeClientes($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('nombre', 'cliente'));
    }

    public function scopeModeradores($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->where('nombre', 'moderador'));
    }

    /** Quien atiende reportes y mensajes: administradores y moderadores. */
    public function scopeEquipoModeracion($query)
    {
        return $query->whereHas('roles', fn ($q) => $q->whereIn('nombre', ['admin', 'moderador']));
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
            'esSuperadmin' => $this->esSuperadmin(),
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
