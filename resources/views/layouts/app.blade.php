<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
{{-- window.APP (URL base y sesión del usuario): va primero, antes de
     cualquier @vite. El control de acceso por rol/zona ya no se hace aquí
     con JavaScript sino en el servidor (middleware zona.cliente / zona.admin). --}}
@include('partials.app-config')
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Roommatch')</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
@vite('resources/css/index.css')
@vite('resources/css/nav.css')
@stack('css')
</head>
<body>

@include('partials.nav')

@yield('content')

@include('partials.footer')

@vite('resources/js/nav.js')
@stack('js')

</body>
</html>
