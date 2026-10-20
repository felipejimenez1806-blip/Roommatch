{{--
  partials/app-config.blade.php

  Única fuente de la configuración y de la sesión que el servidor le pasa al
  JS. Se incluye en el <head> de TODA vista que cargue scripts (layouts y
  páginas sin layout), siempre ANTES de cualquier @vite: los módulos la leen
  una sola vez (core/config.js y core/auth.js).

    baseUrl  URL base de la app, sin barra final. En producción puede incluir
             un subdirectorio (https://dominio.com/roommatch-laravel/public).
    apiBase  URL base de la API (baseUrl + /api).
    basePath Solo la parte de ruta del subdirectorio ("" si vive en la raíz).

  Sesión (cookie de Laravel; el JS nunca ve un token):
    user            Usuario con sesión (Usuario::paraFrontend()) o null.
    rolActivo       'cliente' | 'moderador' | 'admin' | null — el rol con el que entró.
    permisos        Permisos del rol activo (App\Support\Permisos::MAPA), p. ej.
                    ['gestionar-reportes', 'escalar-reportes', ...]. SOLO sirve
                    para mostrar u ocultar botones: el servidor valida cada acción.
    localidades     Catálogo cerrado de localidades de Bogotá ([{nombre, slug}], tabla
                    `localidad`). Alimenta los <select> de zona de los wizards.
    limites         Topes de validación (config/roommatch.php): precio, habitantes, largos de
                    texto, fotos. Los usa core/validacion.js.
    rolesPendientes Si la contraseña ya fue verificada pero falta elegir con
                    qué rol entrar (cuenta con más de un rol, p. ej. tras un
                    login social), los roles a elegir; [] en otro caso.
--}}
@php
    $usuarioSesion = auth()->user();
    if ($usuarioSesion) {
        $usuarioSesion->loadMissing('roles');
    }
@endphp
{{-- Js::from (y no @json) a propósito: escapa < > & ' " dentro del JSON, así que un
     nombre o dirección con "</script>" o "<!--" no puede cerrar este <script>. --}}
<script>
  window.APP = Object.freeze({
    baseUrl: {{ \Illuminate\Support\Js::from(rtrim(url('/'), '/')) }},
    apiBase: {{ \Illuminate\Support\Js::from(url('/api')) }},
    basePath: {{ \Illuminate\Support\Js::from(rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/')) }},
    user: {{ \Illuminate\Support\Js::from($usuarioSesion ? $usuarioSesion->paraFrontend() : null) }},
    rolActivo: {{ \Illuminate\Support\Js::from($usuarioSesion ? session(\App\Services\SesionUsuario::ROL_ACTIVO) : null) }},
    permisos: {{ \Illuminate\Support\Js::from($usuarioSesion ? \App\Support\Permisos::delRolActivo() : []) }},
    rolesPendientes: {{ \Illuminate\Support\Js::from($usuarioSesion ? [] : \App\Services\SesionUsuario::rolesPendientes(request())) }},
    localidades: {{ \Illuminate\Support\Js::from(\App\Models\Localidad::todas()) }},
    limites: {{ \Illuminate\Support\Js::from(config('roommatch.limites')) }},
  });
</script>
