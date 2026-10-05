<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Roommatch | Conectando tu cuenta...</title>
  <link rel="stylesheet" href="{{ asset('Css/oauth-callback.css') }}">
</head>
<body>
  <div class="caja">
    <div class="spinner"></div>
    <p>Conectando tu cuenta...</p>
  </div>

  <script>
    // Blade inyecta aquí estos valores del servidor; la lógica que los
    // usa (y por qué) vive en oauth-callback.js.
    //
    // CAMBIO (roles N:N, Fase 4): además de token/usuario/redirectTo,
    // ahora también llegan requiereSeleccionRol, roles y rolActivo
    // (ver Usuario::emitirTokenSesion() en el backend) — la misma
    // cuenta social puede tener más de un rol, así que esta vista
    // puente necesita poder mostrar "Ingresar como…" igual que
    // login.js, en vez de asumir siempre un solo rol.
    window.__oauthToken = @json($token);
    window.__oauthRequiereSeleccionRol = @json($requiereSeleccionRol);
    window.__oauthRoles = @json($roles);
    window.__oauthRolActivo = @json($rolActivo ?? null);
    window.__oauthUser = @json($usuario);
    window.__oauthRedirectTo = @json($redirectTo);
  </script>
  <script src="{{ asset('Js/oauth-callback.js') }}"></script>
</body>
</html>
