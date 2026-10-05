{{-- partials/admin-nav.blade.php — sin flechita de "volver": solo hay
     un apartado dentro de admin, así que no tenía a dónde apuntar de
     forma útil. --}}
<aside class="prf-sidebar">
  <p class="prf-sidebar-title">Panel de administración</p>


  <div class="prf-nav" id="admNav">
    <button class="prf-nav-item active" data-panel="admPanelResumen" type="button">
      <svg viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9"/><rect x="14" y="3" width="7" height="5"/><rect x="14" y="12" width="7" height="9"/><rect x="3" y="16" width="7" height="5"/></svg>
      Resumen
    </button>
    <button class="prf-nav-item" data-panel="admPanelUsuarios" type="button">
      <svg viewBox="0 0 24 24"><path d="M20 21v-1a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v1"/><circle cx="12" cy="7" r="4"/></svg>
      Usuarios
    </button>
    <button class="prf-nav-item" data-panel="admPanelPublicaciones" type="button">
      <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
      Publicaciones
    </button>
    <button class="prf-nav-item" data-panel="admPanelRoomies" type="button">
      <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
      Roomies
    </button>
    <button class="prf-nav-item" data-panel="admPanelReportes" type="button">
      <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
      Reportes
      <span class="adm-nav-badge" id="admReportesBadge" hidden>0</span>
    </button>
    <button class="prf-nav-item" data-panel="admPanelMensajes" type="button">
      <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M4 6l8 7 8-7"/></svg>
      Mensajes
      <span class="adm-nav-badge" id="admMensajesBadge" hidden>0</span>
    </button>
  </div>

  <button class="prf-logout" id="admLogoutBtn" type="button">
    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    Cerrar sesión
  </button>
</aside>
