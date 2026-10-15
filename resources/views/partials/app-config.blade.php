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
    rolActivo       'cliente' | 'admin' | null — el rol con el que entró.
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
<script>
  window.APP = Object.freeze({
    baseUrl: @json(rtrim(url('/'), '/')),
    apiBase: @json(url('/api')),
    basePath: @json(rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/')),
    user: @json($usuarioSesion ? $usuarioSesion->paraFrontend() : null),
    rolActivo: @json($usuarioSesion ? session(\App\Services\SesionUsuario::ROL_ACTIVO) : null),
    rolesPendientes: @json($usuarioSesion ? [] : \App\Services\SesionUsuario::rolesPendientes(request())),
  });
</script>
