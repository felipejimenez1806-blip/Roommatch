<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UsuarioController extends Controller
{
    /**
     * PUT /api/usuario
     * Datos personales (panel 1 de perfil.js).
     * El rol ya NO se edita desde aquí: todo usuario nace con 'cliente'
     * y 'admin' solo se asigna en BD o desde el panel de administración.
     */
    public function update(Request $request)
    {
        $usuario = $request->user();

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuario', 'correo')->ignore($usuario->id_usuario, 'id_usuario')],
            'telefono' => ['required', 'string', 'min:7'],
            'direccion' => ['nullable', 'string', 'min:3', 'max:255'],
            'genero' => ['nullable', 'in:masculino,femenino,otro,prefiero_no_decir'],
        ]);

        $usuario->update([
            'nombre_completo' => $datos['nombre'],
            'correo' => $datos['email'],
            'telefono' => $datos['telefono'],
            'direccion' => $datos['direccion'] ?? null,
            'genero' => $datos['genero'] ?? null,
        ]);

        return response()->json(['usuario' => $this->formatear($usuario)]);
    }

    /**
     * POST /api/usuario/foto
     * Sube (base64) o quita (foto: null) la foto de perfil.
     */
    public function actualizarFoto(Request $request)
    {
        $request->validate(['foto' => ['nullable', 'string']]);
        $usuario = $request->user();

        if (! $request->foto) {
            $usuario->update(['foto_perfil' => null]);
            return response()->json(['usuario' => $this->formatear($usuario)]);
        }

        if (! preg_match('/^data:image\/(\w+);base64,/', $request->foto, $tipo)) {
            return response()->json(['mensaje' => 'Formato de imagen inválido.'], 422);
        }

        $extension = $tipo[1] === 'jpeg' ? 'jpg' : $tipo[1];
        $contenido = base64_decode(substr($request->foto, strpos($request->foto, ',') + 1));
        $ruta = 'perfiles/' . Str::uuid() . '.' . $extension;
        Storage::disk('public')->put($ruta, $contenido);

        $usuario->update(['foto_perfil' => Storage::url($ruta)]);

        return response()->json(['usuario' => $this->formatear($usuario)]);
    }

    /**
     * PUT /api/usuario/convivencia
     * Fusiona (merge parcial) sobre el JSON preferencias_convivencia.
     */
    public function actualizarConvivencia(Request $request)
    {
        $usuario = $request->user();
        $actuales = $usuario->preferencias_convivencia ?? [];
        $nuevas = array_merge($actuales, $request->all());

        $usuario->update(['preferencias_convivencia' => $nuevas]);

        return response()->json(['preferencias' => $nuevas]);
    }

    /**
     * Mismo shape que Usuario::paraFrontend() (roles[] en vez de
     * tipoUsuario — ver Fase 1/4). Se mantiene como método aparte en
     * vez de reusar paraFrontend() directamente porque este controlador
     * ya lo llamaba así desde antes y otros archivos podrían depender
     * de su firma; el contenido ahora es equivalente.
     */
    public function formatear(Usuario $usuario): array
    {
        $usuario->loadMissing('roles');

        return [
            'id' => $usuario->id_usuario,
            'nombre' => $usuario->nombre_completo,
            'email' => $usuario->correo,
            'roles' => $usuario->roles->pluck('nombre')->values(),
            'telefono' => $usuario->telefono,
            'direccion' => $usuario->direccion,
            'genero' => $usuario->genero,
            'fotoPerfil' => $usuario->foto_perfil,
            'preferenciasConvivencia' => $usuario->preferencias_convivencia ?? [],
        ];
    }
}
