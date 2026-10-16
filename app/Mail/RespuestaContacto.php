<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Respuesta del equipo de soporte (moderador o administrador) a un mensaje
 * del formulario de Contáctanos. Mismo estilo que ConfirmacionContacto.
 */
class RespuestaContacto extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param array{nombre: string, asunto: string, mensaje: string, respuesta: string, atendido_por: string} $datos
     */
    public function __construct(public array $datos)
    {
    }

    public function build(): self
    {
        $etiquetasAsunto = [
            'soporte'    => 'Problema con una publicación, reserva o cita',
            'cuenta'     => 'Problema con mi cuenta',
            'reporte'    => 'Quiero reportar algo',
            'sugerencia' => 'Sugerencia o idea',
            'otro'       => 'Otro',
        ];

        return $this
            ->subject('Respuesta a tu mensaje — RoomMatch')
            ->view('emails.respuesta-contacto')
            ->with([
                'datos'       => $this->datos,
                'asuntoLabel' => $etiquetasAsunto[$this->datos['asunto']] ?? $this->datos['asunto'],
            ]);
    }
}
