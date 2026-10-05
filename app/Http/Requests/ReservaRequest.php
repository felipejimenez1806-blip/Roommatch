<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReservaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el guard de "no puedes reservar tu propia publicación" se hace en el controller
    }

    public function rules(): array
    {
        return [
            'fecha_visita' => ['required', 'date', 'after_or_equal:today'],
            'hora_visita' => ['required', 'date_format:H:i', 'regex:/^([01]\d|2[0-3]):(00|30)$/'],
            'duracion_estimada' => ['required', 'in:1-3,3-6,6-12,12+'],
            'contacto_nombre' => ['required', 'string', 'min:3', 'max:150'],
            'contacto_correo' => ['required', 'email', 'max:150'],
            'contacto_telefono' => ['required', 'string', 'min:7', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_visita.required' => 'Selecciona una fecha para la visita.',
            'fecha_visita.after_or_equal' => 'La fecha de visita no puede ser en el pasado.',
            'hora_visita.required' => 'Selecciona una hora para la visita.',
            'hora_visita.date_format' => 'Selecciona una hora válida.',
            'hora_visita.regex' => 'Las visitas solo se pueden agendar en punto o y media (ej. 2:00 o 2:30).',
            'duracion_estimada.required' => 'Selecciona una duración estimada.',
            'contacto_nombre.required' => 'Ingresa tu nombre completo.',
            'contacto_nombre.min' => 'Ingresa tu nombre completo.',
            'contacto_correo.required' => 'Ingresa un correo válido.',
            'contacto_correo.email' => 'Ingresa un correo válido.',
            'contacto_telefono.required' => 'Ingresa un número de teléfono válido.',
            'contacto_telefono.min' => 'Ingresa un número de teléfono válido.',
        ];
    }
}
