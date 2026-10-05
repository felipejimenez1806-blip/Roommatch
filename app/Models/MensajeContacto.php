<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MensajeContacto extends Model
{
    protected $table = 'mensajes_contacto';
    protected $primaryKey = 'id_mensaje_contacto';

    protected $fillable = [
        'nombre',
        'correo',
        'asunto',
        'mensaje',
        'atendido',
    ];

    protected $casts = [
        'atendido' => 'boolean',
    ];
}
