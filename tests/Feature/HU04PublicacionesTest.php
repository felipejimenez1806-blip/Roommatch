<?php

namespace Tests\Feature;

use App\Models\Publicacion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

/**
 * HU-04 Crear una publicación de un espacio (y editarla).
 * Cubre el criterio de aceptación, las validaciones del formulario (precio,
 * zona, fecha, teléfono, fotos, habitantes) y los permisos (RNF-02).
 */
class HU04PublicacionesTest extends FeatureTestCase
{
    /** PNG de 1x1 píxel: una imagen real, mínima, para probar la carga de fotos. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function foto(): string
    {
        return 'data:image/png;base64,' . self::PNG;
    }

    /** Alarga un texto con relleno hasta alcanzar el mínimo de caracteres exigido. */
    private function texto(string $base, int $minimo): string
    {
        while (mb_strlen($base) < $minimo) {
            $base .= ' texto';
        }

        return $base;
    }

    private function datos(): array
    {
        $l = config('roommatch.limites');

        return [
            'tipo_espacio' => 'Habitación',
            'titulo' => $this->texto('Habitación luminosa cerca al transporte', $l['titulo_min']),
            'zona' => 'Chapinero',
            'ciudad' => 'Bogotá',
            'direccion' => $this->texto('Carrera 7 # 45-10', $l['direccion_min']),
            'precio' => min(max($l['precio_min'], 850000), $l['precio_max']),
            'fecha_disponible' => now()->addDays(10)->toDateString(),
            'genero' => 'otro',
            'telefono_contacto' => '300 111 2233',
            'descripcion' => $this->texto('Habitación amplia, amoblada, con baño privado y buena luz natural.', $l['descripcion_min']),
            'imagenes' => [$this->foto()],
            'caracteristicas' => [
                'amueblado' => true,
                'bano_privado' => true,
                'numero_habitantes' => 2,
                'ambiente_hogar' => 'profesionales',
                'tamano_habitacion' => 'mediana',
                'tipo_cama' => 'doble',
                'horario_silencio' => '10:00pm - 7:00am',
                'horario_entrada' => 'Hasta las 11:00pm',
                'distancia_transporte' => '250 metros',
                'detalles_incluidos' => 'Servicios incluidos',
            ],
        ];
    }

    /** CP-17: con datos válidos se crea la publicación, sus características y se guarda la foto. */
    public function test_cp17_crear_publicacion_con_datos_validos(): void
    {
        $dueno = $this->crearUsuario();

        $respuesta = $this->iniciarSesionComo($dueno)->postJson('/api/mis-publicaciones', $this->datos());

        $respuesta->assertStatus(201)->assertJsonStructure(['publicacion' => ['id']]);

        $id = $respuesta->json('publicacion.id');

        $this->assertDatabaseHas('publicacion', [
            'id_publicacion' => $id,
            'id_usuario' => $dueno->id_usuario,
            'zona' => 'Chapinero',
            'tipo_espacio' => 'Habitación',
            'estado_inmueble' => 'disponible',
        ]);
        $this->assertDatabaseHas('caracteristicas_publicacion', [
            'id_publicacion' => $id,
            'bano_privado' => true,
        ]);
        $this->assertCount(1, Storage::disk('public')->allFiles('publicaciones'));
    }

    /** CP-18: sin sesión no se puede publicar. */
    public function test_cp18_sin_sesion_no_se_puede_publicar(): void
    {
        $this->postJson('/api/mis-publicaciones', $this->datos())->assertStatus(401);

        $this->assertSame(0, Publicacion::count());
    }

    /** CP-19: con el perfil incompleto (sin teléfono) no se puede publicar. */
    public function test_cp19_perfil_incompleto_no_puede_publicar(): void
    {
        $usuario = $this->crearUsuario(['telefono' => null]);

        $this->iniciarSesionComo($usuario)->postJson('/api/mis-publicaciones', $this->datos())
            ->assertStatus(409)
            ->assertJsonPath('mensaje', 'Completa tu perfil antes de continuar.');

        $this->assertSame(0, Publicacion::count());
    }

    public static function datosInvalidos(): array
    {
        return [
            'precio negativo' => ['precio', -5000, 'precio'],
            'precio absurdo' => ['precio', 99999999999, 'precio'],
            'precio con decimales' => ['precio', '850000.50', 'precio'],
            'zona fuera del catálogo' => ['zona', 'Narnia', 'zona'],
            'tipo de espacio inválido' => ['tipo_espacio', 'Castillo', 'tipo_espacio'],
            'título vacío' => ['titulo', '', 'titulo'],
            'descripción vacía' => ['descripcion', '', 'descripcion'],
            'género inválido' => ['genero', 'robot', 'genero'],
            'teléfono inválido' => ['telefono_contacto', '123', 'telefono_contacto'],
            'fecha en el pasado' => ['fecha_disponible', '2020-01-01', 'fecha_disponible'],
            'sin fotos' => ['imagenes', [], 'imagenes'],
            'foto que no es imagen' => ['imagenes.0', 'data:image/png;base64,AAAA', 'imagenes.0'],
            'foto con tipo peligroso (php)' => ['imagenes.0', 'data:image/php;base64,AAAA', 'imagenes.0'],
            'habitantes negativos' => ['caracteristicas.numero_habitantes', -1, 'caracteristicas.numero_habitantes'],
            'ambiente del hogar inválido' => ['caracteristicas.ambiente_hogar', 'espacial', 'caracteristicas.ambiente_hogar'],
        ];
    }

    /** CP-20: datos inválidos o de borde se rechazan con 422 y no se crea nada. */
    #[DataProvider('datosInvalidos')]
    public function test_cp20_datos_invalidos_son_rechazados(string $campo, mixed $valor, string $campoConError): void
    {
        $datos = $this->datos();
        Arr::set($datos, $campo, $valor);

        $this->iniciarSesionComo($this->crearUsuario())->postJson('/api/mis-publicaciones', $datos)
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campoConError]);

        $this->assertSame(0, Publicacion::count());
    }

    /** CP-21: el formulario vacío marca los campos obligatorios. */
    public function test_cp21_formulario_vacio_marca_campos_obligatorios(): void
    {
        $this->iniciarSesionComo($this->crearUsuario())->postJson('/api/mis-publicaciones', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'tipo_espacio', 'titulo', 'zona', 'direccion', 'precio',
                'fecha_disponible', 'telefono_contacto', 'descripcion', 'imagenes',
            ]);
    }

    /**
     * CP-22 (exploración): `caracteristicas` es opcional en el formulario
     * (nullable). Si no se envía, la publicación debería crearse igual.
     * Si esta prueba falla con 500, es un defecto real: el controlador lee
     * claves que no existen en el arreglo de características.
     */
    public function test_cp22_crear_publicacion_sin_caracteristicas_no_debe_fallar(): void
    {
        $datos = $this->datos();
        unset($datos['caracteristicas']);

        $this->iniciarSesionComo($this->crearUsuario())->postJson('/api/mis-publicaciones', $datos)
            ->assertStatus(201);
    }

    /** CP-23: el dueño puede editar su publicación. */
    public function test_cp23_el_dueno_puede_editar_su_publicacion(): void
    {
        $dueno = $this->crearUsuario();
        $publicacion = $this->crearPublicacion($dueno);

        $datos = $this->datos();
        $datos['imagenes'] = ['/storage/publicaciones/demo.jpg'];
        $datos['titulo'] = $this->texto('Titulo editado de la habitacion', config('roommatch.limites.titulo_min'));

        $this->iniciarSesionComo($dueno)
            ->putJson("/api/mis-publicaciones/{$publicacion->id_publicacion}", $datos)
            ->assertOk();

        $this->assertSame($datos['titulo'], $publicacion->fresh()->titulo);
    }

    /** CP-24 (seguridad): otro usuario no puede editar una publicación ajena. */
    public function test_cp24_no_se_puede_editar_la_publicacion_de_otro_usuario(): void
    {
        $dueno = $this->crearUsuario();
        $intruso = $this->crearUsuario();
        $publicacion = $this->crearPublicacion($dueno);

        $datos = $this->datos();
        $datos['imagenes'] = ['/storage/publicaciones/demo.jpg'];

        $this->iniciarSesionComo($intruso)
            ->putJson("/api/mis-publicaciones/{$publicacion->id_publicacion}", $datos)
            ->assertStatus(403);

        $this->assertSame('Habitación amplia cerca a la universidad', $publicacion->fresh()->titulo);
    }

    /** CP-25 (seguridad): otro usuario no puede cambiar el estado de una publicación ajena. */
    public function test_cp25_no_se_puede_cambiar_el_estado_de_una_publicacion_ajena(): void
    {
        $dueno = $this->crearUsuario();
        $intruso = $this->crearUsuario();
        $publicacion = $this->crearPublicacion($dueno);

        $this->iniciarSesionComo($intruso)
            ->patchJson("/api/mis-publicaciones/{$publicacion->id_publicacion}/estado", ['estado_inmueble' => 'no_disponible'])
            ->assertStatus(403);

        $this->assertSame('disponible', $publicacion->fresh()->estado_inmueble);
    }
}
