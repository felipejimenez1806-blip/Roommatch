<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CitaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el guard de "no agendar contigo mismo/a" se hace en el controller
    }

    public function rules(): array
    {
        return [
            'fecha_cita' => ['required', 'date', 'after_or_equal:today'],
            'hora_cita' => ['required', 'date_format:H:i', 'regex:/^([01]\d|2[0-3]):(00|30)$/'],
            'tipo_encuentro' => ['required', 'in:sitio,publico,otro'],
            'lugar_encuentro' => ['required', 'string', 'min:3', 'max:255'],
            'mensaje' => ['nullable', 'string', 'max:1000'],
            'contacto_nombre' => ['required', 'string', 'min:3', 'max:150'],
            'contacto_correo' => ['required', 'email', 'max:150'],
            'contacto_telefono' => ['required', 'string', 'min:7', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'fecha_cita.required' => 'Selecciona una fecha para la cita.',
            'fecha_cita.after_or_equal' => 'La fecha de la cita no puede ser en el pasado.',
            'hora_cita.required' => 'Selecciona una hora.',
            'hora_cita.date_format' => 'Selecciona una hora válida.',
            'hora_cita.regex' => 'Las citas solo se pueden agendar en punto o y media (ej. 2:00 o 2:30).',
            'tipo_encuentro.required' => 'Selecciona dónde se encontrarán.',
            'lugar_encuentro.required' => 'Indica un lugar o punto de referencia.',
            'lugar_encuentro.min' => 'Indica un lugar o punto de referencia.',
            'contacto_nombre.required' => 'Ingresa tu nombre completo.',
            'contacto_nombre.min' => 'Ingresa tu nombre completo.',
            'contacto_correo.required' => 'Ingresa un correo válido.',
            'contacto_correo.email' => 'Ingresa un correo válido.',
            'contacto_telefono.required' => 'Ingresa un número de teléfono válido.',
            'contacto_telefono.min' => 'Ingresa un número de teléfono válido.',
        ];
    }
}
