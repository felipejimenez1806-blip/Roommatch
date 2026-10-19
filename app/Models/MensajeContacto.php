<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MensajeContacto extends Model
{
    protected $table = 'mensajes_contacto';
    protected $primaryKey = 'id_mensaje_contacto';

    protected $fillable = [
        'id_usuario',
        'id_administrador',
        'nombre',
        'correo',
        'asunto',
        'mensaje',
        'atendido',
        'fecha_atencion',
        'respuesta',
    ];

    protected $casts = [
        'atendido' => 'boolean',
        'fecha_atencion' => 'datetime',
    ];

    /** Remitente, si tenía sesión iniciada (NULL = visitante sin cuenta). */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    /** Administrador o moderador que atendió el mensaje. */
    public function administrador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_administrador', 'id_usuario');
    }
}
