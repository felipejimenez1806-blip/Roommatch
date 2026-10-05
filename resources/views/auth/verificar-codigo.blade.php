<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Roommatch | Verificar código</title>
  <link rel="stylesheet" href="{{ asset('Css/login.css') }}">
  <link rel="stylesheet" href="{{ asset('Css/recuperar-password.css') }}">
</head>
<body>

  <div class="fondo-overlay"></div>

  <main class="login-wrapper">
    <div class="login-card">

      <header class="login-header">
        <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo">
        <h1>Verifica tu código</h1>
      </header>

      <p class="recuperar-descripcion" id="descripcionCorreo">
        Ingresa el código de 6 dígitos que enviamos a tu correo.
      </p>

      <form id="codigoForm" class="login-form" novalidate>

        <div class="codigo-grupo" id="codigoGrupo">
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="codigo-casilla" data-index="0" autocomplete="one-time-code">
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="codigo-casilla" data-index="1">
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="codigo-casilla" data-index="2">
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="codigo-casilla" data-index="3">
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="codigo-casilla" data-index="4">
          <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="1" class="codigo-casilla" data-index="5">
        </div>
        <p class="field-error" id="codigoError" style="text-align:center;"></p>

        <p class="form-message" id="formMessage" role="alert"></p>

        <button type="submit" class="btn-primary" id="submitBtn">
          <span class="btn-text">Verificar código</span>
          <span class="spinner" hidden></span>
        </button>

        <div class="reenviar-fila">
          ¿No te llegó? <button type="button" id="reenviarBtn">Reenviar código</button>
        </div>

        <a href="{{ route('login') }}" class="forgot-link">Volver a iniciar sesión</a>
      </form>
    </div>
  </main>

  <div class="toast" id="toast"></div>

  <script src="{{ asset('Js/verificar-codigo.js') }}"></script>
</body>
</html>
