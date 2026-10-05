<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>@yield('code') – @yield('title') | Roommatch</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="{{ asset('css/error-azul.css') }}" rel="stylesheet">
</head>
<body>

  <!-- Background shapes -->
  <div class="shape shape-1"></div>
  <div class="shape shape-2"></div>
  <div class="shape shape-3"></div>

  <main>
    <div class="card">

      <!-- Logo -->
      <a href="{{ url('/') }}" class="logo">
        <svg class="logo-icon" viewBox="0 0 40 40" fill="none">
          <path d="M20 6L4 19h4v15h9v-9h6v9h9V19h4L20 6z" fill="#2b6cb0"/>
          <rect x="16" y="28" width="8" height="6" rx="1" fill="#4299e1"/>
        </svg>
        <span class="logo-text">Roommatch</span>
      </a>

      <!-- Number -->
      <div class="error-number">@yield('code')</div>

      <div class="divider"></div>

      <!-- Text -->
      <h1 class="error-title">@yield('title')</h1>
      <p class="error-desc">
        @yield('description')
      </p>

      <!-- Buttons -->
      <div class="btn-group">
        <a href="{{ url('/') }}" class="btn btn-primary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Volver al inicio
        </a>
        <a href="javascript:history.back()" class="btn btn-secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 18 9 12 15 6"/></svg>
          Página anterior
        </a>
      </div>

      <!-- Footer -->
      <footer>
        © Roommatch {{ date('Y') }} &nbsp;|&nbsp;
        <a href="{{ url('/ayuda') }}">ayuda</a> &nbsp;|&nbsp;
        <a href="{{ url('/faq') }}">faq</a> &nbsp;|&nbsp;
        <a href="{{ url('/contactanos') }}">contactanos</a>
      </footer>

    </div>
  </main>
</body>
</html>
