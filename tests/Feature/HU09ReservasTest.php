<?php

namespace Tests\Feature;

use App\Models\Reserva;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

/**
 * HU-09 Solicitar una reserva (visita) de una publicación.
 * Cubre la solicitud válida, el horario ocupado, la publicación no disponible,
 * las validaciones del formulario y las reglas de acceso.
 */
class HU09ReservasTest extends FeatureTestCase
{
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'fecha_visita' => now()->addDays(3)->toDateString(),
            'hora_visita' => '10:00',
            'duracion_estimada' => '1-3',
            'contacto_nombre' => 'Carlos Buscador',
            'contacto_correo' => 'carlos.buscador@example.com',
            'contacto_telefono' => '310 555 1234',
        ], $cambios);
    }

    private function url(int $idPublicacion): string
    {
        return "/api/publicaciones/{$idPublicacion}/reservas";
    }

    /** CP-26: una solicitud válida crea la reserva pendiente y avisa al dueño. */
    public function test_cp26_solicitud_valida_crea_reserva_pendiente_y_notifica_al_dueno(): void
    {
        $dueno = $this->crearUsuario();
        $buscador = $this->crearUsuario();
        $publicacion = $this->crearPublicacion($dueno);

        $this->iniciarSesionComo($buscador)
            ->postJson($this->url($publicacion->id_publicacion), $this->datos())
            ->assertStatus(201)
            ->assertJsonStructure(['reserva' => ['id']]);

        $reserva = Reserva::firstOrFail();

        $this->assertSame('pendiente', $reserva->estado_reserva);
        $this->assertSame($buscador->id_usuario, $reserva->id_usuario);
        $this->assertSame($publicacion->id_publicacion, $reserva->id_publicacion);
        $this->assertSame(now()->addDays(3)->toDateString(), $reserva->fecha_visita->toDateString());
        $this->assertStringStartsWith('10:00', (string) $reserva->hora_visita);
        $this->assertSame('+573105551234', $reserva->contacto_telefono, 'El teléfono se guarda normalizado.');

        $this->assertDatabaseHas('notificacion', [
            'id_usuario' => $dueno->id_usuario,
            'tipo' => 'reserva_nueva',
        ]);
    }

    /** CP-27: sin sesión no se puede reservar. */
    public function test_cp27_sin_sesion_no_se_puede_reservar(): void
    {
        $publicacion = $this->crearPublicacion($this->crearUsuario());

        $this->postJson($this->url($publicacion->id_publicacion), $this->datos())->assertStatus(401);

        $this->assertSame(0, Reserva::count());
    }

    /** CP-28: con el perfil incompleto no se puede reservar. */
    public function test_cp28_perfil_incompleto_no_puede_reservar(): void
    {
        $publicacion = $this->crearPublicacion($this->crearUsuario());
        $buscador = $this->crearUsuario(['telefono' => null]);

        $this->iniciarSesionComo($buscador)
            ->postJson($this->url($publicacion->id_publicacion), $this->datos())
            ->assertStatus(409);

        $this->assertSame(0, Reserva::count());
    }

    /** CP-29: no se puede reservar la propia publicación. */
    public function test_cp29_no_se_puede_reservar_la_propia_publicacion(): void
    {
        $dueno = $this->crearUsuario();
        $publicacion = $this->crearPublicacion($dueno);

        $this->iniciarSesionComo($dueno)
            ->postJson($this->url($publicacion->id_publicacion), $this->datos())
            ->assertStatus(403);

        $this->assertSame(0, Reserva::count());
    }

    /** CP-30: una publicación no disponible no recibe solicitudes. */
    public function test_cp30_publicacion_no_disponible_rechaza_la_solicitud(): void
    {
        $publicacion = $this->crearPublicacion($this->crearUsuario(), ['estado_inmueble' => 'no_disponible']);

        $this->iniciarSesionComo($this->crearUsuario())
            ->postJson($this->url($publicacion->id_publicacion), $this->datos())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['estado_inmueble']);

        $this->assertSame(0, Reserva::count());
    }

    /** CP-31: dos personas no pueden tomar el mismo horario de la misma publicación. */
    public function test_cp31_horario_ocupado_es_rechazado(): void
    {
        $publicacion = $this->crearPublicacion($this->crearUsuario());
        $primero = $this->crearUsuario();
        $segundo = $this->crearUsuario();
        $datos = $this->datos();

        Reserva::create([
            'id_usuario' => $primero->id_usuario,
            'id_publicacion' => $publicacion->id_publicacion,
            'fecha_visita' => $datos['fecha_visita'],
            'hora_visita' => $datos['hora_visita'],
            'duracion_estimada' => '1-3',
            'contacto_nombre' => 'Primera Persona',
            'contacto_correo' => 'primera@example.com',
            'contacto_telefono' => '+573105551234',
        ]);

        $this->iniciarSesionComo($segundo)
            ->postJson($this->url($publicacion->id_publicacion), $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['hora_visita']);

        $this->assertSame(1, Reserva::count());
    }

    /** CP-32: el mismo horario en otra fecha sí está libre. */
    public function test_cp32_mismo_horario_en_otra_fecha_si_se_permite(): void
    {
        $publicacion = $this->crearPublicacion($this->crearUsuario());
        $primero = $this->crearUsuario();
        $segundo = $this->crearUsuario();

        Reserva::create([
            'id_usuario' => $primero->id_usuario,
            'id_publicacion' => $publicacion->id_publicacion,
            'fecha_visita' => now()->addDays(3)->toDateString(),
            'hora_visita' => '10:00',
            'duracion_estimada' => '1-3',
            'contacto_nombre' => 'Primera Persona',
            'contacto_correo' => 'primera@example.com',
            'contacto_telefono' => '+573105551234',
        ]);

        $this->iniciarSesionComo($segundo)
            ->postJson($this->url($publicacion->id_publicacion), $this->datos(['fecha_visita' => now()->addDays(4)->toDateString()]))
            ->assertStatus(201);

        $this->assertSame(2, Reserva::count());
    }

    /** CP-33: una publicación que no existe responde 404. */
    public function test_cp33_publicacion_inexistente_responde_404(): void
    {
        $this->iniciarSesionComo($this->crearUsuario())
            ->postJson($this->url(999999), $this->datos())
            ->assertStatus(404);
    }

    public static function datosInvalidos(): array
    {
        return [
            'fecha en el pasado' => ['fecha_visita', date('Y-m-d', strtotime('-2 days')), 'fecha_visita'],
            'sin fecha' => ['fecha_visita', '', 'fecha_visita'],
            'hora fuera del bloque de 30 min' => ['hora_visita', '10:15', 'hora_visita'],
            'hora imposible' => ['hora_visita', '25:00', 'hora_visita'],
            'sin hora' => ['hora_visita', '', 'hora_visita'],
            'duración inválida' => ['duracion_estimada', '20 años', 'duracion_estimada'],
            'correo inválido' => ['contacto_correo', 'no-es-correo', 'contacto_correo'],
            'teléfono inválido' => ['contacto_telefono', '12345', 'contacto_telefono'],
            'nombre vacío' => ['contacto_nombre', '', 'contacto_nombre'],
        ];
    }

    /** CP-34: datos inválidos o de borde se rechazan con 422 y no se crea la reserva. */
    #[DataProvider('datosInvalidos')]
    public function test_cp34_datos_invalidos_son_rechazados(string $campo, string $valor, string $campoConError): void
    {
        $publicacion = $this->crearPublicacion($this->crearUsuario());

        $this->iniciarSesionComo($this->crearUsuario())
            ->postJson($this->url($publicacion->id_publicacion), $this->datos([$campo => $valor]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campoConError]);

        $this->assertSame(0, Reserva::count());
    }
}
