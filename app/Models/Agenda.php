<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    protected $table = 'agenda';
    protected $primaryKey = 'id_agenda';

    const CREATED_AT = 'creado';
    const UPDATED_AT = 'actualizado';

    protected $fillable = [
        'id_usuario', 'id_otro', 'es_solicitante', 'tipo', 'id_origen',
        'titulo', 'lugar', 'fecha', 'hora', 'duracion_min', 'estado',
        'google_event_id', 'google_sincronizado_en',
    ];

    protected $casts = [
        'es_solicitante' => 'boolean',
        'fecha' => 'date',
        'google_sincronizado_en' => 'datetime',
    ];
}
