<?php

/*
|--------------------------------------------------------------------------
| Comunicación con los servicios de Java (Spring Boot)
|--------------------------------------------------------------------------
| Se lee desde aquí (y no con env() en el código) para que siga funcionando
| con `php artisan config:cache`.
|
| .env de Laravel:
|   SERVICIO_JAVA_URL=http://localhost:8081   (si no existe, se usa REPORTES_URL)
|   SERVICIO_JAVA_URL_PUBLICA=http://localhost:8081   (opcional: la dirección de Java que ve el
|       NAVEGADOR, para el marco de "Mi agenda". Si no existe, se usa SERVICIO_JAVA_URL. En Azure,
|       pon aquí la dirección real y con https, p. ej. https://agenda.tudominio.com)
|   ROOMMATCH_SERVICE_KEY=<la misma clave larga y aleatoria que usa Spring>
*/
return [
    // URL base del servicio de Java. REPORTES_URL se sigue aceptando para no
    // obligarte a cambiar el .env que ya tienes.
    'url' => rtrim((string) env('SERVICIO_JAVA_URL', env('REPORTES_URL', 'http://localhost:8081')), '/'),

    // Dirección de Java tal como la abre el NAVEGADOR (marco de "Mi agenda"). Puede ser distinta de
    // 'url' (la que usa Laravel desde el servidor), p. ej. cuando Java está en una red interna.
    'url_publica' => rtrim((string) env(
        'SERVICIO_JAVA_URL_PUBLICA',
        env('SERVICIO_JAVA_URL', env('REPORTES_URL', 'http://localhost:8081'))
    ), '/'),

    // Cuántos segundos vale el pase de entrada a la agenda (se usa una sola vez).
    'pase_segundos' => 60,

    // Clave compartida entre servicios (header X-Service-Key). Debe ser la misma
    // que la variable de entorno ROOMMATCH_SERVICE_KEY con la que arranca Spring.
    'clave' => env('ROOMMATCH_SERVICE_KEY'),

    // Segundos máximos de espera por intento.
    'timeout' => (int) env('SERVICIO_JAVA_TIMEOUT', env('REPORTES_TIMEOUT', 5)),

    // Espera máxima (segundos) a que Java genere un archivo; el timeout de arriba
    // se usa para conectarse.
    'timeout_exportacion' => (int) env('SERVICIO_JAVA_TIMEOUT_EXPORTACION', 30),

    // Avisos a la agenda (reserva o cita aceptada/cancelada): esperas cortas para que
    // aceptar o cancelar nunca se quede colgado si el servicio de Java está caído.
    'timeout_agenda' => (int) env('SERVICIO_JAVA_TIMEOUT_AGENDA', 3),
    'conexion_agenda' => (int) env('SERVICIO_JAVA_CONEXION_AGENDA', 1),

    // Cuántos días hacia atrás se reenvían reservas y citas al sincronizar todo (agenda:sincronizar --todo).
    'agenda_dias' => (int) env('AGENDA_SINCRONIZAR_DIAS', 60),

    // Intentos totales (1 + reintentos) y espera entre ellos, en milisegundos.
    'intentos' => (int) env('SERVICIO_JAVA_INTENTOS', env('REPORTES_INTENTOS', 3)),
    'espera_ms' => (int) env('SERVICIO_JAVA_ESPERA_MS', env('REPORTES_ESPERA_MS', 400)),
];
