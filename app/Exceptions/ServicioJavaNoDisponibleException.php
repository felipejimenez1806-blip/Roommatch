<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * El servicio de Java (Spring Boot) no respondió: está caído, tardó demasiado o
 * la clave de servicio no está configurada. El mensaje es apto para mostrarlo
 * al usuario.
 */
class ServicioJavaNoDisponibleException extends RuntimeException
{
}
