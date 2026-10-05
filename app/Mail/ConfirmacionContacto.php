<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConfirmacionContacto extends Mailable
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
        // Mismas etiquetas que se muestran en el <select> de contactanos.blade.php,
        // para que el correo diga "Problema con mi cuenta" y no "cuenta".
        $etiquetasAsunto = [
            'soporte'    => 'Problema con una publicación, reserva o cita',
            'cuenta'     => 'Problema con mi cuenta',
            'reporte'    => 'Quiero reportar algo',
            'sugerencia' => 'Sugerencia o idea',
            'otro'       => 'Otro',
        ];

        return $this
            ->subject('Recibimos tu mensaje — RoomMatch')
            ->view('emails.confirmacion-contacto')
            ->with([
                'datos'       => $this->datos,
                'asuntoLabel' => $etiquetasAsunto[$this->datos['asunto']] ?? $this->datos['asunto'],
            ]);
    }
}
