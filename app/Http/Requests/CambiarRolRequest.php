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
            'rol' => ['required', 'string', 'in:cliente,moderador,admin'],
            // Se exige al pasar a un rol con privilegios (admin o moderador);
            // para volver a 'cliente' no hace falta reingresar la contraseña.
            'password' => ['required_if:rol,admin,moderador', 'string'],
        ];
    }
}
