<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Roommatch | No se pudo iniciar sesión</title>
  <link rel="stylesheet" href="{{ asset('Css/oauth-error.css') }}">
</head>
<body>
  <div class="caja">
    <h1>No se pudo iniciar sesión</h1>
    <p>{{ $mensaje ?? 'Ocurrió un error al conectar tu cuenta. Intenta de nuevo.' }}</p>
    <a href="{{ route('login') }}">Volver al inicio de sesión</a>
  </div>
</body>
</html>
