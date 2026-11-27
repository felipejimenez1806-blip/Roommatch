<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Comprueba el "pase de entrada" que Java (Spring) le da al navegador cuando un CLIENTE inicia sesión
 * allá, para abrirle aquí la sesión de Laravel sin pedirle el login otra vez.
 *
 * Es el camino inverso de PaseAgendaService (ese lo genera Laravel y lo lee Java). Este lo genera
 * Modelpase.java y lo lee Laravel. Deben coincidir letra por letra:
 *
 *   pase  = base64url(carga) "." base64url(HMAC-SHA256(base64url(carga), ROOMMATCH_SERVICE_KEY))
 *   carga = "dashboard-v1" "|" idUsuario "|" caducaEnSegundosUnix "|" numeroUnico
 *
 * Se rechaza el pase si:
 *  - la firma no coincide (alguien lo inventó o lo modificó),
 *  - la versión no es "dashboard-v1" (p. ej. un pase de la agenda no sirve aquí),
 *  - ya caducó, o caduca demasiado lejos en el futuro (Java mal configurado),
 *  - ya se usó (el número único se anota en caché: un pase sirve UNA sola vez).
 *
 * Importante: el pase solo identifica a la persona (su id). Quién es, si está bloqueada y qué roles tiene
 * se vuelve a leer de la base de datos en AuthJavaController; el pase no se toma como prueba de eso.
 */
class PaseJavaService
{
    // Debe ser igual a VERSION en Modelpase.java
    public const VERSION = 'dashboard-v1';

    // Segundos de margen por si el reloj de Java y el de Laravel no están perfectamente sincronizados
    private const TOLERANCIA = 10;

    // Un pase que caduca más allá de esto (en segundos desde ahora) no es de fiar: Java usa 60
    private const MAX_VIGENCIA = 300;

    /**
     * @return int|null  el id_usuario del pase, o null si el pase no es válido (el motivo queda en el log,
     *                   no se le muestra a quien lo intentó)
     */
    public function verificar(?string $pase): ?int
    {
        $clave = (string) config('servicios.clave');

        if ($clave === '') {
            Log::error('PaseJavaService: falta ROOMMATCH_SERVICE_KEY en el .env de Laravel.');

            return null;
        }

        if ($pase === null || $pase === '' || strlen($pase) > 1000) {
            return $this->rechazar('pase vacío o demasiado largo');
        }

        $partes = explode('.', $pase);
        if (count($partes) !== 2) {
            return $this->rechazar('formato incorrecto (se esperaba cuerpo.firma)');
        }

        [$cuerpo, $firma] = $partes;

        // 1. Firma. hash_equals compara en tiempo constante (no revela cuántos caracteres acertó).
        $esperada = self::base64url(hash_hmac('sha256', $cuerpo, $clave, true));
        if (! hash_equals($esperada, $firma)) {
            return $this->rechazar('firma inválida (¿la clave de Spring y la de Laravel son distintas?)');
        }

        // 2. Carga: version | idUsuario | caducaEn | numeroUnico
        $carga = self::base64urlDecode($cuerpo);
        $datos = $carga === null ? [] : explode('|', $carga);

        if (count($datos) !== 4) {
            return $this->rechazar('carga con formato incorrecto');
        }

        [$version, $id, $caducaEn, $numeroUnico] = $datos;

        if ($version !== self::VERSION) {
            return $this->rechazar("versión inesperada ({$version})");
        }

        if (! ctype_digit($id) || (int) $id < 1 || ! ctype_digit($caducaEn) || ! preg_match('/^[a-f0-9]{32}$/', $numeroUnico)) {
            return $this->rechazar('datos de la carga inválidos');
        }

        // 3. Vigencia
        $ahora = time();
        $caducaEn = (int) $caducaEn;

        if ($ahora > $caducaEn + self::TOLERANCIA) {
            return $this->rechazar('pase caducado');
        }

        if ($caducaEn - $ahora > self::MAX_VIGENCIA) {
            return $this->rechazar('pase con vigencia demasiado larga');
        }

        // 4. Un solo uso. Cache::add escribe solo si la llave NO existe y es atómico: si dos peticiones
        //    llegan a la vez con el mismo pase, solo una gana. Se hace al final para que un pase
        //    inválido no llene la caché. La llave vive hasta que el pase habría caducado.
        $vive = max(1, $caducaEn - $ahora) + self::TOLERANCIA + 1;
        if (! Cache::add('pase-java:'.$numeroUnico, true, $vive)) {
            return $this->rechazar('pase ya usado');
        }

        return (int) $id;
    }

    private function rechazar(string $motivo): ?int
    {
        Log::warning('PaseJavaService: pase rechazado — '.$motivo);

        return null;
    }

    private static function base64url(string $datos): string
    {
        return rtrim(strtr(base64_encode($datos), '+/', '-_'), '=');
    }

    private static function base64urlDecode(string $texto): ?string
    {
        $base64 = strtr($texto, '-_', '+/');
        $base64 .= str_repeat('=', (4 - strlen($base64) % 4) % 4);

        $decodificado = base64_decode($base64, true);

        return $decodificado === false ? null : $decodificado;
    }
}
