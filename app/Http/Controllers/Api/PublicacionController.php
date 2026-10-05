<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicacionRequest;
use App\Models\CaracteristicasPublicacion;
use App\Models\Notificacion;
use App\Models\Publicacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PublicacionController extends Controller
{
    /**
     * GET /api/publicaciones
     * Listado público con filtros, orden y paginación server-side
     * (reemplaza el filtrado en memoria que antes vivía en habitaciones.js).
     */
    public function index(Request $request)
    {
        $porPagina = 6;

        $query = Publicacion::query()
            ->disponibles()
            ->with('caracteristicas')
            ->withAvg('calificaciones as rating', 'puntuacion');

        // ---- Zona (búsqueda parcial) ----
        if ($request->filled('zona')) {
            $query->where('zona', 'like', '%' . $request->input('zona') . '%');
        }

        // ---- Tipo de espacio (multi-select) ----
        if ($request->filled('tipo')) {
            $query->whereIn('tipo_espacio', (array) $request->input('tipo'));
        }

        // ---- Precio ----
        if ($request->filled('precio_min')) {
            $query->where('precio', '>=', $request->input('precio_min'));
        }
        if ($request->filled('precio_max')) {
            $query->where('precio', '<=', $request->input('precio_max'));
        }

        // ---- Características (booleanas + selects) vía whereHas ----
        $booleanosValidos = [
            'amueblado', 'bano_privado', 'cocina_compartida', 'lavadora', 'secadora',
            'parqueadero', 'balcon', 'terraza', 'incluye_agua', 'incluye_luz',
            'incluye_internet', 'incluye_gas', 'mascotas_en_casa', 'habitacion_compartida',
            'permite_mascotas', 'permite_visitas', 'permite_fumar', 'permite_fiestas',
            'permite_parejas', 'cerca_transporte_publico', 'porteria', 'camaras_seguridad',
            'espacio_trabajo', 'ascensor', 'gimnasio', 'zona_comun', 'cerca_supermercado',
            'cerca_universidad',
        ];

        $tieneFiltrosCaracteristicas = $request->filled('booleanos')
            || $request->filled('ambiente')
            || $request->filled('tamano')
            || $request->filled('cama');

        if ($tieneFiltrosCaracteristicas) {
            $query->whereHas('caracteristicas', function ($q) use ($request, $booleanosValidos) {
                foreach ((array) $request->input('booleanos', []) as $campo) {
                    if ($campo === 'sin_fumadores') {
                        $q->where('fumadores_en_casa', false);
                    } elseif (in_array($campo, $booleanosValidos, true)) {
                        $q->where($campo, true);
                    }
                }

                if ($request->filled('ambiente')) {
                    $q->where('ambiente_hogar', $request->input('ambiente'));
                }
                if ($request->filled('tamano')) {
                    $q->where('tamano_habitacion', $request->input('tamano'));
                }
                if ($request->filled('cama')) {
                    $q->where('tipo_cama', $request->input('cama'));
                }
            });
        }

        // ---- Calificación mínima (sobre el alias 'rating' de withAvg) ----
        $calificacionMin = (float) $request->input('calificacion_min', 0);
        if ($calificacionMin > 0) {
            $query->havingRaw('COALESCE(rating, 0) >= ?', [$calificacionMin]);
        }

        // ---- Orden ----
        switch ($request->input('orden', 'recientes')) {
            case 'precio-asc':
                $query->orderBy('precio', 'asc');
                break;
            case 'precio-desc':
                $query->orderBy('precio', 'desc');
                break;
            case 'rating':
                $query->orderByRaw('COALESCE(rating, 0) desc');
                break;
            default:
                $query->orderByDesc('fecha_publicacion');
        }

        $paginado = $query->paginate($porPagina)->withQueryString();

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (Publicacion $p) => $this->formatearTarjeta($p)),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'total' => $paginado->total(),
            ],
        ]);
    }

    /**
     * GET /api/publicaciones/destacadas
     * Usado por el hero de index/dashboard cuando NO hay ninguna zona
     * seleccionada: la MEJOR publicación de cada zona (sin repetir
     * zona), mostrando como máximo 5. Si una zona no tiene ninguna
     * calificación todavía, compite con rating 0 (no se excluye).
     *
     * IMPORTANTE: esta ruta debe registrarse en api.php ANTES de
     * GET /publicaciones/{publicacion}, o Laravel intentará resolver
     * "destacadas" como un {publicacion} y devolverá 404.
     */
    public function destacadas()
    {
        $publicaciones = Publicacion::query()
            ->disponibles()
            ->with('caracteristicas')
            ->withAvg('calificaciones as rating', 'puntuacion')
            ->get();

        $mejoresPorZona = $publicaciones
            ->groupBy('zona')
            ->map(fn ($grupo) => $grupo->sortByDesc(fn (Publicacion $p) => $p->rating ?? 0)->first())
            ->sortByDesc(fn (Publicacion $p) => $p->rating ?? 0)
            ->take(5)
            ->values();

        return response()->json([
            'data' => $mejoresPorZona->map(fn (Publicacion $p) => $this->formatearTarjeta($p)),
        ]);
    }

    /**
     * GET /api/publicaciones/{id}
     * Detalle público de una publicación (reemplaza al lookup en el
     * array `publicaciones` de data.js que usaba publicacion.js).
     *
     * NOTA soft delete: el route model binding de Laravel respeta el
     * global scope de SoftDeletes automáticamente, así que una
     * publicación con deleted_at != null aquí devuelve 404 solo, sin
     * que tengamos que filtrar nada a mano.
     */
    public function show(Publicacion $publicacion)
    {
        $publicacion->load('caracteristicas', 'propietario', 'calificaciones.autor');

        return response()->json(['publicacion' => $this->formatearDetalle($publicacion)]);
    }

    /**
     * GET /api/mis-publicaciones
     * Publicaciones del usuario autenticado, con sus solicitudes de
     * reserva (reemplaza obtenerMisPublicaciones()/obtenerSolicitudes()
     * de mis-publicaciones.js).
     */
    public function misPublicaciones(Request $request)
    {
        $publicaciones = Publicacion::where('id_usuario', $request->user()->id_usuario)
            ->with(['reservas' => fn ($q) => $q->orderByDesc('fecha_solicitud')])
            ->orderByDesc('fecha_publicacion')
            ->get()
            ->map(fn (Publicacion $p) => $this->formatearMiPublicacion($p));

        return response()->json(['publicaciones' => $publicaciones]);
    }

    /**
     * GET /api/mis-publicaciones/{publicacion}
     * Detalle de UNA publicación propia (para precargar el wizard en
     * modo edición). Solo el dueño puede verla por esta ruta.
     */
    public function misPublicacionShow(Request $request, Publicacion $publicacion)
    {
        $this->verificarPropietario($request, $publicacion);
        $publicacion->load('caracteristicas');

        return response()->json(['publicacion' => $publicacion]);
    }

    /**
     * POST /api/mis-publicaciones
     * Crea una publicación + sus características. Reemplaza a
     * guardarPublicacionExtra() de crear-publicacion.js.
     */
    public function store(PublicacionRequest $request)
    {
        $datos = $request->validated();
        $usuario = $request->user();

        $this->sincronizarTelefonoUsuario($usuario, $datos['telefono_contacto']);

        $urls = array_map(fn ($img) => $this->guardarImagenBase64($img), $datos['imagenes']);

        $publicacion = Publicacion::create([
            'id_usuario' => $usuario->id_usuario,
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'precio' => $datos['precio'],
            'tipo_espacio' => $datos['tipo_espacio'],
            'direccion' => $datos['direccion'],
            'zona' => $datos['zona'],
            'ciudad' => $datos['ciudad'] ?? 'Bogotá',
            'genero_preferido' => $datos['genero'] ?? null,
            'img' => $urls[0],
            // FIX: antes se hacía json_encode($urls) aquí, pero el Model
            // ya castea 'imagenes' como 'array', así que Eloquent lo
            // codificaba una segunda vez (doble JSON-encode). Eso rompía
            // la galería en el detalle: al leer, json_decode una sola vez
            // devolvía un string en vez de un array. Se asigna el array
            // directo y se deja que el cast del Model haga su trabajo.
            'imagenes' => $urls,
            'fecha_disponible' => $datos['fecha_disponible'],
        ]);

        $publicacion->caracteristicas()->create($this->mapearCaracteristicas($datos));

        return response()->json(['publicacion' => ['id' => $publicacion->id_publicacion]], 201);
    }

    /**
     * PUT /api/mis-publicaciones/{publicacion}
     * Edita una publicación propia. Reemplaza a actualizarPublicacionExtra().
     */
    public function update(PublicacionRequest $request, Publicacion $publicacion)
    {
        $this->verificarPropietario($request, $publicacion);

        $datos = $request->validated();
        $this->sincronizarTelefonoUsuario($request->user(), $datos['telefono_contacto']);

        // Las imágenes que ya eran URLs (no se tocaron en el wizard) se
        // dejan igual; solo las nuevas (base64) se guardan como archivo.
        $urls = array_map(
            fn ($img) => str_starts_with($img, 'data:image') ? $this->guardarImagenBase64($img) : $img,
            $datos['imagenes']
        );

        $publicacion->update([
            'titulo' => $datos['titulo'],
            'descripcion' => $datos['descripcion'],
            'precio' => $datos['precio'],
            'tipo_espacio' => $datos['tipo_espacio'],
            'direccion' => $datos['direccion'],
            'zona' => $datos['zona'],
            'ciudad' => $datos['ciudad'] ?? 'Bogotá',
            'genero_preferido' => $datos['genero'] ?? null,
            'img' => $urls[0],
            // Mismo fix que en store(): se asigna el array directo.
            'imagenes' => $urls,
            'fecha_disponible' => $datos['fecha_disponible'],
            'fecha_actualizacion' => now(),
        ]);

        $publicacion->caracteristicas()->updateOrCreate(
            ['id_publicacion' => $publicacion->id_publicacion],
            $this->mapearCaracteristicas($datos)
        );

        return response()->json(['publicacion' => ['id' => $publicacion->id_publicacion]]);
    }

    /**
     * PATCH /api/mis-publicaciones/{publicacion}/estado
     * Cambia disponible/reservado/no_disponible sin tocar el resto de
     * la publicación (fotos, precio, descripción, reseñas). Reemplaza
     * al workaround de "borrar y crear de nuevo" que usaban los
     * oferentes cuando ya habían acordado el espacio con alguien.
     *
     * Si se marca como NO disponible, las solicitudes de reserva que
     * sigan 'pendiente' se rechazan automáticamente — no tiene sentido
     * dejarlas colgadas esperando una respuesta que ya no va a llegar.
     */
    public function actualizarEstado(Request $request, Publicacion $publicacion)
    {
        $this->verificarPropietario($request, $publicacion);

        $datos = $request->validate([
            'estado_inmueble' => 'required|in:disponible,reservado,no_disponible',
        ]);

        $publicacion->update([
            'estado_inmueble' => $datos['estado_inmueble'],
            'fecha_actualizacion' => now(),
        ]);

        $solicitudesRechazadas = 0;

        if ($datos['estado_inmueble'] !== 'disponible') {
            $pendientes = $publicacion->reservas()->where('estado_reserva', 'pendiente')->get();

            foreach ($pendientes as $reserva) {
                $reserva->update(['estado_reserva' => 'rechazada', 'fecha_respuesta' => now()]);

                Notificacion::create([
                    'id_usuario' => $reserva->id_usuario,
                    'tipo' => 'reserva_rechazada',
                    'mensaje' => "\"{$publicacion->titulo}\" ya no está disponible, así que tu solicitud de reserva fue rechazada automáticamente.",
                    'objetivo_titulo' => $publicacion->titulo,
                    'link' => '/perfil#panelReservas',
                    'leida' => false,
                ]);
            }

            $solicitudesRechazadas = $pendientes->count();
        }

        return response()->json([
            'mensaje' => 'Estado actualizado.',
            'estado_inmueble' => $publicacion->estado_inmueble,
            'solicitudes_rechazadas' => $solicitudesRechazadas,
        ]);
    }

    /**
     * DELETE /api/mis-publicaciones/{publicacion}
     *
     * Borrado lógico (soft delete): el Model usa SoftDeletes, así que
     * $publicacion->delete() ya NO ejecuta un DELETE físico, solo marca
     * deleted_at. Eso significa que el ON DELETE CASCADE de la FK nunca
     * se dispara y las reservas asociadas NO se eliminan ni cambian
     * solas. Por eso, igual que en actualizarEstado() al marcar
     * "no_disponible", rechazamos aquí explícitamente las solicitudes
     * que sigan pendientes antes de eliminar — si no lo hiciéramos,
     * quedarían pendientes para siempre, apuntando a una publicación
     * que el usuario ya no puede ver.
     */
    public function destroy(Request $request, Publicacion $publicacion)
    {
        $this->verificarPropietario($request, $publicacion);

        $pendientes = $publicacion->reservas()->where('estado_reserva', 'pendiente')->get();

        foreach ($pendientes as $reserva) {
            $reserva->update(['estado_reserva' => 'rechazada', 'fecha_respuesta' => now()]);

            Notificacion::create([
                'id_usuario' => $reserva->id_usuario,
                'tipo' => 'reserva_rechazada',
                'mensaje' => "\"{$publicacion->titulo}\" fue eliminada, así que tu solicitud de reserva fue rechazada automáticamente.",
                'objetivo_titulo' => $publicacion->titulo,
                'link' => '/perfil#panelReservas',
                'leida' => false,
            ]);
        }

        $publicacion->delete(); // soft delete: solo marca deleted_at, el registro sigue en BD

        return response()->json(['mensaje' => 'Publicación eliminada.']);
    }

    // ------------------------------------------------------------

    private function verificarPropietario(Request $request, Publicacion $publicacion): void
    {
        if ($publicacion->id_usuario !== $request->user()->id_usuario) {
            abort(403, 'No puedes gestionar una publicación que no es tuya.');
        }
    }

    private function sincronizarTelefonoUsuario($usuario, string $telefonoContacto): void
    {
        $limpio = preg_replace('/\D/', '', $telefonoContacto);
        if ($limpio && $limpio !== $usuario->telefono) {
            $usuario->update(['telefono' => $limpio]);
        }
    }

    /**
     * Decodifica un data URL (base64) de imagen y lo guarda como archivo
     * real en storage/app/public/publicaciones. Devuelve la URL pública.
     */
    private function guardarImagenBase64(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $tipo)) {
            throw new \InvalidArgumentException('Formato de imagen inválido.');
        }

        $extension = $tipo[1] === 'jpeg' ? 'jpg' : $tipo[1];
        $contenido = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        $ruta = 'publicaciones/' . Str::uuid() . '.' . $extension;

        Storage::disk('public')->put($ruta, $contenido);

        return Storage::url($ruta); // ej: /storage/publicaciones/xxxx.jpg
    }

    private function mapearCaracteristicas(array $datos): array
    {
        $c = $datos['caracteristicas'] ?? [];

        $booleanos = [
            'amueblado', 'bano_privado', 'cocina_compartida', 'lavadora', 'secadora',
            'parqueadero', 'balcon', 'terraza', 'incluye_agua', 'incluye_luz',
            'incluye_internet', 'incluye_gas', 'fumadores_en_casa', 'mascotas_en_casa',
            'habitacion_compartida', 'permite_mascotas', 'permite_visitas', 'permite_fumar',
            'permite_fiestas', 'permite_parejas', 'cerca_transporte_publico', 'porteria',
            'camaras_seguridad', 'espacio_trabajo', 'ascensor', 'gimnasio', 'zona_comun',
            'cerca_supermercado', 'cerca_universidad',
        ];

        $resultado = [];
        foreach ($booleanos as $campo) {
            $resultado[$campo] = (bool) ($c[$campo] ?? false);
        }

        $resultado['ambiente_hogar'] = $c['ambiente_hogar'] ?: null;
        $resultado['tamano_habitacion'] = $c['tamano_habitacion'] ?: null;
        $resultado['tipo_cama'] = $c['tipo_cama'] ?: null;
        $resultado['detalles_incluidos'] = $c['detalles_incluidos'] ?: null;
        $resultado['numero_habitantes'] = $c['numero_habitantes'] ?: null;
        $resultado['horario_silencio'] = $c['horario_silencio'] ?: null;
        $resultado['horario_entrada'] = $c['horario_entrada'] ?: null;
        $resultado['distancia_transporte'] = $c['distancia_transporte'] ?: null;

        return $resultado;
    }

    private function formatearTarjeta(Publicacion $p): array
    {
        return [
            'id' => $p->id_publicacion,
            'tipo_espacio' => $p->tipo_espacio,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'precio' => (float) $p->precio,
            'rating' => round($p->rating ?? 0, 1),
            'img' => $p->img,
            'tags' => $this->obtenerTags($p->caracteristicas),
        ];
    }

    /**
     * Normaliza el campo 'imagenes' a un array plano de URLs.
     *
     * Publicaciones creadas ANTES del fix del bug de doble-encoding
     * (ver store()/update()) quedaron con el JSON codificado dos
     * veces en la base de datos. El cast 'array' del Model solo
     * decodifica una vez, así que en esos registros $p->imagenes
     * llega como STRING (el JSON interno todavía sin decodificar) en
     * vez de array. Esta función detecta ese caso y decodifica una
     * vez más, para que el frontend siempre reciba un array real sin
     * importar cuándo se creó el registro.
     */
    private function normalizarImagenes($valor): array
    {
        if (is_array($valor)) {
            return $valor;
        }

        if (is_string($valor) && $valor !== '') {
            $decodificado = json_decode($valor, true);
            if (is_array($decodificado)) {
                return $decodificado;
            }
        }

        return [];
    }

    /**
     * Formatea el detalle completo para GET /api/publicaciones/{id}.
     * Esta es la forma exacta que espera publicacion.js: imágenes ya
     * resueltas como array, rating numérico, reseñas con nombre de
     * autor resuelto y datos del propietario (sin exponer todo el
     * modelo Usuario).
     */
    private function formatearDetalle(Publicacion $p): array
    {
        $imagenes = $this->normalizarImagenes($p->imagenes);

        return [
            'id' => $p->id_publicacion,
            'titulo' => $p->titulo,
            'descripcion' => $p->descripcion,
            'precio' => (float) $p->precio,
            'tipo_espacio' => $p->tipo_espacio,
            'direccion' => $p->direccion,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'genero_preferido' => $p->genero_preferido,
            'estado_inmueble' => $p->estado_inmueble,
            'fecha_disponible' => optional($p->fecha_disponible)->format('Y-m-d'),
            'imagenes' => $imagenes ?: array_filter([$p->img]),
            'rating' => $p->promedio_calificacion,
            'caracteristicas' => $p->caracteristicas,
            'propietario' => $p->propietario ? [
                'id' => $p->propietario->id_usuario,
                'nombre' => $p->propietario->nombre_completo,
                'correo' => $p->propietario->correo,
            ] : null,
            'resenas' => $p->calificaciones->map(fn ($c) => [
                'autor' => $c->autor_nombre ?: optional($c->autor)->nombre_completo ?: 'Usuario',
                'fecha' => optional($c->fecha_calificacion)->format('Y-m-d'),
                'puntuacion' => $c->puntuacion,
                'comentario' => $c->comentario,
            ])->values(),
        ];
    }

    private function obtenerTags(?CaracteristicasPublicacion $c): array
    {
        if (! $c) {
            return [];
        }

        $tags = [];
        if ($c->amueblado) $tags[] = 'Amueblado';
        if ($c->bano_privado) $tags[] = 'Baño privado';
        if ($c->parqueadero) $tags[] = 'Parqueadero';
        if ($c->permite_mascotas) $tags[] = 'Acepta mascotas';
        if ($c->cerca_universidad) $tags[] = 'Cerca a universidad';
        if ($c->incluye_agua && $c->incluye_luz && $c->incluye_internet) $tags[] = 'Servicios incluidos';
        if ($c->zona_comun) $tags[] = 'Zona común';

        return array_slice($tags, 0, 4);
    }

    private function formatearMiPublicacion(Publicacion $p): array
    {
        return [
            'id' => $p->id_publicacion,
            'titulo' => $p->titulo,
            'tipo_espacio' => $p->tipo_espacio,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'precio' => (float) $p->precio,
            'estado_inmueble' => $p->estado_inmueble,
            'img' => $p->img,
            'solicitudes' => $p->reservas->map(fn ($r) => [
                'id' => $r->id_reserva,
                'estado' => $r->estado_reserva,
                'fecha_visita' => optional($r->fecha_visita)->format('Y-m-d'),
                'hora_visita' => $r->hora_visita,
                'duracion' => $r->duracion_estimada,
                'monto_total' => (float) $p->precio,
                'contacto_nombre' => $r->contacto_nombre,
                'contacto_correo' => $r->contacto_correo,
                'contacto_telefono' => $r->contacto_telefono,
            ]),
        ];
    }
}
