<?php

namespace App\Http\Requests;

use App\Models\Localidad;
use App\Rules\ImagenSubida;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PublicacionRequest extends FormRequest
{
    /** Booleanos de `caracteristicas_publicacion` (mismos que mapearCaracteristicas()). */
    private const BOOLEANOS = [
        'amueblado' => 'Amueblado', 'bano_privado' => 'Baño privado', 'cocina_compartida' => 'Cocina compartida',
        'lavadora' => 'Lavadora', 'secadora' => 'Secadora', 'parqueadero' => 'Parqueadero',
        'balcon' => 'Balcón', 'terraza' => 'Terraza',
        'incluye_agua' => 'Agua incluida', 'incluye_luz' => 'Luz incluida',
        'incluye_internet' => 'Internet incluido', 'incluye_gas' => 'Gas incluido',
        'fumadores_en_casa' => 'Fumadores en casa', 'mascotas_en_casa' => 'Mascotas en casa',
        'habitacion_compartida' => 'Habitación compartida',
        'permite_mascotas' => 'Permite mascotas', 'permite_visitas' => 'Permite visitas',
        'permite_fumar' => 'Permite fumar', 'permite_fiestas' => 'Permite fiestas',
        'permite_parejas' => 'Permite parejas', 'cerca_transporte_publico' => 'Cerca a transporte',
        'porteria' => 'Portería', 'camaras_seguridad' => 'Cámaras de seguridad',
        'espacio_trabajo' => 'Espacio de trabajo', 'ascensor' => 'Ascensor', 'gimnasio' => 'Gimnasio',
        'zona_comun' => 'Zona común', 'cerca_supermercado' => 'Cerca a supermercado',
        'cerca_universidad' => 'Cerca a universidad',
    ];

    public function authorize(): bool
    {
        return true; // el guard de "es tuya o no" se hace en el controller
    }

    /** Solo dígitos antes de validar, igual que limpiarTelefono() del JS. */
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

        // Al CREAR, la fecha no puede ser pasada. Al EDITAR se permite: una
        // publicación vieja con fecha ya vencida debe poder guardarse igual.
        $fecha = ['required', 'date', 'before_or_equal:+2 years'];
        if ($this->isMethod('POST')) {
            $fecha[] = 'after_or_equal:today';
        }

        $reglas = [
            'tipo_espacio' => ['required', 'in:Habitación,Apartamento,Casa,Estudio'],
            'titulo' => ['required', 'string', "min:{$l['titulo_min']}", "max:{$l['titulo_max']}"],
            // Catálogo cerrado: debe ser EXACTAMENTE el nombre de una fila de `localidad`.
            'zona' => ['required', 'string', Rule::in(Localidad::nombres())],
            'ciudad' => ['nullable', 'string', "max:{$l['ciudad_max']}"],
            'direccion' => ['required', 'string', "min:{$l['direccion_min']}", "max:{$l['direccion_max']}"],
            // integer + max: la columna es decimal(10,2); sin tope, un precio
            // enorme pasaba la validación y reventaba en MySQL (error 500).
            'precio' => ['required', 'integer', "min:{$l['precio_min']}", "max:{$l['precio_max']}"],
            'fecha_disponible' => $fecha,
            'genero' => ['nullable', 'in:masculino,femenino,otro'],
            'telefono_contacto' => ['required', 'string', "digits_between:{$l['telefono_min']},{$l['telefono_max']}"],
            'descripcion' => ['required', 'string', "min:{$l['descripcion_min']}", "max:{$l['descripcion_max']}"],
            'imagenes' => ['required', 'array', 'min:1', "max:{$l['fotos_max']}"],
            'imagenes.*' => ['required', new ImagenSubida('La foto')],

            // `caracteristicas` se guarda tal cual en la BD (mapearCaracteristicas),
            // así que CADA campo se valida aquí: antes solo se validaba que fuera array.
            'caracteristicas' => ['nullable', 'array'],
            'caracteristicas.numero_habitantes' => ['nullable', 'integer', "min:{$l['habitantes_min']}", "max:{$l['habitantes_max']}"],
            'caracteristicas.ambiente_hogar' => ['nullable', 'in:estudiantes,profesionales,otro'],
            'caracteristicas.tamano_habitacion' => ['nullable', 'in:pequena,mediana,grande'],
            'caracteristicas.tipo_cama' => ['nullable', 'in:sencilla,semidoble,doble,queen,king'],
            'caracteristicas.horario_silencio' => ['nullable', 'string', "max:{$l['texto_corto_max']}"],
            'caracteristicas.horario_entrada' => ['nullable', 'string', "max:{$l['texto_corto_max']}"],
            'caracteristicas.distancia_transporte' => ['nullable', 'string', "max:{$l['texto_corto_max']}"],
            'caracteristicas.detalles_incluidos' => ['nullable', 'string', "max:{$l['detalles_max']}"],
        ];

        foreach (array_keys(self::BOOLEANOS) as $campo) {
            $reglas["caracteristicas.{$campo}"] = ['nullable', 'boolean'];
        }

        return $reglas;
    }

    public function messages(): array
    {
        $l = $this->l();
        $cop = fn (int $n) => '$' . number_format($n, 0, ',', '.');

        $mensajes = [
            'tipo_espacio.required' => 'Selecciona el tipo de espacio.',
            'tipo_espacio.in' => 'El tipo de espacio no es válido. Elige Habitación, Apartamento, Casa o Estudio.',

            'titulo.required' => "Escribe un título de al menos {$l['titulo_min']} caracteres.",
            'titulo.min' => "El título debe tener al menos {$l['titulo_min']} caracteres.",
            'titulo.max' => "El título no puede superar {$l['titulo_max']} caracteres.",

            'zona.required' => 'Selecciona la zona o localidad.',
            'zona.in' => 'Selecciona una localidad válida de la lista.',
            'ciudad.max' => "La ciudad no puede superar {$l['ciudad_max']} caracteres.",

            'direccion.required' => 'Ingresa la dirección del espacio.',
            'direccion.min' => "La dirección debe tener al menos {$l['direccion_min']} caracteres.",
            'direccion.max' => "La dirección no puede superar {$l['direccion_max']} caracteres.",

            'precio.required' => 'Ingresa el precio mensual.',
            'precio.integer' => 'El precio debe ser un número entero en pesos, sin puntos, comas ni decimales.',
            'precio.min' => "El precio mínimo es {$cop($l['precio_min'])} COP al mes.",
            'precio.max' => "El precio no puede superar {$cop($l['precio_max'])} COP al mes. Revisa que no tengas ceros de más.",

            'fecha_disponible.required' => 'Selecciona la fecha desde la que está disponible.',
            'fecha_disponible.date' => 'La fecha de disponibilidad no es válida.',
            'fecha_disponible.after_or_equal' => 'La fecha de disponibilidad no puede ser anterior a hoy.',
            'fecha_disponible.before_or_equal' => 'La fecha de disponibilidad no puede ser mayor a 2 años desde hoy.',

            'genero.in' => 'El género preferido no es válido.',

            'telefono_contacto.required' => 'Ingresa un teléfono de contacto.',
            'telefono_contacto.digits_between' => "El teléfono debe tener entre {$l['telefono_min']} y {$l['telefono_max']} dígitos, con indicativo de país (ej. 573001112233).",

            'descripcion.required' => "Describe el espacio con al menos {$l['descripcion_min']} caracteres.",
            'descripcion.min' => "Describe el espacio con al menos {$l['descripcion_min']} caracteres.",
            'descripcion.max' => "La descripción no puede superar {$l['descripcion_max']} caracteres.",

            'imagenes.required' => 'Sube al menos una foto para publicar.',
            'imagenes.array' => 'Las fotos enviadas no son válidas.',
            'imagenes.min' => 'Sube al menos una foto para publicar.',
            'imagenes.max' => "Puedes subir máximo {$l['fotos_max']} fotos.",

            'caracteristicas.numero_habitantes.integer' => 'El número de habitantes debe ser un número entero.',
            'caracteristicas.numero_habitantes.min' => 'El número de habitantes no puede ser negativo.',
            'caracteristicas.numero_habitantes.max' => "El número de habitantes no puede ser mayor a {$l['habitantes_max']}.",

            'caracteristicas.ambiente_hogar.in' => 'El ambiente del hogar no es válido.',
            'caracteristicas.tamano_habitacion.in' => 'El tamaño de la habitación no es válido.',
            'caracteristicas.tipo_cama.in' => 'El tipo de cama no es válido.',
            'caracteristicas.horario_silencio.max' => "El horario de silencio no puede superar {$l['texto_corto_max']} caracteres.",
            'caracteristicas.horario_entrada.max' => "El horario de entrada no puede superar {$l['texto_corto_max']} caracteres.",
            'caracteristicas.distancia_transporte.max' => "La distancia al transporte no puede superar {$l['texto_corto_max']} caracteres.",
            'caracteristicas.detalles_incluidos.max' => "Los detalles adicionales no pueden superar {$l['detalles_max']} caracteres.",
        ];

        foreach (self::BOOLEANOS as $campo => $etiqueta) {
            $mensajes["caracteristicas.{$campo}.boolean"] = "La opción «{$etiqueta}» no es válida.";
        }

        return $mensajes;
    }
}
