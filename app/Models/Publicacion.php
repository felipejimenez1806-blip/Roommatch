<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Publicacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'publicacion';
    protected $primaryKey = 'id_publicacion';
    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'titulo',
        'descripcion',
        'precio',
        'tipo_espacio',
        'direccion',
        'zona',
        'ciudad',
        'estado_inmueble',
        'genero_preferido',
        'img',
        'imagenes',
        'fecha_actualizacion',
        'fecha_disponible',
    ];

    protected $casts = [
        'precio' => 'decimal:2',
        'imagenes' => 'array',
        'fecha_publicacion' => 'datetime',
        'fecha_actualizacion' => 'datetime',
        'fecha_disponible' => 'date',
        'deleted_at' => 'datetime',
    ];

    // ------------------- Relaciones -------------------

    public function propietario()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function caracteristicas()
    {
        return $this->hasOne(CaracteristicasPublicacion::class, 'id_publicacion', 'id_publicacion');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'id_publicacion', 'id_publicacion')
            ->where('tipo', 'publicacion');
    }

    public function favoritos()
    {
        return $this->hasMany(Favorito::class, 'id_publicacion', 'id_publicacion')
            ->where('tipo', 'publicacion');
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'id_publicacion', 'id_publicacion');
    }

    public function reportes()
    {
        return $this->hasMany(Reporte::class, 'id_publicacion', 'id_publicacion')
            ->where('tipo', 'publicacion');
    }

    // ------------------- Scopes útiles -------------------

    public function scopeDisponibles($query)
    {
        return $query->where('estado_inmueble', 'disponible');
    }

    public function scopeEnZona($query, string $zona)
    {
        return $query->where('zona', $zona);
    }

    /**
     * Promedio de calificación (reemplaza a fn_promedio_calificacion del SQL viejo).
     */
    public function getPromedioCalificacionAttribute(): float
    {
        return round($this->calificaciones()->avg('puntuacion') ?? 0, 1);
    }
}
