<?php

namespace Tests;

use App\Models\Publicacion;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\SesionUsuario;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Contraseña de todos los usuarios de prueba (cumple las reglas de RegistroRequest). */
    public const CLAVE = 'Zx9#Lmq!Tpw4';

    /**
     * Protección: RefreshDatabase borra y recrea TODAS las tablas. Antes de que
     * eso ocurra se comprueba que la base sea una base de pruebas (su nombre
     * debe contener "test"), para no borrar nunca la base de desarrollo.
     */
    protected function setUpTraits()
    {
        $this->verificarBaseDePruebas();

        return parent::setUpTraits();
    }

    private function verificarBaseDePruebas(): void
    {
        $conexion = config('database.default');
        $base = (string) config("database.connections.{$conexion}.database");

        if ($base !== ':memory:' && ! str_contains(strtolower($base), 'test')) {
            throw new RuntimeException(
                "Por seguridad las pruebas solo corren sobre una base cuyo nombre contenga 'test'. "
                . "Base configurada: '{$base}'. Revisa phpunit.xml (DB_DATABASE)."
            );
        }
    }

    /**
     * Las rutas /api/* solo abren sesión si la petición viene del propio sitio
     * (Origin/Referer dentro de SANCTUM_STATEFUL_DOMAINS), igual que desde el
     * navegador. Se toma el primer dominio configurado.
     */
    protected function cabecerasWeb(): array
    {
        $dominio = collect(config('sanctum.stateful', []))->filter()->first() ?? 'localhost';

        return ['Referer' => "http://{$dominio}", 'Origin' => "http://{$dominio}"];
    }

    /** Petición como si viniera del navegador del sitio (con sesión), pero sin usuario. */
    protected function comoNavegador(): static
    {
        return $this->withHeaders($this->cabecerasWeb());
    }

    /** Petición como un usuario con la sesión abierta con el rol indicado. */
    protected function iniciarSesionComo(Usuario $usuario, string $rol = 'cliente'): static
    {
        return $this->actingAs($usuario->loadMissing('roles'), 'web')
            ->withSession([SesionUsuario::ROL_ACTIVO => $rol])
            ->withHeaders($this->cabecerasWeb());
    }

    /** Crea un usuario local con contraseña CLAVE y los roles indicados. */
    protected function crearUsuario(array $atributos = [], array $roles = ['cliente']): Usuario
    {
        static $n = 0;
        $n++;

        $usuario = Usuario::create(array_merge([
            'nombre_completo' => "Usuario Prueba {$n}",
            'correo' => "usuario{$n}@example.com",
            'contrasena' => Hash::make(self::CLAVE),
            'proveedor' => 'local',
            'telefono' => '+573001112233',
            'direccion' => 'Calle 1 # 2-3, Bogotá',
            'terminos_aceptados_en' => now(),
        ], $atributos));

        foreach ($roles as $nombre) {
            $usuario->roles()->attach(Rol::where('nombre', $nombre)->firstOrFail()->id_rol, [
                'fecha_asignacion' => now(),
                'asignado_por' => null,
            ]);
        }

        return $usuario->load('roles');
    }

    /** Crea una publicación válida y disponible del dueño indicado. */
    protected function crearPublicacion(Usuario $dueno, array $atributos = []): Publicacion
    {
        return Publicacion::create(array_merge([
            'id_usuario' => $dueno->id_usuario,
            'titulo' => 'Habitación amplia cerca a la universidad',
            'descripcion' => 'Habitación amoblada, con baño privado y buena iluminación natural.',
            'precio' => 900000,
            'tipo_espacio' => 'Habitación',
            'direccion' => 'Carrera 7 # 45-10',
            'zona' => 'Chapinero',
            'ciudad' => 'Bogotá',
            'estado_inmueble' => 'disponible',
            'img' => '/storage/publicaciones/demo.jpg',
            'imagenes' => ['/storage/publicaciones/demo.jpg'],
            'fecha_disponible' => now()->addDays(7)->toDateString(),
        ], $atributos));
    }
}
