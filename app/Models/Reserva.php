<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reserva extends Model
{
    use HasFactory;

    protected $table = 'reserva';
    protected $primaryKey = 'id_reserva';
    public $timestamps = false;

    const CREATED_AT = 'fecha_solicitud';

    protected $fillable = [
        'id_usuario', 'id_publicacion', 'fecha_visita', 'hora_visita',
        'duracion_estimada', 'estado_reserva', 'contacto_nombre',
        'contacto_correo', 'contacto_telefono', 'fecha_respuesta',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_visita' => 'date',
        'fecha_respuesta' => 'datetime',
    ];

    public function solicitante()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'id_publicacion', 'id_publicacion');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado_reserva', 'pendiente');
    }
}
