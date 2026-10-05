<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonaRoomie extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'persona_roomie';
    protected $primaryKey = 'id_persona';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario', 'edad', 'genero', 'ocupacion', 'zona', 'ciudad',
        'presupuesto', 'telefono', 'img', 'descripcion', 'estado_busqueda', 'fecha_actualizacion',
    ];

    protected $casts = [
        'presupuesto' => 'decimal:2',
        'fecha_publicacion' => 'datetime',
        'fecha_actualizacion' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // ------------------- Relaciones -------------------

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    // ------------------- Scopes útiles -------------------

    public function scopeBuscando($query)
    {
        return $query->where('estado_busqueda', 'buscando');
    }

    public function caracteristicas()
    {
        return $this->hasOne(CaracteristicasPersona::class, 'id_persona', 'id_persona');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'id_persona', 'id_persona')
            ->where('tipo', 'persona');
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'id_persona', 'id_persona')
            ->where('tipo', 'persona');
    }

    public function citas()
    {
        return $this->hasMany(Cita::class, 'id_persona', 'id_persona');
    }

    public function reportes()
    {
        return $this->hasMany(Reporte::class, 'id_persona', 'id_persona')
            ->where('tipo', 'persona');
    }

    public function getPromedioCalificacionAttribute(): float
    {
        return round($this->calificaciones()->avg('puntuacion') ?? 0, 1);
    }
}
