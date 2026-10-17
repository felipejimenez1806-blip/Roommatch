<?php

namespace App\Models;

use App\Services\LocalidadPopularService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Catálogo cerrado de localidades de Bogotá (ver la migración
 * create_localidad_table). Los nombres de zona de publicaciones y perfiles de
 * roomie deben ser siempre uno de estos nombres, tal cual.
 */
class Localidad extends Model
{
    protected $table = 'localidad';
    protected $primaryKey = 'id_localidad';
    public $timestamps = false;

    protected $fillable = ['nombre', 'slug', 'imagen', 'orden_defecto'];

    private const CACHE_TODAS = 'localidades.todas';

    protected static function booted(): void
    {
        // Si alguien edita el catálogo, la lista cacheada y el top se recalculan.
        $limpiar = function () {
            Cache::forget(self::CACHE_TODAS);
            LocalidadPopularService::olvidar();
        };
        static::saved($limpiar);
        static::deleted($limpiar);
    }

    /**
     * Las 20 localidades ordenadas por nombre, como arreglos simples
     * ['nombre' => ..., 'slug' => ...] (cacheadas un día: casi nunca cambian).
     * Alimenta los <select>, window.APP.localidades y la validación.
     */
    public static function todas(): array
    {
        return Cache::remember(self::CACHE_TODAS, 86400, fn () => static::query()
            ->orderBy('nombre')
            ->get(['nombre', 'slug'])
            ->map(fn (self $l) => ['nombre' => $l->nombre, 'slug' => $l->slug])
            ->all());
    }

    /** Solo los nombres, para Rule::in(...) en los FormRequests. */
    public static function nombres(): array
    {
        return array_column(static::todas(), 'nombre');
    }

    /** URL de la foto de la localidad (public/img/localidades/{slug}.webp). */
    public static function urlImagen(string $slug): string
    {
        return asset('img/localidades/' . $slug . '.webp');
    }

    /** Enlace a Habitaciones ya filtrado por esa localidad. */
    public static function urlHabitaciones(string $slug): string
    {
        return route('habitaciones', ['localidad' => $slug]);
    }
}
