<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Valida una imagen que llega del wizard: o bien una foto NUEVA como data URL
 * base64, o bien una URL de una foto que ya estaba guardada (modo edición).
 *
 * Por qué existe: guardarImagenBase64() usaba /^data:image\/(\w+);base64,/ y
 * ponía la extensión que viniera en el texto. Con "data:image/php;base64,..."
 * se podía guardar un .php en storage/public. Aquí se deja pasar SOLO
 * jpeg/png/webp, y además se comprueba que los bytes sean de verdad una imagen.
 */
class ImagenSubida implements ValidationRule
{
    public function __construct(private string $etiqueta = 'La imagen') {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // "imagenes.2" -> "La foto 3": el usuario sabe cuál foto es.
        $nombre = preg_match('/\.(\d+)$/', $attribute, $m)
            ? 'La foto ' . ((int) $m[1] + 1)
            : $this->etiqueta;

        if (! is_string($value) || $value === '') {
            $fail("{$nombre} no es válida.");
            return;
        }

        // Foto que ya estaba guardada (edición): URL absoluta o ruta del sitio.
        if (! str_starts_with($value, 'data:')) {
            if (strlen($value) > 500 || ! preg_match('#^(https?://|/)\S+$#i', $value)) {
                $fail("{$nombre} no es válida.");
            }
            return;
        }

        if (! preg_match('/^data:image\/(jpeg|png|webp);base64,/', $value)) {
            $fail("{$nombre} debe ser JPG, PNG o WEBP.");
            return;
        }

        $binario = base64_decode(substr($value, strpos($value, ',') + 1), true);
        if ($binario === false || $binario === '') {
            $fail("{$nombre} está dañada. Súbela de nuevo.");
            return;
        }

        $maxBytes = (int) config('roommatch.limites.foto_max_bytes');
        if (strlen($binario) > $maxBytes) {
            $mb = round($maxBytes / 1024 / 1024, 1);
            $fail("{$nombre} pesa demasiado (máximo {$mb} MB).");
            return;
        }

        $info = @getimagesizefromstring($binario);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $fail("{$nombre} no es una imagen válida.");
        }
    }
}
