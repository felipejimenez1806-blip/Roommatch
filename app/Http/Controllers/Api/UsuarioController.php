<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Usuario;
use App\Rules\ImagenSubida;
use App\Support\Telefono;
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

        // "300 111 2233" / "+57 300-111-2233" -> "+573001112233" (formato único en BD).
        $request->merge(['telefono' => Telefono::normalizarOConservar($request->input('telefono'))]);

        // Un teléfono viejo ya guardado (7 dígitos, sin +57...) no debe bloquear
        // que la persona edite su nombre o dirección: solo se exige el formato
        // nuevo cuando el teléfono CAMBIA.
        $reglaTelefono = $request->input('telefono') === $usuario->telefono
            ? ['required', 'string']
            : ['required', 'string', 'regex:' . Telefono::REGEX];

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'min:3', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuario', 'correo')->ignore($usuario->id_usuario, 'id_usuario')],
            'telefono' => $reglaTelefono,
            'direccion' => ['nullable', 'string', 'min:3', 'max:255'],
            'genero' => ['nullable', 'in:masculino,femenino,otro,prefiero_no_decir'],
        ], [
            'nombre.required' => 'Ingresa tu nombre completo.',
            'nombre.min' => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max' => 'El nombre no puede superar 150 caracteres.',
            'email.required' => 'Ingresa tu correo.',
            'email.email' => 'Ingresa un correo válido.',
            'email.max' => 'El correo no puede superar 150 caracteres.',
            'email.unique' => 'Ese correo ya está registrado en otra cuenta.',
            'telefono.required' => 'Ingresa tu teléfono.',
            'telefono.regex' => Telefono::MENSAJE,
            'direccion.min' => 'La dirección debe tener al menos 3 caracteres.',
            'direccion.max' => 'La dirección no puede superar 255 caracteres.',
            'genero.in' => 'El género seleccionado no es válido.',
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
        // ImagenSubida: solo JPG/PNG/WEBP reales y con tope de peso. Antes se
        // aceptaba cualquier "data:image/<lo-que-sea>" y se usaba como extensión.
        $request->validate([
            'foto' => ['nullable', 'string', 'starts_with:data:image/', new ImagenSubida('La foto de perfil')],
        ], [
            'foto.starts_with' => 'La foto debe ser una imagen JPG, PNG o WEBP.',
        ]);
        $usuario = $request->user();

        if (! $request->foto) {
            $usuario->update(['foto_perfil' => null]);
            return response()->json(['usuario' => $this->formatear($usuario)]);
        }

        if (! preg_match('/^data:image\/(jpeg|png|webp);base64,/', $request->foto, $tipo)) {
            return response()->json(['message' => 'Formato de imagen inválido.', 'errors' => ['foto' => ['Formato de imagen inválido.']]], 422);
        }

        $extension = $tipo[1] === 'jpeg' ? 'jpg' : $tipo[1];
        $contenido = base64_decode(substr($request->foto, strpos($request->foto, ',') + 1), true);
        if ($contenido === false) {
            return response()->json(['message' => 'La foto está dañada.', 'errors' => ['foto' => ['La foto está dañada. Súbela de nuevo.']]], 422);
        }
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
        $l = config('roommatch.limites');

        // Lista CERRADA de claves (las que envía perfil.js). Antes se hacía
        // array_merge con $request->all(): cualquier clave, de cualquier tamaño,
        // terminaba guardada en el JSON del usuario.
        $booleanos = [
            'fumador' => 'Fumador/a', 'tiene_mascota' => 'Tengo mascota', 'ordenado' => 'Ordenado/a',
            'sociable' => 'Sociable', 'madrugador' => 'Madrugador/a', 'trasnochador' => 'Trasnochador/a',
            'quiere_amueblado' => 'Busco amueblado', 'quiere_bano_privado' => 'Busco baño privado',
            'cerca_universidad' => 'Cerca a universidad', 'cerca_transporte_publico' => 'Cerca a transporte',
            'permite_mascotas' => 'Permite mascotas', 'permite_fumar' => 'Permite fumar',
            'permite_fiestas' => 'Permite fiestas', 'permite_visitas' => 'Permite visitas',
            'permite_parejas' => 'Permite parejas',
        ];

        $reglas = [
            'numero_habitantes' => ['nullable', 'integer', "min:{$l['habitantes_min']}", "max:{$l['habitantes_max']}"],
            'presupuesto_max' => ['nullable', 'integer', "min:{$l['presupuesto_min']}", "max:{$l['presupuesto_max']}"],
            'ambiente_preferido' => ['nullable', 'in:estudiantes,profesionales,otro'],
            'ambiente_hogar' => ['nullable', 'in:estudiantes,profesionales,otro'],
            'horario' => ['nullable', 'in:diurno,nocturno,mixto'],
            'tiempo_busqueda' => ['nullable', 'in:corto,largo'],
            'fecha_mudanza' => ['nullable', 'in:inmediata,1mes,flexible'],
            'horario_entrada' => ['nullable', 'string', "max:{$l['texto_corto_max']}"],
            'horario_silencio' => ['nullable', 'string', "max:{$l['texto_corto_max']}"],
        ];
        foreach (array_keys($booleanos) as $campo) {
            $reglas[$campo] = ['nullable', 'boolean'];
        }

        $cop = fn (int $n) => '$' . number_format($n, 0, ',', '.');
        $mensajes = [
            'numero_habitantes.integer' => 'El número de habitantes debe ser un número entero.',
            'numero_habitantes.min' => 'El número de habitantes no puede ser negativo.',
            'numero_habitantes.max' => "El número de habitantes no puede ser mayor a {$l['habitantes_max']}.",
            'presupuesto_max.integer' => 'El presupuesto debe ser un número entero en pesos, sin puntos, comas ni decimales.',
            'presupuesto_max.min' => "El presupuesto mínimo es {$cop($l['presupuesto_min'])} COP al mes.",
            'presupuesto_max.max' => "El presupuesto no puede superar {$cop($l['presupuesto_max'])} COP al mes. Revisa que no tengas ceros de más.",
            'ambiente_preferido.in' => 'El ambiente seleccionado no es válido.',
            'ambiente_hogar.in' => 'El ambiente de la casa seleccionado no es válido.',
            'horario.in' => 'El horario seleccionado no es válido.',
            'tiempo_busqueda.in' => 'El tiempo de búsqueda seleccionado no es válido.',
            'fecha_mudanza.in' => 'La fecha de mudanza seleccionada no es válida.',
            'horario_entrada.max' => "El horario de entrada no puede superar {$l['texto_corto_max']} caracteres.",
            'horario_silencio.max' => "El horario de silencio no puede superar {$l['texto_corto_max']} caracteres.",
        ];
        foreach ($booleanos as $campo => $etiqueta) {
            $mensajes["{$campo}.boolean"] = "La opción «{$etiqueta}» no es válida.";
        }

        // validate() devuelve SOLO las claves con regla: lo demás se descarta.
        $datos = $request->validate($reglas, $mensajes);

        $nuevas = array_merge($usuario->preferencias_convivencia ?? [], $datos);
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
