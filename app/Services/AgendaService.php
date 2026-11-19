<?php

namespace App\Services;

use App\Exceptions\ServicioJavaNoDisponibleException;
use App\Models\Cita;
use App\Models\Reserva;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Avisa al servicio de agenda (Java) cuando una reserva o cita se acepta o se cancela.
 *
 * - Aceptar o cancelar NUNCA se bloquea por esto: si Java está caído, el aviso queda en una
 *   lista de pendientes (en el caché, sin tablas ni migraciones) y se reenvía solo.
 * - Java no duplica: reenviar lo mismo es inofensivo (una fila por persona y por evento).
 * - Las reservas y citas se agendan en bloques de 30 minutos.
 */
class AgendaService
{
    private const CLAVE_PENDIENTES = 'agenda:pendientes';

    private const CLAVE_CARGA_INICIAL = 'agenda:carga_inicial';

    private const DURACION_MIN = 30;

    /** Tras un fallo de conexión no se vuelve a intentar durante este tiempo: aceptar/cancelar no espera a Java. */
    private const CLAVE_JAVA_CAIDO = 'agenda:java_caido';

    private const PAUSA_SEGUNDOS = 60;

    private const OK = 'ok';

    /** Sin conexión (o clave rechazada): no tiene sentido seguir intentando con el resto. */
    private const CAIDO = 'caido';

    /** Java respondió con un error del servidor: se reintenta luego, pero se sigue con los demás. */
    private const ERROR = 'error';

    /** Java rechazó el dato por inválido: reintentar no ayuda, se registra y se descarta. */
    private const DESCARTADO = 'descartado';

    public function __construct(private readonly ServicioJavaClient $java)
    {
    }

    // ------------------------------------------------------------------
    //  Avisos desde los controladores
    // ------------------------------------------------------------------

    public function avisarReserva(Reserva $reserva): void
    {
        $this->avisar('RESERVA', (int) $reserva->id_reserva, $this->payloadReserva($reserva));
    }

    public function avisarCita(Cita $cita): void
    {
        $this->avisar('CITA', (int) $cita->id_cita, $this->payloadCita($cita));
    }

    /**
     * @param  array<string, mixed>|null  $payload  null = este estado no va a la agenda (pendiente)
     */
    private function avisar(string $tipo, int $id, ?array $payload): void
    {
        if ($payload === null) {
            return;
        }

        try {
            // Si Java acaba de fallar, ni lo intentamos: queda pendiente y la respuesta es inmediata.
            if (Cache::has(self::CLAVE_JAVA_CAIDO)) {
                $this->encolar($tipo, $id);

                return;
            }

            $resultado = $this->enviar($payload);

            if ($resultado === self::CAIDO || $resultado === self::ERROR) {
                $this->encolar($tipo, $id);

                return;
            }

            // Java respondió bien: aprovecha para ponerse al día con lo que quedó pendiente.
            $this->ponerseAlDia();
        } catch (Throwable $e) {
            // Nunca romper aceptar/cancelar por un problema de la agenda.
            Log::error('AgendaService: fallo inesperado al avisar', ['tipo' => $tipo, 'id' => $id, 'error' => $e->getMessage()]);
            $this->encolar($tipo, $id);
        }
    }

    // ------------------------------------------------------------------
    //  Sincronización (la usa el comando agenda:sincronizar)
    // ------------------------------------------------------------------

    /**
     * Reenvía lo que no se pudo avisar. Normalmente la lista está vacía y no hace ninguna llamada.
     *
     * @return array{enviados: int, fallidos: int, pendientes: int}
     */
    public function sincronizarPendientes(int $maximo = 50): array
    {
        $pendientes = Cache::get(self::CLAVE_PENDIENTES, []);
        $enviados = 0;
        $fallidos = 0;

        foreach (array_slice($pendientes, 0, $maximo, true) as $clave => $dato) {
            [$tipo, $id] = $dato;
            $payload = $this->payloadActual((string) $tipo, (int) $id);

            // null = ya no existe o volvió a pendiente: no hay nada que avisar.
            $resultado = $payload === null ? self::OK : $this->enviar($payload);

            if ($resultado === self::OK || $resultado === self::DESCARTADO) {
                unset($pendientes[$clave]);
                $enviados++;
                continue;
            }

            $fallidos++;
            if ($resultado === self::CAIDO) {
                break;
            }
        }

        Cache::forever(self::CLAVE_PENDIENTES, $pendientes);

        return ['enviados' => $enviados, 'fallidos' => $fallidos, 'pendientes' => count($pendientes)];
    }

    /**
     * Reenvía TODAS las reservas y citas aceptadas o canceladas de los últimos N días.
     * Sirve para cargar lo que ya estaba aceptado antes de existir la agenda y como red de seguridad.
     *
     * @return array{enviados: int, fallidos: int, interrumpido: bool}
     */
    public function sincronizarTodo(?int $dias = null): array
    {
        $desde = now()->subDays($dias ?? (int) config('servicios.agenda_dias', 60))->toDateString();
        $enviados = 0;
        $fallidos = 0;
        $interrumpido = false;

        $reservas = Reserva::query()
            ->whereIn('estado_reserva', ['aceptada', 'cancelada', 'rechazada'])
            ->where('fecha_visita', '>=', $desde)
            ->with(['publicacion.propietario', 'solicitante'])
            ->get();

        $citas = Cita::query()
            ->whereIn('estado_cita', ['aceptada', 'cancelada', 'rechazada'])
            ->where('fecha_cita', '>=', $desde)
            ->with(['persona.usuario', 'solicitante'])
            ->get();

        $payloads = $reservas->map(fn (Reserva $r) => $this->payloadReserva($r))
            ->merge($citas->map(fn (Cita $c) => $this->payloadCita($c)))
            ->filter();

        foreach ($payloads as $payload) {
            $resultado = $this->enviar($payload);

            if ($resultado === self::OK || $resultado === self::DESCARTADO) {
                $enviados++;
                continue;
            }

            $fallidos++;
            if ($resultado === self::CAIDO) {
                $interrumpido = true;
                break;
            }
        }

        return ['enviados' => $enviados, 'fallidos' => $fallidos, 'interrumpido' => $interrumpido];
    }

    /**
     * La primera vez que Java responde, carga todo lo que ya estaba aceptado. Así no hay que
     * ejecutar nada a mano: ocurre solo con el comando programado o con el siguiente aviso.
     */
    public function cargaInicialSiHaceFalta(): void
    {
        if (Cache::has(self::CLAVE_CARGA_INICIAL)) {
            return;
        }

        $resultado = $this->sincronizarTodo();

        if ($resultado['fallidos'] === 0 && ! $resultado['interrumpido']) {
            Cache::forever(self::CLAVE_CARGA_INICIAL, now()->toDateTimeString());
        }
    }

    private function ponerseAlDia(): void
    {
        $this->sincronizarPendientes(10);
        $this->cargaInicialSiHaceFalta();
    }

    // ------------------------------------------------------------------
    //  Lista de pendientes (en el caché)
    // ------------------------------------------------------------------

    private function encolar(string $tipo, int $id): void
    {
        $pendientes = Cache::get(self::CLAVE_PENDIENTES, []);
        $pendientes["{$tipo}:{$id}"] = [$tipo, $id];
        Cache::forever(self::CLAVE_PENDIENTES, $pendientes);
    }

    /** @return array<string, mixed>|null */
    private function payloadActual(string $tipo, int $id): ?array
    {
        if ($tipo === 'RESERVA') {
            $reserva = Reserva::find($id);

            return $reserva ? $this->payloadReserva($reserva) : null;
        }

        $cita = Cita::find($id);

        return $cita ? $this->payloadCita($cita) : null;
    }

    // ------------------------------------------------------------------
    //  Armado de los datos (solo ids, nombres, fecha, hora y lugar; nunca teléfonos ni correos)
    // ------------------------------------------------------------------

    /** @return array<string, mixed>|null */
    private function payloadReserva(Reserva $reserva): ?array
    {
        $estado = $this->estadoAgenda($reserva->estado_reserva);
        if ($estado === null) {
            return null;
        }

        $reserva->loadMissing(['publicacion.propietario', 'solicitante']);
        $publicacion = $reserva->publicacion;
        if (! $publicacion) {
            return null;
        }

        $nombreAnfitrion = $publicacion->propietario->nombre_completo ?? 'el anfitrión';
        $nombreSolicitante = $reserva->contacto_nombre ?: ($reserva->solicitante->nombre_completo ?? 'Un usuario');
        $lugar = trim("{$publicacion->direccion}, {$publicacion->zona}, {$publicacion->ciudad}", ' ,');

        return [
            'tipo' => 'RESERVA',
            'idOrigen' => (int) $reserva->id_reserva,
            'estado' => $estado,
            'fecha' => $reserva->fecha_visita->format('Y-m-d'),
            'hora' => $this->hora($reserva->hora_visita),
            'duracionMin' => self::DURACION_MIN,
            'lugar' => $this->cortar($lugar, 200),
            'participantes' => [
                [
                    'idUsuario' => (int) $reserva->id_usuario,
                    'titulo' => $this->cortar("Visita a \"{$publicacion->titulo}\"", 150),
                    'descripcion' => $this->cortar("Anfitrión: {$nombreAnfitrion}", 500),
                    'contraparte' => $this->cortar($nombreAnfitrion, 150),
                ],
                [
                    'idUsuario' => (int) $publicacion->id_usuario,
                    'titulo' => $this->cortar("{$nombreSolicitante} visita \"{$publicacion->titulo}\"", 150),
                    'descripcion' => 'Visita a tu publicación.',
                    'contraparte' => $this->cortar($nombreSolicitante, 150),
                ],
            ],
        ];
    }

    /** @return array<string, mixed>|null */
    private function payloadCita(Cita $cita): ?array
    {
        $estado = $this->estadoAgenda($cita->estado_cita);
        if ($estado === null) {
            return null;
        }

        $cita->loadMissing(['persona.usuario', 'solicitante']);
        $persona = $cita->persona;
        if (! $persona) {
            return null;
        }

        $nombreRoomie = $persona->usuario->nombre_completo ?? 'tu roomie';
        $nombreSolicitante = $cita->contacto_nombre ?: ($cita->solicitante->nombre_completo ?? 'Un usuario');

        return [
            'tipo' => 'CITA',
            'idOrigen' => (int) $cita->id_cita,
            'estado' => $estado,
            'fecha' => $cita->fecha_cita->format('Y-m-d'),
            'hora' => $this->hora($cita->hora_cita),
            'duracionMin' => self::DURACION_MIN,
            'lugar' => $this->cortar((string) $cita->lugar_encuentro, 200),
            'participantes' => [
                [
                    'idUsuario' => (int) $cita->id_usuario,
                    'titulo' => $this->cortar("Cita con {$nombreRoomie}", 150),
                    'descripcion' => 'Encuentro para conocerse.',
                    'contraparte' => $this->cortar($nombreRoomie, 150),
                ],
                [
                    'idUsuario' => (int) $persona->id_usuario,
                    'titulo' => $this->cortar("Cita con {$nombreSolicitante}", 150),
                    'descripcion' => 'Encuentro para conocerse.',
                    'contraparte' => $this->cortar($nombreSolicitante, 150),
                ],
            ],
        ];
    }

    /** aceptada entra a la agenda; cancelada o rechazada la saca; pendiente no se avisa. */
    private function estadoAgenda(?string $estado): ?string
    {
        return match ($estado) {
            'aceptada' => 'ACEPTADA',
            'cancelada', 'rechazada' => 'CANCELADA',
            default => null,
        };
    }

    /** La base guarda HH:MM:SS; Java recibe HH:MM (o nulo si no hay hora). */
    private function hora(?string $hora): ?string
    {
        return $hora ? substr($hora, 0, 5) : null;
    }

    private function cortar(string $texto, int $maximo): string
    {
        return mb_strlen($texto) > $maximo ? mb_substr($texto, 0, $maximo - 1).'…' : $texto;
    }

    // ------------------------------------------------------------------
    //  Envío a Java
    // ------------------------------------------------------------------

    /** @param  array<string, mixed>  $payload */
    private function enviar(array $payload): string
    {
        try {
            $respuesta = $this->java->enviarEventoAgenda($payload);

            if ($respuesta->successful()) {
                Cache::forget(self::CLAVE_JAVA_CAIDO);

                return self::OK;
            }

            $contexto = [
                'estado' => $respuesta->status(),
                'tipo' => $payload['tipo'],
                'id' => $payload['idOrigen'],
                'cuerpo' => Str::limit($respuesta->body(), 300),
            ];

            // 401/403: la clave no coincide (se arregla en la configuración y se reintenta).
            if (in_array($respuesta->status(), [401, 403], true)) {
                Log::error('AgendaService: Java rechazó la clave de servicio (revisa ROOMMATCH_SERVICE_KEY).', $contexto);
                Cache::put(self::CLAVE_JAVA_CAIDO, true, self::PAUSA_SEGUNDOS);

                return self::CAIDO;
            }

            if ($respuesta->clientError()) {
                Log::error('AgendaService: Java rechazó el evento por inválido; se descarta.', $contexto);

                return self::DESCARTADO;
            }

            Log::warning('AgendaService: Java falló al guardar el evento; se reintentará.', $contexto);

            return self::ERROR;
        } catch (ServicioJavaNoDisponibleException $e) {
            Log::warning('AgendaService: el servicio de agenda no responde; el aviso queda pendiente.', [
                'tipo' => $payload['tipo'],
                'id' => $payload['idOrigen'],
            ]);
            Cache::put(self::CLAVE_JAVA_CAIDO, true, self::PAUSA_SEGUNDOS);

            return self::CAIDO;
        }
    }
}
