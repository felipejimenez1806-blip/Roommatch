<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CambiarRolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'rol' => ['required', 'string', 'in:cliente,admin'],
            // Solo se exige cuando 'rol' viene como 'admin'; para volver
            // a 'cliente' no hace falta reingresar la contraseña.
            'password' => ['required_if:rol,admin', 'string'],
        ];
    }
}
