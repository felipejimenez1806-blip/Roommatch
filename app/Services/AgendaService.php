<?php

namespace App\Services;

use App\Models\Agenda;
use App\Models\Cita;
use App\Models\Reserva;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mantiene la tabla "agenda" al día cuando una reserva o una cita cambia de estado.
 *
 * - aceptada            -> cada una de las dos personas tiene su fila (estado "activa")
 * - cancelada/rechazada -> las filas pasan a "cancelada" (no se borran: queda el historial)
 * - Aceptar o cancelar NUNCA se bloquea por esto: si algo falla, solo se escribe en el log.
 *
 * Los controladores llaman a avisarReserva() y avisarCita() igual que antes.
 * Reprogramar y cancelar desde la agenda de Java lo hacen los procedimientos almacenados.
 */
class AgendaService
{
    public function avisarReserva(Reserva $reserva): void
    {
        $this->sinRomper('reserva', $reserva->id_reserva, function () use ($reserva) {
            if ($reserva->estado_reserva !== 'aceptada') {
                $this->cancelarFilas('reserva', $reserva->id_reserva);

                return;
            }

            $reserva->loadMissing(['publicacion', 'solicitante']);
            $publicacion = $reserva->publicacion;
            if (! $publicacion) {
                return;
            }

            $solicitante = $reserva->contacto_nombre ?: ($reserva->solicitante->nombre_completo ?? 'Un usuario');

            $this->guardar([
                'tipo' => 'reserva',
                'id_origen' => $reserva->id_reserva,
                'lugar' => $this->cortar(implode(', ', array_filter([$publicacion->direccion, $publicacion->zona, $publicacion->ciudad])), 255),
                'fecha' => $reserva->fecha_visita->format('Y-m-d'),
                'hora' => $reserva->hora_visita,
            ], [
                // Quien reservó
                [
                    'id_usuario' => $reserva->id_usuario,
                    'id_otro' => $publicacion->id_usuario,
                    'es_solicitante' => true,
                    'titulo' => $this->cortar("Visita a \"{$publicacion->titulo}\"", 255),
                ],
                // El dueño de la publicación
                [
                    'id_usuario' => $publicacion->id_usuario,
                    'id_otro' => $reserva->id_usuario,
                    'es_solicitante' => false,
                    'titulo' => $this->cortar("{$solicitante} visita \"{$publicacion->titulo}\"", 255),
                ],
            ]);
        });
    }

    public function avisarCita(Cita $cita): void
    {
        $this->sinRomper('cita', $cita->id_cita, function () use ($cita) {
            if ($cita->estado_cita !== 'aceptada') {
                $this->cancelarFilas('cita', $cita->id_cita);

                return;
            }

            $cita->loadMissing(['persona.usuario', 'solicitante']);
            $persona = $cita->persona;
            if (! $persona) {
                return;
            }

            $roomie = $persona->usuario->nombre_completo ?? 'tu roomie';
            $solicitante = $cita->contacto_nombre ?: ($cita->solicitante->nombre_completo ?? 'Un usuario');

            $this->guardar([
                'tipo' => 'cita',
                'id_origen' => $cita->id_cita,
                'lugar' => $this->cortar((string) $cita->lugar_encuentro, 255),
                'fecha' => $cita->fecha_cita->format('Y-m-d'),
                'hora' => $cita->hora_cita,
            ], [
                // Quien pidió la cita
                [
                    'id_usuario' => $cita->id_usuario,
                    'id_otro' => $persona->id_usuario,
                    'es_solicitante' => true,
                    'titulo' => $this->cortar("Cita con {$roomie}", 255),
                ],
                // El dueño del perfil roomie
                [
                    'id_usuario' => $persona->id_usuario,
                    'id_otro' => $cita->id_usuario,
                    'es_solicitante' => false,
                    'titulo' => $this->cortar("Cita con {$solicitante}", 255),
                ],
            ]);
        });
    }

    /** Crea la fila de cada persona, o la actualiza si ya existía (nunca duplica). */
    private function guardar(array $comun, array $personas): void
    {
        foreach ($personas as $persona) {
            Agenda::updateOrCreate(
                ['id_usuario' => $persona['id_usuario'], 'tipo' => $comun['tipo'], 'id_origen' => $comun['id_origen']],
                $comun + $persona + ['estado' => 'activa']
            );
        }
    }

    /** Las filas de ese evento dejan de verse en el calendario. */
    private function cancelarFilas(string $tipo, int $idOrigen): void
    {
        Agenda::where('tipo', $tipo)->where('id_origen', $idOrigen)->update(['estado' => 'cancelada']);
    }

    /** Si la agenda falla, aceptar o cancelar la reserva/cita sigue funcionando. */
    private function sinRomper(string $tipo, int $id, callable $accion): void
    {
        try {
            $accion();
        } catch (Throwable $e) {
            Log::error('AgendaService: no se pudo actualizar la agenda', ['tipo' => $tipo, 'id' => $id, 'error' => $e->getMessage()]);
        }
    }

    private function cortar(string $texto, int $maximo): string
    {
        return Str::limit($texto, $maximo, '');
    }
}
