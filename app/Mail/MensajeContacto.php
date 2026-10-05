<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MensajeContacto extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{nombre: string, correo: string, asunto: string, mensaje: string} $datos
     */
    public function __construct(public array $datos)
    {
    }

    public function build(): self
    {
        return $this
            ->subject('Nuevo mensaje de contacto — RoomMatch')
            // Para que si el equipo de soporte le da "Responder" en Gmail,
            // la respuesta le llegue directo a la persona que escribió,
            // no a la propia cuenta de soporte.
            ->replyTo($this->datos['correo'], $this->datos['nombre'])
            ->view('emails.contacto')
            ->with('datos', $this->datos);
    }
}
