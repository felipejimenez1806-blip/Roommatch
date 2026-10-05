<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Roommatch | Nueva contraseña</title>
  <link rel="stylesheet" href="{{ asset('Css/login.css') }}">
  <link rel="stylesheet" href="{{ asset('Css/recuperar-password.css') }}">
</head>
<body>

  <div class="fondo-overlay"></div>

  <main class="login-wrapper">
    <div class="login-card">

      <header class="login-header">
        <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo">
        <h1>Crea tu nueva contraseña</h1>
      </header>

      <form id="nuevaPasswordForm" class="login-form" novalidate>

        <div class="input-group" id="passwordGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          </span>
          <input type="password" id="password" name="password" placeholder="Nueva contraseña" autocomplete="new-password" required>
          <button type="button" class="toggle-password" id="togglePassword">Mostrar</button>
        </div>
        <p class="field-error" id="passwordError"></p>

        <div class="input-group" id="passwordConfirmGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          </span>
          <input type="password" id="passwordConfirm" name="password_confirmation" placeholder="Confirmar contraseña" autocomplete="new-password" required>
        </div>
        <p class="field-error" id="passwordConfirmError"></p>

        <p class="form-message" id="formMessage" role="alert"></p>

        <button type="submit" class="btn-primary" id="submitBtn">
          <span class="btn-text">Guardar contraseña</span>
          <span class="spinner" hidden></span>
        </button>
      </form>
    </div>
  </main>

  <div class="toast" id="toast"></div>

  <script src="{{ asset('Js/nueva-contrasena.js') }}"></script>
</body>
</html>
