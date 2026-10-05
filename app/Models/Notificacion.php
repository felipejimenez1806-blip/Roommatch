<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
     * Avisa a TODOS los usuarios con tipo_usuario = 'admin' cuando entra
     * un reporte nuevo (de publicación o de roomie). Vive aquí en vez de
     * duplicarse en cada controlador que crea reportes (ReservaController,
     * CitaController, y cualquiera que se agregue después).
     */
    public static function notificarAdminsNuevoReporte(Reporte $reporte): void
    {
        $mensaje = "Nuevo reporte de {$reporte->motivo} sobre \"{$reporte->objetivo_titulo}\".";

        Usuario::admins()->get(['id_usuario'])->each(function (Usuario $admin) use ($reporte, $mensaje) {
            static::create([
                'id_usuario' => $admin->id_usuario,
                'tipo' => 'reporte_nuevo',
                'mensaje' => $mensaje,
                'objetivo_titulo' => $reporte->objetivo_titulo,
                'link' => '/admin#admPanelReportes',
                'leida' => false,
            ]);
        });
    }

    /**
     * Avisa a TODOS los usuarios con tipo_usuario = 'admin' cuando llega
     * un mensaje nuevo desde el formulario de Contáctanos.
     */
    public static function notificarAdminsNuevoMensajeContacto(MensajeContacto $mensajeContacto): void
    {
        $mensaje = "Nuevo mensaje de contacto de {$mensajeContacto->nombre}.";

        Usuario::admins()->get(['id_usuario'])->each(function (Usuario $admin) use ($mensajeContacto, $mensaje) {
            static::create([
                'id_usuario' => $admin->id_usuario,
                'tipo' => 'mensaje_contacto_nuevo',
                'mensaje' => $mensaje,
                'objetivo_titulo' => $mensajeContacto->nombre,
                'link' => '/admin#admPanelMensajes',
                'leida' => false,
            ]);
        });
    }
}
