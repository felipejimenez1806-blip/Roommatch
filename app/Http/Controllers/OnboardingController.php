<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OnboardingController extends Controller
{
    /**
     * Completa los datos pendientes (telefono, genero) de un usuario que
     * entró por Google/Facebook. EnsurePerfilCompleto ya garantiza que
     * solo llegue aquí un usuario autenticado con el perfil incompleto.
     *
     * tipo_usuario ya NO se pide aquí: todo usuario nace 'cliente' por el
     * default de la columna en BD desde el momento en que se crea (tanto
     * en registro local como en el primer login social).
     */
    public function completar(Request $request)
    {
        $usuario = $request->user();

        // Si esto dispara, el problema es de autenticación (token no
        // llegó o la ruta no está dentro de auth:sanctum), no de datos.
        if (!$usuario) {
            return response()->json([
                'mensaje' => 'Tu sesión expiró o no es válida. Inicia sesión de nuevo.',
            ], 401);
        }

        $validado = $request->validate([
            'telefono' => ['required', 'regex:/^(\+57)?[0-9]{7,10}$/'],
            'genero' => ['nullable', Rule::in(['masculino', 'femenino', 'otro', 'prefiero_no_decir'])],
        ], [
            'telefono.required' => 'Ingresa tu número de teléfono.',
            'telefono.regex' => 'Ingresa un teléfono válido (10 dígitos).',
            'genero.in' => 'La opción de género no es válida.',
        ]);

        $usuario->telefono = $validado['telefono'];
        $usuario->genero = $validado['genero'] ?? $usuario->genero;
        $usuario->save();

        return response()->json([
            'mensaje' => 'Perfil completado correctamente.',
            'usuario' => $usuario->paraFrontend(),
        ]);
    }
}
