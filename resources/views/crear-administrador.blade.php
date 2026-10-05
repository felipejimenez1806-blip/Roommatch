@extends('layouts.admin-bare')

@section('title', 'Roommatch – Crear administrador')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/crear-administrador.css') }}" />
@endpush

@section('content')

<div class="admcta-page">
  <div class="admcta-card">

    <a href="/admin" class="admcta-back">
      <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
      Volver al panel de administración
    </a>

    <div class="admcta-header">
      <span class="admcta-badge">
        <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        Acceso restringido
      </span>
      <h1>Crear administrador</h1>
      <p>Esta cuenta tendrá acceso completo al panel de administración de Roommatch.</p>
    </div>

    <form id="crearAdminForm" autocomplete="off" novalidate>

      <!-- Campos señuelo: absorben el autocompletado del navegador para
           que no rellene 'correo'/'password' con credenciales guardadas
           de otra cuenta. Nunca deben tener valor real. -->
      <input class="admcta-decoy" type="text" name="fakeusernameremembered" tabindex="-1" aria-hidden="true" autocomplete="off">
      <input class="admcta-decoy" type="password" name="fakepasswordremembered" tabindex="-1" aria-hidden="true" autocomplete="new-password">

      <div class="admcta-section">
        <p class="admcta-section-title">Información básica</p>

        <div class="admcta-field" id="nombreGroupWrap">
          <label for="nombre">Nombre completo</label>
          <div class="admcta-input" id="nombreGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><path d="M20 21v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"/><circle cx="12" cy="7" r="4"/></svg>
            </span>
            <input type="text" id="nombre" name="admin_contacto_nombre" placeholder="Ej. Laura Gómez"
              autocomplete="off" readonly onfocus="this.removeAttribute('readonly')">
          </div>
          <p class="admcta-error" id="nombreError"></p>
        </div>

        <div class="admcta-field" id="correoGroupWrap">
          <label for="correo">Correo electrónico</label>
          <div class="admcta-input" id="correoGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            </span>
            <input type="email" id="correo" name="admin_contacto_correo" placeholder="correo@roommatch.com"
              autocomplete="off" readonly onfocus="this.removeAttribute('readonly')">
          </div>
          <p class="admcta-error" id="correoError"></p>
        </div>

        <div class="admcta-field" id="areaGroupWrap">
          <label for="area">Área en la que trabaja</label>
          <div class="admcta-input" id="areaGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
            </span>
            <input type="text" id="area" name="admin_area_trabajo" placeholder="Ej. Soporte, Seguridad" autocomplete="off">
          </div>
          <p class="admcta-error" id="areaError"></p>
        </div>

        <div class="admcta-field" id="cargoGroupWrap">
          <label for="cargo">Cargo</label>
          <div class="admcta-input" id="cargoGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
            </span>
            <input type="text" id="cargo" name="admin_cargo" placeholder="Ej. Coordinador de soporte" autocomplete="off">
          </div>
          <p class="admcta-error" id="cargoError"></p>
        </div>
      </div>

      <div class="admcta-section">
        <p class="admcta-section-title">Seguridad de la cuenta</p>

        <div class="admcta-field" id="passwordGroupWrap">
          <label for="password">Contraseña de la nueva cuenta</label>
          <div class="admcta-input" id="passwordGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </span>
            <input type="password" id="password" name="admin_password_nueva" placeholder="Contraseña"
              autocomplete="new-password" readonly onfocus="this.removeAttribute('readonly')">
            <button type="button" class="admcta-toggle" id="togglePassword">Mostrar</button>
          </div>
          <p class="admcta-error" id="passwordError"></p>
          <ul class="admcta-checklist" id="passwordChecklist">
            <li data-rule="length">Mínimo 10 caracteres</li>
            <li data-rule="upper">Una letra mayúscula</li>
            <li data-rule="lower">Una letra minúscula</li>
            <li data-rule="number">Un número</li>
            <li data-rule="special">Un carácter especial (!@#$%...)</li>
            <li data-rule="noName">No debe contener el nombre ni el correo</li>
          </ul>
        </div>

        <div class="admcta-field" id="confirmPasswordGroupWrap" style="margin-top:16px;">
          <label for="confirmPassword">Confirmar contraseña</label>
          <div class="admcta-input" id="confirmPasswordGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
            </span>
            <input type="password" id="confirmPassword" name="admin_password_confirmar" placeholder="Repite la contraseña"
              autocomplete="new-password">
            <button type="button" class="admcta-toggle" id="toggleConfirmPassword">Mostrar</button>
          </div>
          <p class="admcta-error" id="confirmPasswordError"></p>
        </div>
      </div>

      <div class="admcta-section admcta-section-confirm">
        <p class="admcta-section-title">Confirma que eres tú</p>
        <div class="admcta-field" id="passwordActualGroupWrap">
          <label for="passwordActual">Tu contraseña actual</label>
          <div class="admcta-input" id="passwordActualGroup">
            <span class="admcta-icon">
              <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            </span>
            <input type="password" id="passwordActual" name="admin_password_propia" placeholder="Tu contraseña"
              autocomplete="off" readonly onfocus="this.removeAttribute('readonly')">
            <button type="button" class="admcta-toggle" id="togglePasswordActual">Mostrar</button>
          </div>
          <p class="admcta-error" id="passwordActualError"></p>
        </div>
      </div>

      <p class="form-message" id="formMessage" role="alert"></p>

      <button type="submit" class="admcta-submit" id="submitBtn">
        <span class="btn-text">Crear administrador</span>
        <span class="spinner" hidden></span>
      </button>

    </form>
  </div>
</div>

<div class="toast" id="toast"></div>

@endsection

@push('js')
<script src="{{ asset('Js/crear-administrador.js') }}"></script>
@endpush
