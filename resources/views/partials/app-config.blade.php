{{--
  partials/app-config.blade.php

  Única fuente de la configuración que el servidor le pasa al JS. Se incluye
  en el <head> de TODA vista que cargue scripts (layouts y páginas sin
  layout), siempre ANTES de cualquier @vite: los módulos la leen una sola
  vez en resources/js/core/config.js.

    baseUrl  URL base de la app, sin barra final. En producción puede incluir
             un subdirectorio (https://dominio.com/roommatch-laravel/public).
    apiBase  URL base de la API (baseUrl + /api).
    basePath Solo la parte de ruta del subdirectorio ("" si vive en la raíz).
--}}
<script>
  window.APP = Object.freeze({
    baseUrl: @json(rtrim(url('/'), '/')),
    apiBase: @json(url('/api')),
    basePath: @json(rtrim(parse_url(url('/'), PHP_URL_PATH) ?? '', '/')),
  });
</script>
