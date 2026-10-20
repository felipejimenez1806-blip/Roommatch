<?php

/*
|--------------------------------------------------------------------------
| Límites de validación de RoomMatch
|--------------------------------------------------------------------------
| ÚNICA fuente de verdad. Los lee:
|   - PublicacionRequest y PersonaRoomieRequest (validación del servidor)
|   - resources/js/core/validacion.js, vía window.APP.limites
|     (partials/app-config.blade.php), para que el mensaje del navegador
|     y el del servidor digan siempre los mismos números.
|
| Para cambiar un tope se edita AQUÍ y nada más. Los montos tienen que
| caber en decimal(10,2) de la BD (máx. 99.999.999).
*/

return [
    'limites' => [
        // Dinero (COP al mes, enteros)
        'precio_min'      => 50000,
        'precio_max'      => 30000000,
        'presupuesto_min' => 50000,
        'presupuesto_max' => 30000000,

        // Personas
        'habitantes_min' => 0,
        'habitantes_max' => 20,
        'edad_min'       => 18,
        'edad_max'       => 99,

        // Textos (la BD permite más; estos son topes de producto)
        'titulo_min'      => 5,
        'titulo_max'      => 120,
        'direccion_min'   => 3,
        'direccion_max'   => 255,
        'descripcion_min' => 15,
        'descripcion_max' => 2000,
        'ciudad_max'      => 100,
        'texto_corto_max' => 50,   // horarios y distancia (varchar 50 en BD)
        'detalles_max'    => 500,

        // El teléfono no tiene topes aquí: su formato (+57 + celular de 10 dígitos)
        // vive en App\Support\Telefono y en core/validacion.js.

        // Fotos
        'fotos_max'      => 8,
        'foto_max_bytes' => 3 * 1024 * 1024, // por foto, ya comprimida
    ],
];
