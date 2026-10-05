<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calificacion extends Model
{
    use HasFactory;

    protected $table = 'calificacion';
    protected $primaryKey = 'id_calificacion';
    public $timestamps = false;

    const CREATED_AT = 'fecha_calificacion';

    protected $fillable = [
        'id_usuario', 'tipo', 'id_publicacion', 'id_persona',
        'autor_nombre', 'puntuacion', 'comentario',
    ];

    protected $casts = [
        'puntuacion' => 'integer',
        'fecha_calificacion' => 'datetime',
    ];

    public function autor()
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'id_publicacion', 'id_publicacion');
    }

    public function persona()
    {
        return $this->belongsTo(PersonaRoomie::class, 'id_persona', 'id_persona');
    }
}
