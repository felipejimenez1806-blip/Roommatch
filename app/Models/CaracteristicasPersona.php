<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaracteristicasPersona extends Model
{
    protected $table = 'caracteristicas_persona';
    protected $primaryKey = 'id_persona';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'id_persona',
        'fumador', 'tiene_mascota', 'ordenado', 'sociable', 'madrugador',
        'trasnochador', 'fiestero', 'ambiente_preferido', 'acepta_mascotas',
        'acepta_fumadores', 'acepta_visitas', 'acepta_parejas', 'horario',
        'trabaja_desde_casa', 'viaja_frecuentemente', 'tiempo_busqueda',
        'fecha_mudanza', 'quiere_amueblado', 'quiere_bano_privado',
        'quiere_parqueadero', 'cerca_universidad', 'cerca_transporte_publico',
        'tiene_vehiculo', 'comparte_gastos', 'referencias_verificadas',
    ];

    protected $casts = [
        'fumador' => 'boolean', 'tiene_mascota' => 'boolean', 'ordenado' => 'boolean',
        'sociable' => 'boolean', 'madrugador' => 'boolean', 'trasnochador' => 'boolean',
        'fiestero' => 'boolean', 'acepta_mascotas' => 'boolean', 'acepta_fumadores' => 'boolean',
        'acepta_visitas' => 'boolean', 'acepta_parejas' => 'boolean',
        'trabaja_desde_casa' => 'boolean', 'viaja_frecuentemente' => 'boolean',
        'quiere_amueblado' => 'boolean', 'quiere_bano_privado' => 'boolean',
        'quiere_parqueadero' => 'boolean', 'cerca_universidad' => 'boolean',
        'cerca_transporte_publico' => 'boolean', 'tiene_vehiculo' => 'boolean',
        'comparte_gastos' => 'boolean', 'referencias_verificadas' => 'boolean',
    ];

    public function persona()
    {
        return $this->belongsTo(PersonaRoomie::class, 'id_persona', 'id_persona');
    }
}
