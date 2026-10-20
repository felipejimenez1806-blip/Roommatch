<?php

namespace App\Support;

/**
 * Formato ÚNICO de teléfono en RoomMatch: "+57" + celular de 10 dígitos
 * (ej. "+573001112233"). Es lo que se guarda en BD siempre, venga como venga
 * del formulario ("300 111 2233", "300-111-2233", "(300) 1112233", "+57 300 111 2233",
 * "573001112233", "0057 300 111 2233").
 *
 * Por qué así:
 *  - Una sola forma en BD => nada de comparar "3001112233" contra "573001112233".
 *  - wa.me exige el indicativo de país; los enlaces de WhatsApp que se arman con
 *    el teléfono guardado ya salen completos.
 *  - Cabe de sobra en varchar(20) (13 caracteres).
 *
 * Solo celulares colombianos (10 dígitos, empiezan por 3): el contacto de la
 * plataforma es por WhatsApp. La misma lógica vive en core/validacion.js
 * (normalizarTelefono): si cambias una, cambia la otra.
 */
final class Telefono
{
    /** Regex del formato canónico, para usar en reglas `regex:`. */
    public const REGEX = '/^\+573\d{9}$/';

    public const MENSAJE = 'Ingresa un celular colombiano de 10 dígitos que empiece por 3 (ej. 300 123 4567). El indicativo +57 es opcional.';

    /** "+573001112233", o null si el valor no es un celular colombiano reconocible. */
    public static function normalizar(mixed $valor): ?string
    {
        if (! is_string($valor) && ! is_int($valor)) {
            return null;
        }

        $d = preg_replace('/\D/', '', (string) $valor);

        if (str_starts_with($d, '0057')) {
            $d = substr($d, 4);
        } elseif (strlen($d) === 12 && str_starts_with($d, '57')) {
            $d = substr($d, 2);
        }

        return preg_match('/^3\d{9}$/', $d) ? '+57' . $d : null;
    }

    /** Valor para el campo de un formulario: si es válido, sin +57; si no, tal cual. */
    public static function normalizarOConservar(mixed $valor): mixed
    {
        return self::normalizar($valor) ?? $valor;
    }

    /** "+57 300 111 2233" (solo para mostrar). */
    public static function formatear(?string $valor): string
    {
        $n = self::normalizar($valor);

        return $n
            ? sprintf('+57 %s %s %s', substr($n, 3, 3), substr($n, 6, 3), substr($n, 9))
            : (string) $valor;
    }
}
