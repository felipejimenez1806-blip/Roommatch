<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del registro de auditoría de moderación (tabla accion_moderacion).
 * Solo se crea; nunca se actualiza ni se borra desde la app.
 */
class AccionModeracion extends Model
{
    protected $table = 'accion_moderacion';
    protected $primaryKey = 'id_accion';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'rol_activo',
        'accion',
        'entidad_tipo',
        'entidad_id',
        'entidad_titulo',
        'detalle',
        'fecha',
    ];

    protected $casts = [
        'detalle' => 'array',
        'fecha' => 'datetime',
    ];

    public function actor(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }
}
