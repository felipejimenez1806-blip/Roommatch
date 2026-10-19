<?php

namespace App\Http\Requests;

use App\Models\Localidad;
use App\Rules\ImagenSubida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonaRoomieRequest extends FormRequest
{
    private const BOOLEANOS = [
        'fumador' => 'Fumador/a', 'tiene_mascota' => 'Tengo mascota propia', 'ordenado' => 'Ordenado/a',
        'sociable' => 'Sociable', 'madrugador' => 'Madrugador/a', 'trasnochador' => 'Trasnochador/a',
        'fiestero' => 'Me gustan las reuniones/fiestas', 'trabaja_desde_casa' => 'Trabajo desde casa',
        'viaja_frecuentemente' => 'Viajo frecuentemente', 'acepta_mascotas' => 'Acepto mascotas',
        'acepta_fumadores' => 'Acepto fumadores', 'acepta_visitas' => 'Acepto visitas',
        'acepta_parejas' => 'Acepto parejas', 'quiere_amueblado' => 'Busco amueblado',
        'quiere_bano_privado' => 'Busco baño privado', 'quiere_parqueadero' => 'Busco parqueadero',
        'cerca_universidad' => 'Cerca a universidad', 'cerca_transporte_publico' => 'Cerca a transporte',
        'tiene_vehiculo' => 'Tengo vehículo', 'comparte_gastos' => 'Comparto gastos',
        'referencias_verificadas' => 'Referencias verificadas',
    ];

    public function authorize(): bool
    {
        return true; // la autorización de "es el dueño" se resuelve en el controlador
    }

    /**
     * Limpia el teléfono a solo dígitos antes de validar, mismo criterio
     * que limpiarTelefono() en crear-perfil-roomie.js.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('telefono_contacto')) {
            $this->merge([
                'telefono_contacto' => preg_replace('/\D/', '', (string) $this->input('telefono_contacto')),
            ]);
        }
    }

    private function l(): array
    {
        return config('roommatch.limites');
    }

    public function rules(): array
    {
        $l = $this->l();

        $reglas = [
            'edad' => ['required', 'integer', "min:{$l['edad_min']}", "max:{$l['edad_max']}"],
            'genero' => ['required', 'in:Mujer,Hombre,Otro'],
            'ocupacion' => ['required', 'in:Estudiante,Profesional,Freelance,Otro'],
            // Catálogo cerrado: debe ser EXACTAMENTE el nombre de una fila de `localidad`.
            'zona' => ['required', 'string', Rule::in(Localidad::nombres())],
            'ciudad' => ['nullable', 'string', "max:{$l['ciudad_max']}"],
            // Antes solo `gt:0`: sin tope, un presupuesto enorme reventaba decimal(10,2).
            'presupuesto' => ['required', 'integer', "min:{$l['presupuesto_min']}", "max:{$l['presupuesto_max']}"],
            'telefono_contacto' => ['required', 'string', "digits_between:{$l['telefono_min']},{$l['telefono_max']}"],
            'descripcion' => ['required', 'string', "min:{$l['descripcion_min']}", "max:{$l['descripcion_max']}"],
            // data:image/... (nuevo) o URL ya existente (sin cambios, en edición)
            'foto' => ['required', new ImagenSubida('La foto de perfil')],

            // ---- Estilo de vida / convivencia (tabla caracteristicas_persona) ----
            'tiempo_busqueda' => ['required', 'in:corto,largo'],
            'fecha_mudanza' => ['required', 'in:inmediata,1mes,flexible'],
            'horario' => ['nullable', 'in:diurno,nocturno,mixto'],
            'ambiente_preferido' => ['nullable', 'in:estudiantes,profesionales,otro'],
        ];

        foreach (array_keys(self::BOOLEANOS) as $campo) {
            $reglas[$campo] = ['boolean'];
        }

        return $reglas;
    }

    public function messages(): array
    {
        $l = $this->l();
        $cop = fn (int $n) => '$' . number_format($n, 0, ',', '.');

        $mensajes = [
            'edad.required' => "Ingresa tu edad ({$l['edad_min']}-{$l['edad_max']}).",
            'edad.integer' => 'La edad debe ser un número entero.',
            'edad.min' => "Debes tener al menos {$l['edad_min']} años para publicar tu perfil.",
            'edad.max' => "Ingresa una edad válida (máximo {$l['edad_max']}).",

            'genero.required' => 'Selecciona tu género.',
            'genero.in' => 'El género seleccionado no es válido.',
            'ocupacion.required' => 'Selecciona tu ocupación.',
            'ocupacion.in' => 'La ocupación seleccionada no es válida.',

            'zona.required' => 'Selecciona la zona o localidad donde buscas.',
            'zona.in' => 'Selecciona una localidad válida de la lista.',
            'ciudad.max' => "La ciudad no puede superar {$l['ciudad_max']} caracteres.",

            'presupuesto.required' => 'Ingresa tu presupuesto mensual.',
            'presupuesto.integer' => 'El presupuesto debe ser un número entero en pesos, sin puntos, comas ni decimales.',
            'presupuesto.min' => "El presupuesto mínimo es {$cop($l['presupuesto_min'])} COP al mes.",
            'presupuesto.max' => "El presupuesto no puede superar {$cop($l['presupuesto_max'])} COP al mes. Revisa que no tengas ceros de más.",

            'telefono_contacto.required' => 'Ingresa un teléfono de contacto.',
            'telefono_contacto.digits_between' => "El teléfono debe tener entre {$l['telefono_min']} y {$l['telefono_max']} dígitos, con indicativo de país (ej. 573001112233).",

            'descripcion.required' => "Cuéntanos un poco más de ti (mínimo {$l['descripcion_min']} caracteres).",
            'descripcion.min' => "Cuéntanos un poco más de ti (mínimo {$l['descripcion_min']} caracteres).",
            'descripcion.max' => "La descripción no puede superar {$l['descripcion_max']} caracteres.",

            'foto.required' => 'Sube una foto de perfil para continuar.',

            'tiempo_busqueda.required' => 'Selecciona el tiempo de estadía que buscas.',
            'tiempo_busqueda.in' => 'El tiempo de estadía seleccionado no es válido.',
            'fecha_mudanza.required' => 'Selecciona tu fecha de mudanza.',
            'fecha_mudanza.in' => 'La fecha de mudanza seleccionada no es válida.',
            'horario.in' => 'El horario seleccionado no es válido.',
            'ambiente_preferido.in' => 'El ambiente seleccionado no es válido.',
        ];

        foreach (self::BOOLEANOS as $campo => $etiqueta) {
            $mensajes["{$campo}.boolean"] = "La opción «{$etiqueta}» no es válida.";
        }

        return $mensajes;
    }
}
