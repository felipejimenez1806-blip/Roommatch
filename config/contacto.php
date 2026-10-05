<?php

/*
|--------------------------------------------------------------------------
| Config de Contacto
|--------------------------------------------------------------------------
| Correo al que llegan los mensajes del formulario de Contáctanos.
| Se define en .env como CONTACTO_EMAIL_DESTINO para no dejarlo
| escrito directamente en el código.
*/

return [
    'destino' => env('CONTACTO_EMAIL_DESTINO', 'roommatch.soporte2026@gmail.com'),
];
