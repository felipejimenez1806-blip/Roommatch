<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>@yield('code') – @yield('title') | Roommatch</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Display:ital@0;1&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link href="{{ asset('css/error-naranja.css') }}" rel="stylesheet">
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
          <path d="M20 6L4 19h4v15h9v-9h6v9h9V19h4L20 6z" fill="#c53030"/>
          <rect x="16" y="28" width="8" height="6" rx="1" fill="#fc8181"/>
        </svg>
        <span class="logo-text">Roommatch</span>
      </a>

      <!-- Animated icon -->
      <div class="icon-wrap">
        <div class="icon-bg">
          <svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/>
            <line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
      </div>

      <!-- Status badge -->
      <div class="status-bar">
        <span class="status-dot"></span>
        @yield('badge')
      </div>

      <!-- Number -->
      <div class="error-number">@yield('code')</div>

      <div class="divider"></div>

      <!-- Text -->
      <h1 class="error-title">@yield('title')</h1>
      <p class="error-desc">
        @yield('description')
      </p>

      <!-- Retry hint -->
      <div class="retry-box">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
        Intenta recargar la página en unos minutos
      </div>

      <!-- Buttons -->
      <div class="btn-group">
        <button class="btn btn-primary" onclick="window.location.reload()">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 .49-3.51"/></svg>
          Reintentar ahora
        </button>
        <a href="{{ url('/') }}" class="btn btn-secondary">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
          Volver al inicio
        </a>
        <a href="mailto:soporte@roommatch.com" class="btn btn-ghost">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
          Contactar soporte
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
