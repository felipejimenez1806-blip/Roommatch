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
|   ROOMMATCH_SERVICE_KEY=<la misma clave larga y aleatoria que usa Spring>
*/
return [
    // URL base del servicio de Java. REPORTES_URL se sigue aceptando para no
    // obligarte a cambiar el .env que ya tienes.
    'url' => rtrim((string) env('SERVICIO_JAVA_URL', env('REPORTES_URL', 'http://localhost:8081')), '/'),

    // Clave compartida entre servicios (header X-Service-Key). Debe ser la misma
    // que la variable de entorno ROOMMATCH_SERVICE_KEY con la que arranca Spring.
    'clave' => env('ROOMMATCH_SERVICE_KEY'),

    // Segundos máximos de espera por intento.
    'timeout' => (int) env('SERVICIO_JAVA_TIMEOUT', env('REPORTES_TIMEOUT', 5)),

    // Espera máxima (segundos) a que Java genere un archivo; el timeout de arriba
    // se usa para conectarse.
    'timeout_exportacion' => (int) env('SERVICIO_JAVA_TIMEOUT_EXPORTACION', 30),

    // Intentos totales (1 + reintentos) y espera entre ellos, en milisegundos.
    'intentos' => (int) env('SERVICIO_JAVA_INTENTOS', env('REPORTES_INTENTOS', 3)),
    'espera_ms' => (int) env('SERVICIO_JAVA_ESPERA_MS', env('REPORTES_ESPERA_MS', 400)),
];
