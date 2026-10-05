@extends('layouts.app')

@section('title', 'Roommatch – Mi perfil')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/perfil.css') }}" />
@endpush

@section('content')
<div class="prf-page">

  <!-- SIDEBAR -->
  <aside class="prf-sidebar">
    <a class="prf-back" href="{{ route('dashboard') }}" title="Volver al dashboard">
      <svg viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
    </a>

    <p class="prf-sidebar-title">Ajustes de perfil</p>

    <div class="prf-nav" id="prfNav">
      <button class="prf-nav-item active" data-panel="panelDatos">
        <svg viewBox="0 0 24 24"><path d="M20 21v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"/><circle cx="12" cy="7" r="4"/></svg>
        Datos personales
      </button>
      <button class="prf-nav-item" data-panel="panelNotificaciones">
        <svg viewBox="0 0 24 24"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notificaciones
        <span class="prf-nav-badge" id="prfNotifBadge" hidden>0</span>
      </button>
      <button class="prf-nav-item" data-panel="panelConvivencia">
        <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
        Información convivencial
      </button>
      <button class="prf-nav-item" data-panel="panelFavoritos">
        <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        Favoritos
      </button>
      <button class="prf-nav-item" data-panel="panelReservas">
        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
        Reservas
      </button>
      <button class="prf-nav-item" data-panel="panelCitas">
        <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4.4 3.6-8 8-8s8 3.6 8 8"/></svg>
        Mis citas
      </button>
    </div>

    <a class="prf-nav-item" href="{{ route('mis-publicaciones') }}">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Mis publicaciones
    </a>

    <a class="prf-nav-item prf-nav-cta" href="{{ route('crear-publicacion') }}">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      Crear publicación
    </a>

    <button class="prf-logout" id="logoutBtnSidebar">
      <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
      Cerrar sesión
    </button>
  </aside>

  <!-- CONTENIDO -->
  <main class="prf-content">

    <!-- DATOS PERSONALES -->
    <section class="prf-panel active" id="panelDatos">
      <h1 class="prf-title">Datos personales</h1>
      <p class="prf-subtitle">Edita tus datos personales</p>

      <div class="prf-avatar-row">
        <span class="prf-avatar-big" id="prfAvatarBig">U</span>
        <div class="prf-avatar-actions">
          <label class="prf-photo-btn" for="fotoInput">Cambiar foto</label>
          <input type="file" id="fotoInput" accept="image/png, image/jpeg, image/webp" hidden/>
          <button type="button" class="prf-photo-btn is-remove" id="quitarFotoBtn" hidden>Quitar foto</button>
          <p class="prf-photo-hint" id="fotoError"></p>
        </div>
      </div>

      <div class="prf-field-list" id="datosPersonalesList"></div>
    </section>

    <!-- NOTIFICACIONES -->
    <section class="prf-panel" id="panelNotificaciones">
      <h1 class="prf-title">Notificaciones</h1>
      <p class="prf-subtitle">Avisos sobre tus reportes y otras novedades de tu cuenta</p>
      <div class="prf-list" id="notificacionesList"></div>
    </section>

    <!-- INFORMACIÓN CONVIVENCIAL -->
    <section class="prf-panel" id="panelConvivencia">
      <h1 class="prf-title">Información convivencial</h1>
      <p class="prf-subtitle" id="convivenciaSubtitle">Cuéntanos cómo es tu forma de convivir</p>
      <div class="prf-save-note" id="convivenciaSaveNote">Guardado ✓</div>
      <div class="prf-conv-list" id="convivenciaList"></div>
    </section>

    <!-- FAVORITOS -->
    <section class="prf-panel" id="panelFavoritos">
      <h1 class="prf-title">Favoritos</h1>
      <p class="prf-subtitle">Las publicaciones y roomies que has guardado</p>

      <div class="prf-subtabs">
        <button class="prf-subtab active" data-fav="habitaciones">Habitaciones</button>
        <button class="prf-subtab" data-fav="roomies">Roomies</button>
      </div>

      <div class="prf-fav-grid" id="favHabitacionesGrid"></div>
      <div class="prf-fav-grid" id="favRoomiesGrid" hidden></div>
    </section>

    <!-- RESERVAS -->
    <section class="prf-panel" id="panelReservas">
      <h1 class="prf-title">Reservas</h1>
      <p class="prf-subtitle">Tus solicitudes de visita a habitaciones y apartamentos</p>
      <div class="prf-list" id="reservasList"></div>
    </section>

    <!-- MIS CITAS -->
    <section class="prf-panel" id="panelCitas">
      <h1 class="prf-title">Mis citas</h1>
      <p class="prf-subtitle">Encuentros agendados con roomies para conocerse</p>
      <div class="prf-list" id="citasList"></div>
    </section>

  </main>
</div>
@endsection

@push('js')
<script src="{{ asset('Js/perfil.js') }}"></script>
@endpush
