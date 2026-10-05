<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SeleccionarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La autorización real (¿tiene ese rol?, ¿trae la ability correcta?)
        // vive en el middleware RequiereSeleccionRol y en el controlador.
        return true;
    }

    public function rules(): array
    {
        return [
            'rol' => ['required', 'string', 'in:cliente,admin'],
        ];
    }
}
