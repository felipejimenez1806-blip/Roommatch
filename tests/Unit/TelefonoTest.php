<?php

namespace Tests\Unit;

use App\Support\Telefono;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias del validador de teléfonos (sin base de datos).
 * Cubre el formato único "+57" + celular colombiano de 10 dígitos.
 */
class TelefonoTest extends TestCase
{
    public static function celularesValidos(): array
    {
        return [
            'con espacios' => ['300 111 2233'],
            'con indicativo y guiones' => ['+57 300-111-2233'],
            'con indicativo sin signo' => ['573001112233'],
            'con prefijo 0057' => ['0057 300 111 2233'],
            'con paréntesis' => ['(300) 1112233'],
            'como número entero' => [3001112233],
        ];
    }

    #[DataProvider('celularesValidos')]
    public function test_normaliza_celulares_validos_al_formato_canonico(string|int $entrada): void
    {
        $this->assertSame('+573001112233', Telefono::normalizar($entrada));
    }

    public static function valoresInvalidos(): array
    {
        return [
            'muy corto' => ['1234567'],
            'no empieza por 3' => ['2001112233'],
            'fijo de Bogotá' => ['6011234567'],
            'vacío' => [''],
            'nulo' => [null],
            'letras' => ['abc'],
            'incompleto' => ['300 111 22'],
            'arreglo' => [['300']],
        ];
    }

    #[DataProvider('valoresInvalidos')]
    public function test_rechaza_valores_que_no_son_celular_colombiano(mixed $entrada): void
    {
        $this->assertNull(Telefono::normalizar($entrada));
    }

    public function test_normalizar_o_conservar_deja_el_valor_si_no_es_valido(): void
    {
        $this->assertSame('12345', Telefono::normalizarOConservar('12345'));
        $this->assertSame('+573001112233', Telefono::normalizarOConservar('300 111 2233'));
    }

    public function test_la_expresion_regular_solo_acepta_el_formato_canonico(): void
    {
        $this->assertSame(1, preg_match(Telefono::REGEX, '+573001112233'));
        $this->assertSame(0, preg_match(Telefono::REGEX, '3001112233'));
        $this->assertSame(0, preg_match(Telefono::REGEX, '+572001112233'));
    }

    public function test_formatear_agrega_espacios_para_mostrar(): void
    {
        $this->assertSame('+57 300 111 2233', Telefono::formatear('+573001112233'));
    }
}
