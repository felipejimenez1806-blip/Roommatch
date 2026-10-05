<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactoRequest;
use App\Mail\ConfirmacionContacto;
use App\Mail\MensajeContacto as MensajeContactoMail;
use App\Models\MensajeContacto;
use App\Models\Notificacion;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ContactoController extends Controller
{
    public function store(ContactoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        // El mensaje queda guardado primero. Así, si algún correo falla
        // (ej. credenciales SMTP mal configuradas), el mensaje no se pierde.
        $registro = MensajeContacto::create($datos);

        Notificacion::notificarAdminsNuevoMensajeContacto($registro);

        // Correo interno para el equipo de soporte (destino fijo en config('contacto.destino')).
        try {
            Mail::to(config('contacto.destino'))
                ->send(new MensajeContactoMail($datos));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de contacto al soporte', [
                'id_mensaje_contacto' => $registro->id_mensaje_contacto,
                'error' => $e->getMessage(),
            ]);
        }

        // Correo de confirmación para la persona que escribió el mensaje.
        // Va en su propio try/catch: si este falla, no debe afectar el correo
        // al soporte (ya enviado arriba) ni la respuesta al frontend.
        try {
            Mail::to($datos['correo'], $datos['nombre'])
                ->send(new ConfirmacionContacto($datos));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de confirmación al cliente', [
                'id_mensaje_contacto' => $registro->id_mensaje_contacto,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'mensaje' => 'Tu mensaje fue enviado correctamente.',
        ]);
    }
}
