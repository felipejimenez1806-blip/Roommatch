<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Roommatch | Crear una cuenta</title>
  <link rel="stylesheet" href="{{ asset('Css/signup.css') }}">
</head>
<body>

  <div class="fondo-overlay"></div>

  <main class="login-wrapper">
    <div class="login-card">

      <header class="login-header">
        <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo">
        <h1>Crear una cuenta</h1>
      </header>

      <form id="signupForm" class="login-form" novalidate>

        <!-- Nombre completo -->
        <div class="input-group" id="nombreGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><path d="M20 21v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"/><circle cx="12" cy="7" r="4"/></svg>
          </span>
          <input type="text" id="nombre" name="nombre" placeholder="Nombre completo" autocomplete="name" required>
        </div>
        <p class="field-error" id="nombreError"></p>

        <!-- Correo electrónico -->
        <div class="input-group" id="emailGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          </span>
          <input type="email" id="email" name="email" placeholder="Correo electrónico" autocomplete="email" required>
        </div>
        <p class="field-error" id="emailError"></p>

        <!-- Teléfono -->
        <div class="input-group" id="telefonoGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.68 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.32 1.85.55 2.81.68A2 2 0 0 1 22 16.92z"/></svg>
          </span>
          <input type="tel" id="telefono" name="telefono" placeholder="Teléfono" autocomplete="tel" required>
        </div>
        <p class="field-error" id="telefonoError"></p>

        <!-- Dirección -->
        <div class="input-group" id="direccionGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
          </span>
          <input type="text" id="direccion" name="direccion" placeholder="Dirección" autocomplete="street-address" required>
        </div>
        <p class="field-error" id="direccionError"></p>

        <!-- Contraseña -->
        <div class="input-group" id="passwordGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          </span>
          <input type="password" id="password" name="password" placeholder="Contraseña" autocomplete="new-password" required>
          <button type="button" class="toggle-password" id="togglePassword">Mostrar</button>
        </div>
        <p class="field-error" id="passwordError"></p>
        <ul class="password-checklist" id="passwordChecklist">
          <li data-rule="length">Mínimo 8 caracteres</li>
          <li data-rule="upper">Una letra mayúscula</li>
          <li data-rule="lower">Una letra minúscula</li>
          <li data-rule="number">Un número</li>
          <li data-rule="special">Un carácter especial (!@#$%...)</li>
          <li data-rule="noName">No debe contener tu nombre ni tu correo</li>
        </ul>

        <!-- Confirmar contraseña -->
        <div class="input-group" id="confirmPasswordGroup">
          <span class="icon">
            <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
          </span>
          <input type="password" id="confirmPassword" name="confirmPassword" placeholder="Confirmar contraseña" autocomplete="new-password" required>
          <button type="button" class="toggle-password" id="toggleConfirmPassword">Mostrar</button>
        </div>
        <p class="field-error" id="confirmPasswordError"></p>

        <!-- Acepto términos y condiciones -->
        <div class="checkbox-group" id="terminosGroup">
          <input type="checkbox" id="aceptaTerminos" name="aceptaTerminos" required>
          <label for="aceptaTerminos">
            Acepto los <a href="{{ route('terminos') }}" target="_blank" rel="noopener">términos y condiciones</a> de Roommatch
          </label>
        </div>
        <p class="field-error" id="terminosError"></p>

        <p class="form-message" id="formMessage" role="alert"></p>

        <button type="submit" class="btn-primary" id="submitBtn">
          <span class="btn-text">Crear cuenta</span>
          <span class="spinner" hidden></span>
        </button>

        <p class="small-note" id="loginNote">¿Ya tienes una cuenta?</p>
        <a href="{{ route('login') }}" id="loginLink" class="btn-secondary">Inicia sesión</a>

        <div class="divider"><span>o regístrate con</span></div>

        <div class="social-row">
          <button type="button" class="btn-social" data-provider="google">
            <svg viewBox="0 0 48 48" class="social-icon"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.7-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 13 24 13c3.1 0 5.9 1.2 8 3.1l5.7-5.7C34.5 6.1 29.5 4 24 4c-7.7 0-14.3 4.3-17.7 10.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.6 26.7 36.5 24 36.5c-5.3 0-9.7-3.4-11.3-8.1l-6.5 5C9.6 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.3-2.3 4.2-4.3 5.6l6.2 5.2C40.9 36 44 30.5 44 24c0-1.3-.1-2.7-.4-3.5z"/></svg>
            Google
          </button>
          <button type="button" class="btn-social" data-provider="facebook">
            <svg viewBox="0 0 24 24" class="social-icon" fill="#1877F2"><path d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.4v7A10 10 0 0 0 22 12z"/></svg>
            Facebook
          </button>
        </div>

      </form>
    </div>

    <footer class="page-footer">
      <p>© Roommatch 2026 &nbsp;|&nbsp; <a href="{{ route('ayuda') }}">ayuda</a> &nbsp;|&nbsp; <a href="{{ route('faq') }}">faq</a> &nbsp;|&nbsp; <a href="{{ route('terminos') }}">términos y condiciones</a></p>
    </footer>
  </main>

  <div class="toast" id="toast"></div>

  <script src="{{ asset('Js/signup.js') }}"></script>
</body>
</html>
