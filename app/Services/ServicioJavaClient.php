<?php

namespace App\Services;

use App\Exceptions\ServicioJavaNoDisponibleException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente de los servicios de Java. Un solo lugar para: la URL, la clave
 * compartida (header X-Service-Key), el tiempo de espera y los reintentos.
 * Todo sale de config/servicios.php.
 */
class ServicioJavaClient
{
    /**
     * Pide a Java que filtre y exporte reportes. Devuelve la respuesta tal cual
     * (archivo si salió bien; JSON con "mensaje" si Java rechazó la petición).
     *
     * @param  array<string, mixed>  $filtros
     * @param  array<int, array<string, mixed>>  $reportes
     *
     * @throws ServicioJavaNoDisponibleException si no se pudo hablar con Java
     */
    public function exportarReportes(string $formato, array $filtros, array $reportes): Response
    {
        $cuerpo = json_encode(
            ['formato' => $formato, 'filtros' => (object) $filtros, 'reportes' => $reportes],
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );

        return $this->enviar('/api/reportes/exportar', $cuerpo);
    }

    private function enviar(string $ruta, string $cuerpoJson): Response
    {
        $clave = config('servicios.clave');
        if (blank($clave)) {
            Log::error('ServicioJavaClient: falta ROOMMATCH_SERVICE_KEY en el .env de Laravel.');
            throw new ServicioJavaNoDisponibleException(
                'El servicio de exportación no está configurado. Avisa al equipo técnico.'
            );
        }

        $intentos = max(1, (int) config('servicios.intentos', 2));
        $espera = max(0, (int) config('servicios.espera_ms', 400));
        $ultimoError = null;

        for ($i = 1; $i <= $intentos; $i++) {
            try {
                // Sin asJson(): eso fija "Accept: application/json" y Spring
                // respondería 406 al devolver un Excel o un PDF.
                return Http::baseUrl(config('servicios.url'))
                    ->withHeaders(['X-Service-Key' => $clave])
                    ->accept('*/*')
                    ->timeout((int) config('servicios.timeout_exportacion', 30))
                    ->connectTimeout((int) config('servicios.timeout', 5))
                    ->withBody($cuerpoJson, 'application/json')
                    ->post($ruta);
            } catch (ConnectionException $e) {
                // Solo se reintenta si no hubo conexión: si Java sí respondió
                // (aunque sea con error), reintentar no cambiaría nada.
                $ultimoError = $e;
                Log::warning("ServicioJavaClient: intento {$i}/{$intentos} sin conexión", ['error' => $e->getMessage()]);
                if ($i < $intentos && $espera > 0) {
                    usleep($espera * 1000);
                }
            }
        }

        throw new ServicioJavaNoDisponibleException(
            'El servicio de exportación no está disponible en este momento. Inténtalo de nuevo en unos minutos.',
            0,
            $ultimoError
        );
    }
}
