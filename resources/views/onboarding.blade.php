<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Roommatch | Completa tu perfil</title>
  <link rel="stylesheet" href="{{ asset('Css/onboarding.css') }}">
</head>
<body>

  <div class="fondo-overlay"></div>

  <main class="onboarding-wrapper">
    <div class="onboarding-card">

      <header class="onboarding-header">
        <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="logo">
        <h1>¡Ya casi entras!</h1>
        <p>Nos faltan unos datos para armar tu cuenta</p>
      </header>

      <!-- Indicador de pasos -->
      <ul class="stepper" id="stepper">
        <li class="is-active" data-step="1"><span class="step-dot">1</span></li>
        <li><span class="step-line"></span></li>
        <li data-step="2"><span class="step-dot">2</span></li>
      </ul>

      <form id="onboardingForm" class="step-panels" novalidate>

        <!-- Paso 1: teléfono -->
        <section class="step-panel is-active" data-panel="1">
          <span class="step-icon">
            <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.68 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.32 1.85.55 2.81.68A2 2 0 0 1 22 16.92z"/></svg>
          </span>
          <h2>¿Cuál es tu número de teléfono?</h2>
          <p class="step-hint">Lo usamos para contactarte sobre reservas y citas</p>

          <div class="input-group" id="telefonoGroup">
            <span class="icon">
              <svg viewBox="0 0 24 24"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.68 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.32 1.85.55 2.81.68A2 2 0 0 1 22 16.92z"/></svg>
            </span>
            <input type="tel" id="telefono" name="telefono" placeholder="Ej: 3001234567" autocomplete="tel">
          </div>
          <p class="field-error" id="telefonoError"></p>

          <div class="step-nav">
            <button type="button" class="btn-primary" id="next1">Continuar</button>
          </div>
        </section>

        <!-- Paso 2: género + resumen -->
        <section class="step-panel" data-panel="2">
          <span class="step-icon">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
          </span>
          <h2>Un último dato (opcional)</h2>
          <p class="step-hint">Ayuda a otros roomies a conocerte mejor — puedes omitirlo</p>

          <div class="onb-select" id="generoField" data-select>
            <button type="button" class="onb-select-trigger" id="generoTrigger" aria-haspopup="listbox" aria-expanded="false">
              <span class="icon">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>
              </span>
              <span class="onb-select-value is-placeholder" data-select-value>Selecciona tu género (opcional)</span>
              <svg class="onb-select-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
            </button>
            <ul class="onb-select-menu" role="listbox" data-select-menu hidden>
              <li class="onb-select-option is-selected" role="option" tabindex="-1" data-value="">Sin especificar</li>
              <li class="onb-select-option" role="option" tabindex="-1" data-value="masculino">Masculino</li>
              <li class="onb-select-option" role="option" tabindex="-1" data-value="femenino">Femenino</li>
              <li class="onb-select-option" role="option" tabindex="-1" data-value="otro">Otro</li>
              <li class="onb-select-option" role="option" tabindex="-1" data-value="prefiero_no_decir">Prefiero no decir</li>
            </ul>
          </div>
          <p class="field-error" id="generoError"></p>

          <ul class="summary-list" id="summaryList">
            <li>
              <span><span class="summary-label">Teléfono:</span> <span class="summary-value" id="summaryTelefono">—</span></span>
              <button type="button" class="summary-edit" data-goto="1">Editar</button>
            </li>
          </ul>

          <p class="form-message" id="formMessage" role="alert"></p>

          <div class="step-nav">
            <button type="button" class="btn-outline" id="back2">Atrás</button>
            <button type="submit" class="btn-primary" id="submitBtn">
              <span class="btn-text">Completar perfil</span>
              <span class="spinner" hidden></span>
            </button>
          </div>
        </section>

      </form>

    </div>
  </main>

  <div class="toast" id="toast"></div>

  <script src="{{ asset('Js/onboarding.js') }}"></script>
</body>
</html>
