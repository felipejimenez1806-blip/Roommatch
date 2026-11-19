<?php

namespace App\Services;

use App\Exceptions\ServicioJavaNoDisponibleException;
use App\Models\Usuario;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;

/**
 * Genera el "pase de entrada" a la agenda de Java (Spring).
 *
 * El pase es una dirección que lleva un texto firmado. Spring comprueba la firma, que no haya
 * caducado y que no se haya usado antes; si todo está bien abre una sesión sin pedir login.
 *
 * Formato (lo lee PaseService.java, deben coincidir):
 *
 *   pase    = base64url(carga) "." base64url(HMAC-SHA256(base64url(carga), ROOMMATCH_SERVICE_KEY))
 *   carga   = "agenda-v1" "|" idUsuario "|" caducaEnSegundosUnix "|" numeroUnico "|" base64url(nombre)
 *
 * Se usa texto separado por "|" (y no JSON) para que PHP y Java no dependan de cómo cada uno
 * escribe los acentos o las comillas: el nombre va en base64url y listo.
 */
class PaseAgendaService
{
    public const VERSION = 'agenda-v1';

    /**
     * @return array{url: string, expiraEn: int}
     *
     * @throws AuthorizationException si el rol activo no es cliente
     * @throws ServicioJavaNoDisponibleException si falta la clave compartida
     */
    public function generar(Usuario $usuario, ?string $rolActivo): array
    {
        if ($rolActivo !== 'cliente') {
            throw new AuthorizationException('La agenda solo está disponible cuando entras como cliente.');
        }

        $clave = config('servicios.clave');
        if (blank($clave)) {
            Log::error('PaseAgendaService: falta ROOMMATCH_SERVICE_KEY en el .env de Laravel.');
            throw new ServicioJavaNoDisponibleException(
                'El servicio de agenda no está configurado. Avisa al equipo técnico.'
            );
        }

        $segundos = max(1, (int) config('servicios.pase_segundos', 60));
        $caducaEn = time() + $segundos;
        $numeroUnico = bin2hex(random_bytes(16));

        $carga = implode('|', [
            self::VERSION,
            (int) $usuario->getKey(),
            $caducaEn,
            $numeroUnico,
            self::base64url((string) $usuario->nombre_completo),
        ]);

        $cuerpo = self::base64url($carga);
        $firma = self::base64url(hash_hmac('sha256', $cuerpo, (string) $clave, true));

        $base = rtrim((string) config('servicios.url_publica'), '/');

        return [
            'url' => $base.'/agenda/entrar?pase='.$cuerpo.'.'.$firma,
            'expiraEn' => $segundos,
        ];
    }

    private static function base64url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }
}
