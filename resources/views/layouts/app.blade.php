<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"/>
<!-- admin-guard.js va primero, sin defer/async: bloquea el parser a
     propósito para redirigir ANTES de que el resto del <head>/<body>
     se procese, si el rol del usuario no corresponde a esta zona. -->
<script src="{{ asset('Js/admin-guard.js') }}"></script>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>@yield('title', 'Roommatch')</title>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="{{ asset('Css/index.css') }}" />
<link rel="stylesheet" href="{{ asset('Css/nav.css') }}" />
@stack('css')
</head>
<body>

@include('partials.nav')

@yield('content')

@include('partials.footer')

<script>
  // Disponible globalmente para todos los JS que necesiten llamar a la API
  const API_BASE = "{{ url('/api') }}";
</script>
<script src="{{ asset('Js/nav.js') }}"></script>
@stack('js')

</body>
</html>
