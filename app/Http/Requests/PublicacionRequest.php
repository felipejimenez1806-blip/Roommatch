<?php

namespace App\Http\Requests;

use App\Models\Localidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicacionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el guard de "es tuya o no" se hace en el controller
    }

    public function rules(): array
    {
        return [
            'tipo_espacio' => ['required', 'in:Habitación,Apartamento,Casa,Estudio'],
            'titulo' => ['required', 'string', 'min:5', 'max:200'],
            // Catálogo cerrado: debe ser EXACTAMENTE el nombre de una fila de `localidad`.
            'zona' => ['required', 'string', Rule::in(Localidad::nombres())],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'direccion' => ['required', 'string', 'min:3', 'max:255'],
            'precio' => ['required', 'numeric', 'min:1'],
            'fecha_disponible' => ['required', 'date'],
            'genero' => ['nullable', 'in:masculino,femenino,otro'],
            'numero_habitantes' => ['nullable', 'integer', 'min:0'],
            'telefono_contacto' => ['required', 'string', 'min:10'],
            'descripcion' => ['required', 'string', 'min:15'],
            'imagenes' => ['required', 'array', 'min:1'],
            'imagenes.*' => ['required', 'string'],
            'caracteristicas' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'tipo_espacio.required' => 'Selecciona el tipo de espacio.',
            'titulo.min' => 'Escribe un título de al menos 5 caracteres.',
            'zona.required' => 'Selecciona la zona o localidad.',
            'zona.in' => 'Selecciona una localidad válida de la lista.',
            'direccion.min' => 'Ingresa una dirección válida.',
            'precio.required' => 'Ingresa un precio válido.',
            'precio.min' => 'Ingresa un precio válido.',
            'fecha_disponible.required' => 'Selecciona una fecha de disponibilidad.',
            'telefono_contacto.min' => 'Ingresa un número de WhatsApp válido, con indicativo de país.',
            'descripcion.min' => 'Describe el espacio con al menos 15 caracteres.',
            'imagenes.required' => 'Sube al menos una foto para publicar.',
            'imagenes.min' => 'Sube al menos una foto para publicar.',
        ];
    }
}
