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
@vite('resources/css/index.css')
@stack('css')
</head>
<body>

{{--
  A propósito SIN @include('partials.admin-nav') aquí: esos botones del
  sidebar son interruptores de pestaña por JS (admin.js hace
  querySelectorAll('.prf-panel') y les cambia la clase 'active'), no
  enlaces reales — solo funcionan dentro de /admin, donde SÍ existen
  esas secciones .prf-panel. Puestos en una página aparte como esta, se
  verían clicables pero no harían nada. Por eso esta variante del
  layout es para pantallas admin de un solo propósito (como crear un
  administrador), y layouts.admin (con sidebar) es para el panel con
  pestañas. Cada vista con este layout trae su propio enlace "volver
  al panel" si lo necesita.
--}}
@yield('content')

{{-- nav.js sigue haciendo falta: aplica el auto-logout por inactividad
     también en esta pantalla (crear una cuenta admin es sensible, no
     debería quedar abierta indefinidamente). --}}
@vite('resources/js/nav.js')
@stack('js')

</body>
</html>
