<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    /**
     * Ruta pública: cualquier visitante (con o sin cuenta) puede
     * escribir desde el formulario de Contáctanos.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'  => ['required', 'string', 'min:2', 'max:100'],
            'correo'  => ['required', 'email', 'max:150'],
            'asunto'  => ['required', 'in:soporte,cuenta,reporte,sugerencia,otro'],
            'mensaje' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required'  => 'El nombre es obligatorio.',
            'nombre.min'       => 'El nombre es muy corto.',
            'correo.required'  => 'El correo es obligatorio.',
            'correo.email'     => 'Escribe un correo válido.',
            'asunto.required'  => 'Selecciona un asunto.',
            'asunto.in'        => 'Selecciona un asunto válido.',
            'mensaje.required' => 'El mensaje es obligatorio.',
            'mensaje.min'      => 'Cuéntanos un poco más (mínimo 10 caracteres).',
            'mensaje.max'      => 'El mensaje es demasiado largo (máximo 2000 caracteres).',
        ];
    }
}
