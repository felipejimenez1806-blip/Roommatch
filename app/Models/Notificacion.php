<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Notificacion extends Model
{
    protected $table = 'notificacion';
    protected $primaryKey = 'id_notificacion';
    public $timestamps = false;

    const CREATED_AT = 'fecha';

    protected $fillable = [
        'id_usuario', 'tipo', 'mensaje', 'objetivo_titulo', 'link', 'leida',
    ];

    protected $casts = [
        'leida' => 'boolean',
        'fecha' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function scopeNoLeidas($query)
    {
        return $query->where('leida', false);
    }

    /**
     * Avisa a TODO el equipo (administradores y moderadores) cuando entra un
     * reporte nuevo (de publicación o de roomie). Vive aquí en vez de
     * duplicarse en cada controlador que crea reportes (ReservaController,
     * CitaController, y cualquiera que se agregue después).
     */
    public static function notificarAdminsNuevoReporte(Reporte $reporte): void
    {
        static::avisarAlEquipo(
            Usuario::equipoModeracion()->with('roles')->get(),
            'reporte_nuevo',
            "Nuevo reporte de {$reporte->motivo} sobre \"{$reporte->objetivo_titulo}\".",
            $reporte->objetivo_titulo,
            '/admin#admPanelReportes',
            '/moderador#modPanelReportes',
        );
    }

    /**
     * Avisa a TODO el equipo (administradores y moderadores) cuando llega un
     * mensaje nuevo desde el formulario de Contáctanos.
     */
    public static function notificarAdminsNuevoMensajeContacto(MensajeContacto $mensajeContacto): void
    {
        static::avisarAlEquipo(
            Usuario::equipoModeracion()->with('roles')->get(),
            'mensaje_contacto_nuevo',
            "Nuevo mensaje de contacto de {$mensajeContacto->nombre}.",
            $mensajeContacto->nombre,
            '/admin#admPanelMensajes',
            '/moderador#modPanelMensajes',
        );
    }

    /**
     * Un moderador escaló un reporte: solo los administradores deben verlo.
     */
    public static function notificarAdminsReporteEscalado(Reporte $reporte, Usuario $moderador): void
    {
        static::avisarAlEquipo(
            Usuario::admins()->with('roles')->get(),
            'reporte_escalado',
            "{$moderador->nombre_completo} escaló un reporte de {$reporte->motivo} sobre \"{$reporte->objetivo_titulo}\".",
            $reporte->objetivo_titulo,
            '/admin#admPanelReportes',
            '/admin#admPanelReportes',
        );
    }

    /**
     * Un administrador devolvió a moderación un reporte que el moderador
     * había escalado. $idModerador se captura antes de limpiar el escalamiento.
     */
    public static function notificarModeradorReporteDevuelto(?int $idModerador, Reporte $reporte, ?string $nota): void
    {
        if (! $idModerador) {
            return;
        }

        $mensaje = "Un administrador devolvió a moderación el reporte sobre \"{$reporte->objetivo_titulo}\"";
        $mensaje .= $nota ? ": {$nota}" : '.';

        static::create([
            'id_usuario' => $idModerador,
            'tipo' => 'reporte_devuelto',
            'mensaje' => Str::limit($mensaje, 495),
            'objetivo_titulo' => $reporte->objetivo_titulo,
            'link' => '/moderador#modPanelReportes',
            'leida' => false,
        ]);
    }

    /**
     * Avisa al dueño de una publicación que el equipo de moderación la bajó.
     */
    public static function notificarPublicacionBajada(Publicacion $publicacion, ?string $motivo): void
    {
        $mensaje = "Tu publicación \"{$publicacion->titulo}\" fue dada de baja por el equipo de moderación.";
        if ($motivo) {
            $mensaje .= " Motivo: {$motivo}";
        }

        static::create([
            'id_usuario' => $publicacion->id_usuario,
            'tipo' => 'publicacion_bajada',
            'mensaje' => Str::limit($mensaje, 495),
            'objetivo_titulo' => $publicacion->titulo,
            'link' => '/mis-publicaciones',
            'leida' => false,
        ]);
    }

    /**
     * Avisa al dueño de un perfil de roomie que el equipo de moderación lo
     * bajó (mismo patrón que notificarPublicacionBajada()).
     */
    public static function notificarPersonaBajada(PersonaRoomie $persona, ?string $motivo): void
    {
        $mensaje = 'Tu perfil de roomie fue dado de baja por el equipo de moderación.';
        if ($motivo) {
            $mensaje .= " Motivo: {$motivo}";
        }

        static::create([
            'id_usuario' => $persona->id_usuario,
            'tipo' => 'persona_bajada',
            'mensaje' => Str::limit($mensaje, 495),
            'objetivo_titulo' => $persona->usuario->nombre_completo ?? 'Perfil de roomie',
            'link' => '/mis-publicaciones',
            'leida' => false,
        ]);
    }

    /**
     * Crea una notificación por persona. Quien tenga el rol admin recibe el
     * enlace al panel de administración; quien solo sea moderador, el del
     * panel de moderación (cada panel tiene sus propios ids de sección).
     *
     * @param Collection<int, Usuario> $usuarios  con la relación 'roles' cargada
     */
    private static function avisarAlEquipo(
        Collection $usuarios,
        string $tipo,
        string $mensaje,
        ?string $objetivoTitulo,
        string $linkAdmin,
        string $linkModerador,
    ): void {
        $usuarios->each(function (Usuario $destinatario) use ($tipo, $mensaje, $objetivoTitulo, $linkAdmin, $linkModerador) {
            static::create([
                'id_usuario' => $destinatario->id_usuario,
                'tipo' => $tipo,
                'mensaje' => Str::limit($mensaje, 495),
                'objetivo_titulo' => $objetivoTitulo,
                'link' => $destinatario->esAdmin() ? $linkAdmin : $linkModerador,
                'leida' => false,
            ]);
        });
    }
}
