<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PersonaRoomieRequest;
use App\Models\CaracteristicasPersona;
use App\Models\Notificacion;
use App\Models\PersonaRoomie;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PersonaRoomieController extends Controller
{
    /**
     * GET /api/roomies
     * Listado público con filtros, orden y paginación server-side.
     * Mismo enfoque que PublicacionController@index (ver Habitaciones).
     */
    public function index(Request $request)
    {
        $porPagina = 6;

        $query = PersonaRoomie::query()
            ->buscando()
            ->with(['caracteristicas', 'usuario'])
            ->withAvg('calificaciones as rating', 'puntuacion');

        // ---- Zona (búsqueda parcial) ----
        if ($request->filled('zona')) {
            $query->where('zona', 'like', '%' . $request->input('zona') . '%');
        }

        // ---- Ocupación (multi-select) ----
        if ($request->filled('ocupacion')) {
            $query->whereIn('ocupacion', (array) $request->input('ocupacion'));
        }

        // ---- Género (multi-select) ----
        if ($request->filled('genero')) {
            $query->whereIn('genero', (array) $request->input('genero'));
        }

        // ---- Presupuesto ----
        if ($request->filled('presupuesto_min')) {
            $query->where('presupuesto', '>=', $request->input('presupuesto_min'));
        }
        if ($request->filled('presupuesto_max')) {
            $query->where('presupuesto', '<=', $request->input('presupuesto_max'));
        }

        // ---- Características (booleanas + selects) vía whereHas ----
        $booleanosValidos = [
            'fumador', 'tiene_mascota', 'ordenado', 'sociable', 'madrugador',
            'trasnochador', 'fiestero', 'acepta_mascotas', 'acepta_fumadores',
            'acepta_visitas', 'acepta_parejas', 'trabaja_desde_casa',
            'viaja_frecuentemente', 'quiere_amueblado', 'quiere_bano_privado',
            'quiere_parqueadero', 'cerca_universidad', 'cerca_transporte_publico',
            'tiene_vehiculo', 'comparte_gastos', 'referencias_verificadas',
        ];

        $tieneFiltrosCaracteristicas = $request->filled('booleanos')
            || $request->filled('ambiente')
            || $request->filled('horario')
            || $request->filled('tiempo_busqueda')
            || $request->filled('mudanza');

        if ($tieneFiltrosCaracteristicas) {
            $query->whereHas('caracteristicas', function ($q) use ($request, $booleanosValidos) {
                foreach ((array) $request->input('booleanos', []) as $campo) {
                    if (in_array($campo, $booleanosValidos, true)) {
                        $q->where($campo, true);
                    }
                }

                if ($request->filled('ambiente')) {
                    $q->where('ambiente_preferido', $request->input('ambiente'));
                }
                if ($request->filled('horario')) {
                    $q->where('horario', $request->input('horario'));
                }
                if ($request->filled('tiempo_busqueda')) {
                    $q->where('tiempo_busqueda', $request->input('tiempo_busqueda'));
                }
                if ($request->filled('mudanza')) {
                    $q->where('fecha_mudanza', $request->input('mudanza'));
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
            case 'presupuesto-asc':
                $query->orderBy('presupuesto', 'asc');
                break;
            case 'presupuesto-desc':
                $query->orderBy('presupuesto', 'desc');
                break;
            case 'rating':
                $query->orderByRaw('COALESCE(rating, 0) desc');
                break;
            default:
                $query->orderByDesc('fecha_publicacion');
        }

        $paginado = $query->paginate($porPagina)->withQueryString();

        return response()->json([
            'data' => $paginado->getCollection()->map(fn (PersonaRoomie $p) => $this->formatearTarjeta($p)),
            'meta' => [
                'current_page' => $paginado->currentPage(),
                'last_page' => $paginado->lastPage(),
                'total' => $paginado->total(),
            ],
        ]);
    }

    /**
     * GET /api/roomies/{persona}
     * Detalle público de un perfil de roomie (para perfil-roomie.js).
     * Distinto de miPerfil(): este es público, sin auth, y devuelve
     * rating/reseñas/propietario en vez de los campos "en bruto" que
     * necesita el wizard de edición.
     *
     * NOTA soft delete: el route model binding respeta el global scope
     * de SoftDeletes automáticamente, así que un perfil con deleted_at
     * != null aquí devuelve 404 solo, sin filtrar nada a mano.
     */
    public function show(PersonaRoomie $persona)
    {
        $persona->load(['caracteristicas', 'usuario', 'calificaciones.autor']);

        return response()->json(['persona' => $this->formatearDetallePublico($persona)]);
    }

    /**
     * GET /api/mi-perfil-roomie
     * Perfil de roomie del usuario autenticado, o null si aún no se ha
     * publicado. Reemplaza al chequeo `personas.find(duenoEmail === sesion.email)`
     * que hacía crear-perfil-roomie.js contra roomies-data.js: el wizard
     * llama esto al cargar para decidir si redirige a "ver mi perfil" (ya
     * existe y no viene ?id=), si precarga el formulario (modo edición),
     * o si arranca vacío (primera vez).
     *
     * NOTA soft delete: el global scope excluye automáticamente perfiles
     * con deleted_at != null, así que tras eliminar su perfil el usuario
     * vuelve a ver el estado "aún no te has publicado", igual que si
     * nunca hubiera creado uno.
     */
    public function miPerfil(Request $request)
    {
        $perfil = PersonaRoomie::with('caracteristicas')
            ->where('id_usuario', $request->user()->id_usuario)
            ->first();

        return response()->json([
            'perfil' => $perfil ? $this->formatearDetalle($perfil) : null,
        ]);
    }

    /**
     * POST /api/mi-perfil-roomie
     * Crea el perfil de roomie + sus características. Reemplaza a
     * guardarPersonaExtra() de crear-perfil-roomie.js.
     *
     * NOTA soft delete: ya NO existe el unique(id_usuario) a nivel de BD
     * (ver migración) porque bloquearía volver a crear un perfil después
     * de eliminarlo. La regla "un perfil ACTIVO por usuario" se valida
     * aquí con este exists(): como el global scope de SoftDeletes ya
     * excluye los perfiles eliminados, este chequeo solo encuentra un
     * perfil activo existente, nunca uno ya eliminado.
     */
    public function store(PersonaRoomieRequest $request)
    {
        $usuario = $request->user();

        if (PersonaRoomie::where('id_usuario', $usuario->id_usuario)->exists()) {
            abort(409, 'Ya tienes un perfil de roomie publicado.');
        }

        $datos = $request->validated();
        $this->sincronizarTelefonoUsuario($usuario, $datos['telefono_contacto']);

        $urlImg = str_starts_with($datos['foto'], 'data:image')
            ? $this->guardarImagenBase64($datos['foto'])
            : $datos['foto'];

        $persona = PersonaRoomie::create([
            'id_usuario' => $usuario->id_usuario,
            'edad' => $datos['edad'],
            'genero' => $datos['genero'],
            'ocupacion' => $datos['ocupacion'],
            'zona' => $datos['zona'],
            'ciudad' => $datos['ciudad'] ?? 'Bogotá',
            'presupuesto' => $datos['presupuesto'],
            'telefono' => $datos['telefono_contacto'],
            'img' => $urlImg,
            'descripcion' => $datos['descripcion'],
        ]);

        $persona->caracteristicas()->create($this->mapearCaracteristicas($datos));

        return response()->json(['perfil' => ['id' => $persona->id_persona]], 201);
    }

    /**
     * PUT /api/mi-perfil-roomie
     * Edita el perfil propio. Reemplaza a actualizarPersonaExtra(). No
     * recibe {id} en la ruta porque la relación usuario-perfil es 1 a 1:
     * siempre se edita el perfil del usuario autenticado.
     */
    public function update(PersonaRoomieRequest $request)
    {
        $usuario = $request->user();
        $persona = PersonaRoomie::where('id_usuario', $usuario->id_usuario)->first();

        if (! $persona) {
            abort(404, 'Aún no tienes un perfil de roomie publicado.');
        }

        $datos = $request->validated();
        $this->sincronizarTelefonoUsuario($usuario, $datos['telefono_contacto']);

        // Igual que en PublicacionController: si la foto que llega ya es
        // una URL (no se tocó en el wizard), se deja igual; solo si es un
        // data URL nuevo se decodifica y se guarda como archivo.
        $urlImg = str_starts_with($datos['foto'], 'data:image')
            ? $this->guardarImagenBase64($datos['foto'])
            : $datos['foto'];

        $persona->update([
            'edad' => $datos['edad'],
            'genero' => $datos['genero'],
            'ocupacion' => $datos['ocupacion'],
            'zona' => $datos['zona'],
            'ciudad' => $datos['ciudad'] ?? 'Bogotá',
            'presupuesto' => $datos['presupuesto'],
            'telefono' => $datos['telefono_contacto'],
            'img' => $urlImg,
            'descripcion' => $datos['descripcion'],
            'fecha_actualizacion' => now(),
        ]);

        $persona->caracteristicas()->updateOrCreate(
            ['id_persona' => $persona->id_persona],
            $this->mapearCaracteristicas($datos)
        );

        return response()->json(['perfil' => ['id' => $persona->id_persona]]);
    }

    /**
     * PATCH /api/mi-perfil-roomie/estado
     * Mismo mecanismo que PublicacionController::actualizarEstado():
     * cambiar buscando/ya_encontro/pausado sin pasar por el wizard ni
     * borrar el perfil. Sin {id} en la ruta: siempre es el perfil del
     * usuario autenticado (relación 1 a 1, igual que update()/destroy()).
     *
     * Si se marca como NO buscando, las citas que sigan 'pendiente' se
     * rechazan automáticamente y se notifica a quien las agendó.
     */
    public function actualizarEstado(Request $request)
    {
        $usuario = $request->user();
        $persona = PersonaRoomie::where('id_usuario', $usuario->id_usuario)->first();

        if (! $persona) {
            abort(404, 'Aún no tienes un perfil de roomie publicado.');
        }

        $datos = $request->validate([
            'estado_busqueda' => 'required|in:buscando,ya_encontro,pausado',
        ]);

        $persona->update([
            'estado_busqueda' => $datos['estado_busqueda'],
            'fecha_actualizacion' => now(),
        ]);

        $citasRechazadas = 0;

        if ($datos['estado_busqueda'] !== 'buscando') {
            $pendientes = $persona->citas()->where('estado_cita', 'pendiente')->get();

            foreach ($pendientes as $cita) {
                $cita->update(['estado_cita' => 'rechazada', 'fecha_respuesta' => now()]);

                Notificacion::create([
                    'id_usuario' => $cita->id_usuario,
                    'tipo' => 'cita_rechazada',
                    'mensaje' => 'Este roomie ya no está buscando, así que tu solicitud de cita fue rechazada automáticamente.',
                    'objetivo_titulo' => $usuario->nombre_completo,
                    'link' => '/perfil#panelCitas',
                    'leida' => false,
                ]);
            }

            $citasRechazadas = $pendientes->count();
        }

        return response()->json([
            'mensaje' => 'Estado actualizado.',
            'estado_busqueda' => $persona->estado_busqueda,
            'citas_rechazadas' => $citasRechazadas,
        ]);
    }

    /**
     * DELETE /api/mi-perfil-roomie
     * Despublica el perfil propio (equivalente a eliminarPersonaExtra()).
     *
     * Borrado lógico (soft delete): el Model usa SoftDeletes, así que
     * $persona->delete() ya NO ejecuta un DELETE físico, solo marca
     * deleted_at. El ON DELETE CASCADE de la FK nunca se dispara y las
     * citas asociadas NO se eliminan ni cambian solas. Por eso, igual que
     * en actualizarEstado() al marcar "no buscando", rechazamos aquí
     * explícitamente las citas que sigan pendientes antes de eliminar.
     */
    public function destroy(Request $request)
    {
        $usuario = $request->user();
        $persona = PersonaRoomie::where('id_usuario', $usuario->id_usuario)->first();

        if (! $persona) {
            abort(404, 'Aún no tienes un perfil de roomie publicado.');
        }

        $pendientes = $persona->citas()->where('estado_cita', 'pendiente')->get();

        foreach ($pendientes as $cita) {
            $cita->update(['estado_cita' => 'rechazada', 'fecha_respuesta' => now()]);

            Notificacion::create([
                'id_usuario' => $cita->id_usuario,
                'tipo' => 'cita_rechazada',
                'mensaje' => 'Este perfil de roomie fue eliminado, así que tu solicitud de cita fue rechazada automáticamente.',
                'objetivo_titulo' => $usuario->nombre_completo,
                'link' => '/perfil#panelCitas',
                'leida' => false,
            ]);
        }

        $persona->delete(); // soft delete: solo marca deleted_at, el registro sigue en BD

        return response()->json(['mensaje' => 'Perfil de roomie eliminado.']);
    }

    // ------------------------------------------------------------

    private function sincronizarTelefonoUsuario($usuario, string $telefonoLimpio): void
    {
        if ($telefonoLimpio && $telefonoLimpio !== $usuario->telefono) {
            $usuario->update(['telefono' => $telefonoLimpio]);
        }
    }

    /**
     * Decodifica un data URL (base64) de imagen y lo guarda como archivo
     * real en storage/app/public/roomies. Devuelve la URL pública. Mismo
     * criterio que PublicacionController::guardarImagenBase64.
     */
    private function guardarImagenBase64(string $dataUrl): string
    {
        if (! preg_match('/^data:image\/(\w+);base64,/', $dataUrl, $tipo)) {
            throw new \InvalidArgumentException('Formato de imagen inválido.');
        }

        $extension = $tipo[1] === 'jpeg' ? 'jpg' : $tipo[1];
        $contenido = base64_decode(substr($dataUrl, strpos($dataUrl, ',') + 1));
        $ruta = 'roomies/' . Str::uuid() . '.' . $extension;

        Storage::disk('public')->put($ruta, $contenido);

        return Storage::url($ruta); // ej: /storage/roomies/xxxx.jpg
    }

    private function mapearCaracteristicas(array $datos): array
    {
        $booleanos = [
            'fumador', 'tiene_mascota', 'ordenado', 'sociable', 'madrugador',
            'trasnochador', 'fiestero', 'trabaja_desde_casa', 'viaja_frecuentemente',
            'acepta_mascotas', 'acepta_fumadores', 'acepta_visitas', 'acepta_parejas',
            'quiere_amueblado', 'quiere_bano_privado', 'quiere_parqueadero',
            'cerca_universidad', 'cerca_transporte_publico', 'tiene_vehiculo',
            'comparte_gastos', 'referencias_verificadas',
        ];

        $resultado = [];
        foreach ($booleanos as $campo) {
            $resultado[$campo] = (bool) ($datos[$campo] ?? false);
        }

        $resultado['ambiente_preferido'] = $datos['ambiente_preferido'] ?? null;
        $resultado['horario'] = $datos['horario'] ?? null;
        $resultado['tiempo_busqueda'] = $datos['tiempo_busqueda'];
        $resultado['fecha_mudanza'] = $datos['fecha_mudanza'];

        return $resultado;
    }

    private function formatearTarjeta(PersonaRoomie $p): array
    {
        return [
            'id' => $p->id_persona,
            'nombre' => $p->usuario->nombre_completo ?? 'Usuario',
            'edad' => $p->edad,
            'ocupacion' => $p->ocupacion,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'presupuesto' => (float) $p->presupuesto,
            'rating' => round($p->rating ?? 0, 1),
            'img' => $p->img,
            'tags' => $this->obtenerTags($p->caracteristicas, $p->ocupacion),
        ];
    }

    /**
     * Formatea el perfil propio completo para GET /api/mi-perfil-roomie.
     * Esta es la forma que espera crear-perfil-roomie.js para precargar
     * `estadoInicial()` en modo edición: mismos nombres de campo que
     * `personaEnEdicion` + `personaEnEdicion.caracteristicas` tenían con
     * roomies-data.js, para no tener que tocar esa lógica del wizard.
     */
    private function formatearDetalle(PersonaRoomie $p): array
    {
        return [
            'id' => $p->id_persona,
            'edad' => $p->edad,
            'genero' => $p->genero,
            'ocupacion' => $p->ocupacion,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'presupuesto' => (float) $p->presupuesto,
            'telefono' => $p->telefono,
            'img' => $p->img,
            'descripcion' => $p->descripcion,
            'estado_busqueda' => $p->estado_busqueda,
            'caracteristicas' => $p->caracteristicas,
        ];
    }

    /**
     * Formatea el detalle PÚBLICO para GET /api/roomies/{id} (distinto
     * de formatearDetalle(), que es para el dueño editando su propio
     * perfil). Esta es la forma que espera perfil-roomie.js: incluye
     * nombre, rating, reseñas y el propietario (para el chequeo de "es
     * mi propio perfil" por id, no por email).
     */
    private function formatearDetallePublico(PersonaRoomie $p): array
    {
        return [
            'id' => $p->id_persona,
            'nombre' => $p->usuario->nombre_completo ?? 'Usuario',
            'edad' => $p->edad,
            'genero' => $p->genero,
            'ocupacion' => $p->ocupacion,
            'zona' => $p->zona,
            'ciudad' => $p->ciudad,
            'presupuesto' => (float) $p->presupuesto,
            'descripcion' => $p->descripcion,
            'img' => $p->img,
            'rating' => $p->promedio_calificacion,
            'estado_busqueda' => $p->estado_busqueda,
            'caracteristicas' => $p->caracteristicas,
            'propietario' => $p->usuario ? [
                'id' => $p->usuario->id_usuario,
                'nombre' => $p->usuario->nombre_completo,
            ] : null,
            'resenas' => $p->calificaciones->map(fn ($c) => [
                'autor' => $c->autor_nombre ?: optional($c->autor)->nombre_completo ?: 'Usuario',
                'fecha' => optional($c->fecha_calificacion)->format('Y-m-d'),
                'puntuacion' => $c->puntuacion,
                'comentario' => $c->comentario,
            ])->values(),
        ];
    }

    private function obtenerTags(?CaracteristicasPersona $c, string $ocupacion): array
    {
        $tags = [$ocupacion];

        if ($c) {
            if ($c->tiene_mascota) $tags[] = 'Tiene mascota';
            if ($c->ordenado) $tags[] = 'Ordenado/a';
            if (! $c->fumador) $tags[] = 'No fumador/a';
            if ($c->trabaja_desde_casa) $tags[] = 'Trabaja desde casa';
            if ($c->tiempo_busqueda === 'largo') $tags[] = 'Busca largo plazo';
            if ($c->referencias_verificadas) $tags[] = 'Referencias verificadas';
        }

        return array_slice($tags, 0, 4);
    }
}
