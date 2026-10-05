<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PersonaRoomieRequest extends FormRequest
{
    /**
     * Localidades de Bogotá permitidas para el campo "zona".
     * Misma lista que App\Http\Requests\PublicacionRequest::LOCALIDADES_BOGOTA
     * y que el <select> del wizard en crear-perfil-roomie.js — si se agrega
     * o quita una localidad, hay que sincronizar las tres.
     */
    public const LOCALIDADES_BOGOTA = [
        'Antonio Nariño',
        'Barrios Unidos',
        'Bosa',
        'Chapinero',
        'Ciudad Bolívar',
        'Engativá',
        'Fontibón',
        'Kennedy',
        'La Candelaria',
        'Los Mártires',
        'Puente Aranda',
        'Rafael Uribe Uribe',
        'San Cristóbal',
        'Santa Fe',
        'Suba',
        'Sumapaz',
        'Teusaquillo',
        'Tunjuelito',
        'Usaquén',
        'Usme',
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

    public function rules(): array
    {
        return [
            'edad' => ['required', 'integer', 'min:18', 'max:99'],
            'genero' => ['required', 'in:Mujer,Hombre,Otro'],
            'ocupacion' => ['required', 'in:Estudiante,Profesional,Freelance,Otro'],
            'zona' => ['required', 'string', Rule::in(self::LOCALIDADES_BOGOTA)],
            'ciudad' => ['nullable', 'string', 'max:100'],
            'presupuesto' => ['required', 'numeric', 'gt:0'],
            'telefono_contacto' => ['required', 'string', 'min:10', 'max:20'],
            'descripcion' => ['required', 'string', 'min:15'],
            'foto' => ['required', 'string'], // data:image/... (nuevo) o URL ya existente (sin cambios, en edición)

            // ---- Estilo de vida / convivencia (tabla caracteristicas_persona) ----
            'tiempo_busqueda' => ['required', 'in:corto,largo'],
            'fecha_mudanza' => ['required', 'in:inmediata,1mes,flexible'],
            'horario' => ['nullable', 'in:diurno,nocturno,mixto'],
            'ambiente_preferido' => ['nullable', 'in:estudiantes,profesionales,otro'],

            'fumador' => ['boolean'],
            'tiene_mascota' => ['boolean'],
            'ordenado' => ['boolean'],
            'sociable' => ['boolean'],
            'madrugador' => ['boolean'],
            'trasnochador' => ['boolean'],
            'fiestero' => ['boolean'],
            'trabaja_desde_casa' => ['boolean'],
            'viaja_frecuentemente' => ['boolean'],
            'acepta_mascotas' => ['boolean'],
            'acepta_fumadores' => ['boolean'],
            'acepta_visitas' => ['boolean'],
            'acepta_parejas' => ['boolean'],
            'quiere_amueblado' => ['boolean'],
            'quiere_bano_privado' => ['boolean'],
            'quiere_parqueadero' => ['boolean'],
            'cerca_universidad' => ['boolean'],
            'cerca_transporte_publico' => ['boolean'],
            'tiene_vehiculo' => ['boolean'],
            'comparte_gastos' => ['boolean'],
            'referencias_verificadas' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'edad.min' => 'Ingresa una edad válida (18-99).',
            'edad.max' => 'Ingresa una edad válida (18-99).',
            'zona.required' => 'Selecciona la zona o localidad donde buscas.',
            'zona.in' => 'Selecciona una localidad válida de la lista.',
            'presupuesto.gt' => 'Ingresa un presupuesto válido.',
            'telefono_contacto.min' => 'Ingresa un número de WhatsApp válido, con indicativo de país (ej. 573001112233).',
            'descripcion.min' => 'Cuéntanos un poco más de ti (mínimo 15 caracteres).',
            'foto.required' => 'Sube una foto de perfil para continuar.',
        ];
    }
}
