<?php

namespace Tests\Feature;

use App\Models\Usuario;
use Tests\FeatureTestCase;

/**
 * HU-02 Iniciar sesión en la plataforma.
 * Criterios: (1) inicio de sesión exitoso, (2) credenciales incorrectas.
 * Además: bloqueo por intentos fallidos y mensajes que no revelan qué dato falló (RNF-02).
 */
class HU02InicioSesionTest extends FeatureTestCase
{
    private const MENSAJE_ERROR = 'Datos incorrectos vuelva a intentar.';

    /** CP-07: con credenciales correctas el sistema permite el acceso. */
    public function test_cp07_inicio_de_sesion_exitoso(): void
    {
        $usuario = $this->crearUsuario(['correo' => 'ana.login@example.com']);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'ana.login@example.com',
            'password' => self::CLAVE,
        ])
            ->assertOk()
            ->assertJsonPath('mensaje', '¡Bienvenido a RoomMatch!')
            ->assertJsonPath('usuario.email', 'ana.login@example.com')
            ->assertJsonPath('rolActivo', 'cliente');

        $this->assertAuthenticatedAs($usuario, 'web');
    }

    /** CP-08: contraseña incorrecta muestra error y no abre sesión. */
    public function test_cp08_contrasena_incorrecta_es_rechazada(): void
    {
        $this->crearUsuario(['correo' => 'ana.login@example.com']);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'ana.login@example.com',
            'password' => 'Incorrecta#99',
        ])
            ->assertStatus(401)
            ->assertJsonPath('mensaje', self::MENSAJE_ERROR);

        $this->assertGuest('web');
    }

    /** CP-09: el mensaje es el mismo si el correo no existe (no revela cuál dato falló). */
    public function test_cp09_correo_inexistente_da_el_mismo_mensaje_que_contrasena_incorrecta(): void
    {
        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'nadie@example.com',
            'password' => self::CLAVE,
        ])
            ->assertStatus(401)
            ->assertJsonPath('mensaje', self::MENSAJE_ERROR);

        $this->assertGuest('web');
    }

    /** CP-10: campos vacíos se rechazan con 422. */
    public function test_cp10_campos_vacios_son_rechazados(): void
    {
        $this->comoNavegador()->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /** CP-11 (seguridad): inyección SQL en el correo no entra. */
    public function test_cp11_inyeccion_sql_en_el_correo_no_permite_entrar(): void
    {
        $this->crearUsuario(['correo' => 'ana.login@example.com']);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => "' OR '1'='1",
            'password' => "' OR '1'='1",
        ])->assertStatus(422);

        $this->assertGuest('web');
    }

    /** CP-12 (seguridad): inyección SQL en la contraseña tampoco entra. */
    public function test_cp12_inyeccion_sql_en_la_contrasena_no_permite_entrar(): void
    {
        $this->crearUsuario(['correo' => 'ana.login@example.com']);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'ana.login@example.com',
            'password' => "' OR '1'='1",
        ])
            ->assertStatus(401)
            ->assertJsonPath('mensaje', self::MENSAJE_ERROR);

        $this->assertGuest('web');
    }

    /** CP-13: tras 5 contraseñas incorrectas la cuenta se bloquea, aun con la clave correcta. */
    public function test_cp13_cinco_intentos_fallidos_bloquean_la_cuenta(): void
    {
        $usuario = $this->crearUsuario(['correo' => 'ana.login@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->comoNavegador()->postJson('/api/login', [
                'email' => 'ana.login@example.com',
                'password' => 'Incorrecta#99',
            ])->assertStatus(401);
        }

        $this->assertTrue($usuario->fresh()->bloqueado);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'ana.login@example.com',
            'password' => self::CLAVE,
        ])->assertStatus(403);

        $this->assertGuest('web');
    }

    /** CP-14: un inicio de sesión correcto reinicia el contador de intentos. */
    public function test_cp14_login_correcto_reinicia_el_contador_de_intentos(): void
    {
        $usuario = $this->crearUsuario(['correo' => 'ana.login@example.com']);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'ana.login@example.com',
            'password' => 'Incorrecta#99',
        ])->assertStatus(401);
        $this->assertSame(1, (int) $usuario->fresh()->intentos_fallidos);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'ana.login@example.com',
            'password' => self::CLAVE,
        ])->assertOk();

        $this->assertSame(0, (int) Usuario::find($usuario->id_usuario)->intentos_fallidos);
    }

    /** CP-15: una cuenta ya bloqueada no entra aunque la contraseña sea correcta. */
    public function test_cp15_cuenta_bloqueada_no_puede_iniciar_sesion(): void
    {
        $this->crearUsuario([
            'correo' => 'bloqueada@example.com',
            'bloqueado' => true,
            'bloqueado_motivo' => 'Incumplió las normas.',
        ]);

        $this->comoNavegador()->postJson('/api/login', [
            'email' => 'bloqueada@example.com',
            'password' => self::CLAVE,
        ])->assertStatus(403);

        $this->assertGuest('web');
    }

    /** CP-16: las rutas protegidas exigen sesión (sin sesión responden 401). */
    public function test_cp16_ruta_protegida_sin_sesion_devuelve_401(): void
    {
        $this->getJson('/api/usuario')->assertStatus(401);
    }
}
