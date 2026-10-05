<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CodigoRecuperacionMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $codigo;

    public function __construct(string $codigo)
    {
        $this->codigo = $codigo;
    }

    public function build()
    {
        return $this->subject('Tu código de recuperación - Roommatch')
            ->view('emails.codigo-recuperacion')
            ->with(['codigo' => $this->codigo]);
    }
}
