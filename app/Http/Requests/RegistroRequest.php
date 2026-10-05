<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegistroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el registro es público
    }

    /**
     * Mismas reglas que validaba signup.js en el frontend,
     * ahora aplicadas también (y sobre todo) en el servidor.
     *
     * tipo_usuario ya NO se valida aquí: todo registro público nace
     * 'cliente' por el default de la columna en BD; 'admin' se crea
     * aparte (manualmente en BD o desde el panel de administración).
     *
     * acepta_terminos: defensa en el servidor por si alguien llama a
     * /api/registro directo, saltándose el checkbox del formulario.
     * 'accepted' exige true/1/"yes"/"on"; cualquier otra cosa (incluido
     * que el campo no llegue) falla la validación con un 422.
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:usuario,correo'],
            'telefono' => ['required', 'string', 'regex:/^(\+57)?[0-9]{7,10}$/'],
            'direccion' => ['required', 'string', 'min:5', 'max:255'],
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)->mixedCase()->numbers()->symbols(),
                function ($attribute, $value, $fail) {
                    $this->validarPasswordSinNombreNiCorreo($value, $fail);
                },
            ],
            'acepta_terminos' => ['accepted'],
        ];
    }

    /**
     * Mismo criterio que passwordContieneNombreOCorreo() en signup.js:
     * rechaza la contraseña si contiene el usuario del correo (antes
     * del @) o alguna palabra de 3+ letras del nombre completo.
     */
    private function validarPasswordSinNombreNiCorreo(string $value, \Closure $fail): void
    {
        $valorMin = strtolower($value);
        $nombre = strtolower((string) $this->input('nombre'));
        $email = strtolower((string) $this->input('email'));

        $usuarioCorreo = explode('@', $email)[0] ?? '';
        if (strlen($usuarioCorreo) >= 3 && str_contains($valorMin, $usuarioCorreo)) {
            $fail('La contraseña no debe contener tu correo.');
            return;
        }

        foreach (preg_split('/\s+/', trim($nombre)) as $parte) {
            if (strlen($parte) >= 3 && str_contains($valorMin, $parte)) {
                $fail('La contraseña no debe contener tu nombre.');
                return;
            }
        }
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'Ingresa tu nombre completo.',
            'nombre.min' => 'Ingresa tu nombre completo.',
            'email.required' => 'Ingresa tu correo electrónico.',
            'email.email' => 'Ingresa un correo válido.',
            'email.unique' => 'Ya existe una cuenta con este correo.',
            'telefono.required' => 'Ingresa tu número de teléfono.',
            'telefono.regex' => 'Ingresa un teléfono válido (10 dígitos).',
            'direccion.required' => 'Ingresa tu dirección.',
            'direccion.min' => 'Ingresa tu dirección.',
            'password.required' => 'Ingresa una contraseña.',
            'password.min' => 'Debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
            'password.letters' => 'Debe incluir al menos una letra.',
            'password.mixed' => 'Debe incluir al menos una mayúscula y una minúscula.',
            'password.numbers' => 'Debe incluir al menos un número.',
            'password.symbols' => 'Debe incluir al menos un carácter especial.',
            'acepta_terminos.accepted' => 'Debes aceptar los términos y condiciones para continuar.',
        ];
    }
}
