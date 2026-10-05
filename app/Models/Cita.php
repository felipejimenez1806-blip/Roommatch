<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    use HasFactory;

    protected $table = 'cita';
    protected $primaryKey = 'id_cita';
    public $timestamps = false;

    const CREATED_AT = 'fecha_solicitud';

    protected $fillable = [
        'id_usuario', 'id_persona', 'fecha_cita', 'hora_cita', 'tipo_encuentro',
        'lugar_encuentro', 'mensaje', 'estado_cita', 'contacto_nombre',
        'contacto_correo', 'contacto_telefono', 'fecha_respuesta',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_cita' => 'date',
        'fecha_respuesta' => 'datetime',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function persona()
    {
        return $this->belongsTo(PersonaRoomie::class, 'id_persona', 'id_persona');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado_cita', 'pendiente');
    }
}
