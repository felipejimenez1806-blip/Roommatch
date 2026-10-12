<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
{{-- window.APP (URL base de la app) y guardia de rol: van primero y son
     scripts inline y síncronos a propósito — la guardia debe redirigir
     ANTES de que se pinte el resto del <head>/<body>. --}}
@include('partials.app-config')
@include('partials.admin-guard')
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Roommatch · Administración')</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
{{-- index.css: reset/tokens base que admin.css asume. nav.css no se
     incluye aquí porque es del navbar de cliente, que esta zona no usa. --}}
<link rel="stylesheet" href="{{ asset('Css/index.css') }}" />
@stack('css')
</head>
<body>

{{-- El sidebar es parte del layout, no de cada vista: cuando dividas
     el panel en rutas separadas (/admin/usuarios, /admin/reportes...),
     cada una solo define su <section class="prf-panel">, y este
     wrapper prf-page + sidebar se sigue viendo igual en todas. --}}
<div class="prf-page">
  @include('partials.admin-nav')

  <main class="prf-content">
    @yield('content')
  </main>
</div>

<!-- Modal genérico y toast: compartidos por todos los paneles admin,
     por eso viven en el layout y no repetidos en cada vista. -->
<div class="adm-modal-overlay" id="admModalOverlay" hidden>
  <div class="adm-modal" id="admModal"></div>
</div>
<div class="adm-toast" id="admToast"></div>

{{-- nav.js sigue haciendo falta: expone window.RoommatchNav.cerrarSesion()
     (usado por #admLogoutBtn en admin.js) y el auto-logout por
     inactividad, que aplica igual a las sesiones de admin. --}}
@vite('resources/js/nav.js')
@stack('js')

</body>
</html>
