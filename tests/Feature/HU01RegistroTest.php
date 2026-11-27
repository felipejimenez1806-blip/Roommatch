<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\FeatureTestCase;

/**
 * HU-01 Registrarme en el sistema ingresando mis datos personales.
 * Criterios: (1) registro exitoso, (2) correo ya registrado.
 * Además: validaciones de borde y seguridad básica (RNF-02).
 */
class HU01RegistroTest extends FeatureTestCase
{
    private function datos(array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'María Prueba',
            'email' => 'maria.prueba@example.com',
            'telefono' => '300 111 2233',
            'direccion' => 'Calle 45 # 12-30, Bogotá',
            'password' => self::CLAVE,
            'password_confirmation' => self::CLAVE,
            'acepta_terminos' => true,
        ], $cambios);
    }

    /** CP-01: con datos válidos el sistema crea la cuenta y abre la sesión. */
    public function test_cp01_registro_exitoso_crea_la_cuenta_con_rol_cliente(): void
    {
        $respuesta = $this->comoNavegador()->postJson('/api/registro', $this->datos());

        $respuesta->assertStatus(201)
            ->assertJsonPath('usuario.email', 'maria.prueba@example.com')
            ->assertJsonPath('rolActivo', 'cliente');

        $usuario = Usuario::where('correo', 'maria.prueba@example.com')->firstOrFail();

        $this->assertSame('+573001112233', $usuario->telefono, 'El teléfono se guarda normalizado.');
        $this->assertSame('local', $usuario->proveedor);
        $this->assertNotNull($usuario->terminos_aceptados_en, 'Debe quedar la fecha de aceptación de términos.');
        $this->assertTrue($usuario->tieneRol('cliente'));
        $this->assertFalse($usuario->tieneRol('admin'), 'El registro público nunca da rol admin.');
        $this->assertAuthenticated('web');
    }

    /** RNF-02: la contraseña se guarda cifrada, nunca en texto plano. */
    public function test_cp02_la_contrasena_se_guarda_cifrada(): void
    {
        $this->comoNavegador()->postJson('/api/registro', $this->datos())->assertStatus(201);

        $guardada = Usuario::where('correo', 'maria.prueba@example.com')->value('contrasena');

        $this->assertNotSame(self::CLAVE, $guardada);
        $this->assertTrue(Hash::check(self::CLAVE, $guardada));
    }

    /** CP-03: si el correo ya existe, muestra el mensaje y no crea otra cuenta. */
    public function test_cp03_correo_ya_registrado_muestra_mensaje(): void
    {
        $this->crearUsuario(['correo' => 'maria.prueba@example.com']);

        $this->comoNavegador()->postJson('/api/registro', $this->datos())
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonPath('errors.email.0', 'Ya existe una cuenta con este correo.');

        $this->assertSame(1, Usuario::where('correo', 'maria.prueba@example.com')->count());
    }

    public static function datosInvalidos(): array
    {
        return [
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'nombre demasiado corto' => [['nombre' => 'Al'], 'nombre'],
            'correo sin formato' => [['email' => 'no-es-un-correo'], 'email'],
            'teléfono corto' => [['telefono' => '12345'], 'telefono'],
            'teléfono fijo' => [['telefono' => '6011234567'], 'telefono'],
            'sin dirección' => [['direccion' => ''], 'direccion'],
            'contraseña corta' => [['password' => 'Ab1#', 'password_confirmation' => 'Ab1#'], 'password'],
            'contraseña sin mayúscula' => [['password' => 'zx9#lmq!tpw4', 'password_confirmation' => 'zx9#lmq!tpw4'], 'password'],
            'contraseña sin símbolo' => [['password' => 'Zx9Lmqtpw44', 'password_confirmation' => 'Zx9Lmqtpw44'], 'password'],
            'contraseña con el nombre' => [['password' => 'Prueba#1234', 'password_confirmation' => 'Prueba#1234'], 'password'],
            'contraseñas distintas' => [['password_confirmation' => 'Otra#Clave9'], 'password'],
            'sin aceptar términos' => [['acepta_terminos' => false], 'acepta_terminos'],
        ];
    }

    /** CP-04: datos inválidos o de borde se rechazan con 422 y no se crea la cuenta. */
    #[DataProvider('datosInvalidos')]
    public function test_cp04_datos_invalidos_son_rechazados(array $cambios, string $campoConError): void
    {
        $this->comoNavegador()->postJson('/api/registro', $this->datos($cambios))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$campoConError]);

        $this->assertSame(0, Usuario::count());
    }

    /** CP-05: el formulario vacío marca todos los campos obligatorios. */
    public function test_cp05_formulario_vacio_marca_los_campos_obligatorios(): void
    {
        $this->comoNavegador()->postJson('/api/registro', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'email', 'telefono', 'direccion', 'password', 'acepta_terminos']);
    }

    /** CP-06 (seguridad): texto de inyección SQL en el correo se rechaza como correo inválido. */
    public function test_cp06_inyeccion_sql_en_el_correo_es_rechazada(): void
    {
        $this->comoNavegador()->postJson('/api/registro', $this->datos(['email' => "' OR '1'='1"]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        $this->assertSame(0, Usuario::count());
    }
}
