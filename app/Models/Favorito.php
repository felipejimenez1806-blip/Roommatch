<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Favorito extends Model
{
    protected $table = 'favorito';
    protected $primaryKey = 'id_favorito';
    public $timestamps = false;

    const CREATED_AT = 'fecha_agregado';

    protected $fillable = [
        'id_usuario', 'tipo', 'id_publicacion', 'id_persona',
    ];

    protected $casts = [
        'fecha_agregado' => 'datetime',
    ];

    public function usuario()
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
