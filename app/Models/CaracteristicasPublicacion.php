<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaracteristicasPublicacion extends Model
{
    protected $table = 'caracteristicas_publicacion';
    protected $primaryKey = 'id_publicacion';
    public $incrementing = false; // la PK es también FK, no autoincrementa aquí
    public $timestamps = false;

    protected $fillable = [
        'id_publicacion',
        'amueblado', 'bano_privado', 'cocina_compartida', 'lavadora', 'secadora',
        'parqueadero', 'balcon', 'terraza', 'incluye_agua', 'incluye_luz',
        'incluye_internet', 'incluye_gas', 'detalles_incluidos', 'numero_habitantes',
        'ambiente_hogar', 'fumadores_en_casa', 'mascotas_en_casa', 'tamano_habitacion',
        'tipo_cama', 'habitacion_compartida', 'permite_mascotas', 'permite_visitas',
        'permite_fumar', 'permite_fiestas', 'permite_parejas', 'horario_silencio',
        'horario_entrada', 'cerca_transporte_publico', 'distancia_transporte',
        'porteria', 'camaras_seguridad', 'espacio_trabajo', 'ascensor', 'gimnasio',
        'zona_comun', 'cerca_supermercado', 'cerca_universidad',
    ];

    protected $casts = [
        'amueblado' => 'boolean', 'bano_privado' => 'boolean', 'cocina_compartida' => 'boolean',
        'lavadora' => 'boolean', 'secadora' => 'boolean', 'parqueadero' => 'boolean',
        'balcon' => 'boolean', 'terraza' => 'boolean', 'incluye_agua' => 'boolean',
        'incluye_luz' => 'boolean', 'incluye_internet' => 'boolean', 'incluye_gas' => 'boolean',
        'fumadores_en_casa' => 'boolean', 'mascotas_en_casa' => 'boolean',
        'habitacion_compartida' => 'boolean', 'permite_mascotas' => 'boolean',
        'permite_visitas' => 'boolean', 'permite_fumar' => 'boolean', 'permite_fiestas' => 'boolean',
        'permite_parejas' => 'boolean', 'cerca_transporte_publico' => 'boolean',
        'porteria' => 'boolean', 'camaras_seguridad' => 'boolean', 'espacio_trabajo' => 'boolean',
        'ascensor' => 'boolean', 'gimnasio' => 'boolean', 'zona_comun' => 'boolean',
        'cerca_supermercado' => 'boolean', 'cerca_universidad' => 'boolean',
    ];

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'id_publicacion', 'id_publicacion');
    }
}
