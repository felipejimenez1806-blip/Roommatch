<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
{{-- window.APP (URL base, sesión y permisos del rol activo): va primero,
     antes de cualquier @vite. El acceso a esta zona lo decide el servidor
     (middleware zona.moderador); los DATOS los protege `can:` en la API. --}}
@include('partials.app-config')
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Roommatch · Moderación')</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
@vite('resources/css/index.css')
@stack('css')
</head>
<body>

<div class="prf-page">
  @include('partials.moderador-nav')

  <main class="prf-content">
    @yield('content')
  </main>
</div>

<!-- Modal genérico y toast (mismos ids que layouts.admin). -->
<div class="adm-modal-overlay" id="admModalOverlay" hidden>
  <div class="adm-modal" id="admModal"></div>
</div>
<div class="adm-toast" id="admToast"></div>

{{-- nav.js: cerrar sesión, modal "Cambiar de rol" y auto-logout por inactividad. --}}
@vite('resources/js/nav.js')
@stack('js')

</body>
</html>
