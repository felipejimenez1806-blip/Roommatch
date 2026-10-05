<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Roommatch | Recuperar contraseña</title>
  <link rel="stylesheet" href="{{ asset('Css/login.css') }}">
  <link rel="stylesheet" href="{{ asset('Css/recuperar-password.css') }}">
</head>
<body>

  <div class="fondo-overlay"></div>

  <main class="login-wrapper">
    <div class="login-card">

      <header class="login-header">
        <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo">
        <h1>Recuperar contraseña</h1>
      </header>

      <p class="recuperar-descripcion">
        Ingresa el correo asociado a tu cuenta y te enviaremos un código de verificación de 6 dígitos.
      </p>

      <form id="recuperarForm" class="login-form" novalidate>

        <div class="input-group" id="emailGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <input type="email" id="email" name="email" placeholder="Correo electrónico" autocomplete="email" required>
        </div>
        <p class="field-error" id="emailError"></p>

        <p class="form-message" id="formMessage" role="alert"></p>

        <button type="submit" class="btn-primary" id="submitBtn">
          <span class="btn-text">Enviar código</span>
          <span class="spinner" hidden></span>
        </button>

        <a href="{{ route('login') }}" class="forgot-link">Volver a iniciar sesión</a>
      </form>
    </div>
  </main>

  <div class="toast" id="toast"></div>

  <script src="{{ asset('Js/recuperar-password.js') }}"></script>
</body>
</html>
