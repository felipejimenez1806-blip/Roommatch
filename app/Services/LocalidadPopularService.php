<?php

namespace App\Services;

use App\Models\Localidad;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Las 6 localidades "más agendadas" para el bloque "Lugares populares" de
 * index y dashboard.
 *
 * Puntaje de los últimos DIAS días, sumando:
 *   - reservas (visitas a una publicación)  -> zona de la publicación, x PESO_RESERVA
 *   - citas (encuentros con un roomie)      -> zona del perfil del roomie, x PESO_CITA
 * Se descartan las canceladas y las que pertenecen a una publicación o perfil
 * con borrado lógico. Si hay menos de LIMITE localidades con actividad, se
 * completa con las "por defecto" (localidad.orden_defecto) para que el bloque
 * nunca quede vacío ni incompleto.
 *
 * El resultado se cachea TTL_SEGUNDOS. Se guardan solo nombre y slug (no URLs):
 * las URLs se arman al renderizar, así un cambio de dominio o de subdirectorio
 * no deja enlaces viejos en el caché.
 */
class LocalidadPopularService
{
    public const LIMITE = 6;
    public const DIAS = 30;
    public const TTL_SEGUNDOS = 3600;
    public const CACHE_KEY = 'localidades.populares';

    private const PESO_CITA = 1;
    private const PESO_RESERVA = 3;

    /** @return array<int, array{nombre: string, slug: string}> siempre ordenado: la más popular primero */
    public function populares(): array
    {
        return Cache::remember(self::CACHE_KEY, self::TTL_SEGUNDOS, fn () => $this->calcular());
    }

    public static function olvidar(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function calcular(): array
    {
        $desde = now()->subDays(self::DIAS);

        $citas = DB::table('cita')
            ->join('persona_roomie', 'persona_roomie.id_persona', '=', 'cita.id_persona')
            ->whereNull('persona_roomie.deleted_at')
            ->where('cita.estado_cita', '<>', 'cancelada')
            ->where('cita.fecha_solicitud', '>=', $desde)
            ->selectRaw('persona_roomie.zona AS zona, COUNT(*) * ' . self::PESO_CITA . ' AS puntos')
            ->groupBy('persona_roomie.zona');

        $reservas = DB::table('reserva')
            ->join('publicacion', 'publicacion.id_publicacion', '=', 'reserva.id_publicacion')
            ->whereNull('publicacion.deleted_at')
            ->where('reserva.estado_reserva', '<>', 'cancelada')
            ->where('reserva.fecha_solicitud', '>=', $desde)
            ->selectRaw('publicacion.zona AS zona, COUNT(*) * ' . self::PESO_RESERVA . ' AS puntos')
            ->groupBy('publicacion.zona');

        // El join con `localidad` descarta cualquier zona que no esté en el
        // catálogo (datos viejos) y a la vez trae las columnas del desempate:
        // a igual puntaje gana una de las "por defecto", luego el nombre.
        $ids = DB::query()
            ->fromSub($citas->unionAll($reservas), 'actividad')
            ->join('localidad', 'localidad.nombre', '=', 'actividad.zona')
            ->selectRaw('localidad.id_localidad AS id, SUM(actividad.puntos) AS puntaje')
            ->groupBy('localidad.id_localidad', 'localidad.orden_defecto', 'localidad.nombre')
            ->orderByDesc('puntaje')
            ->orderByRaw('localidad.orden_defecto IS NULL')
            ->orderBy('localidad.orden_defecto')
            ->orderBy('localidad.nombre')
            ->limit(self::LIMITE)
            ->pluck('id')
            ->all();

        $porId = Localidad::whereIn('id_localidad', $ids)->get()->keyBy('id_localidad');
        $activas = collect($ids)->map(fn ($id) => $porId->get($id))->filter();

        $faltan = self::LIMITE - $activas->count();
        $relleno = $faltan > 0
            ? Localidad::whereNotIn('id_localidad', $ids)
                ->whereNotNull('orden_defecto')
                ->orderBy('orden_defecto')
                ->limit($faltan)
                ->get()
            : collect();

        return $activas->concat($relleno)
            ->map(fn (Localidad $l) => ['nombre' => $l->nombre, 'slug' => $l->slug])
            ->values()
            ->all();
    }
}
