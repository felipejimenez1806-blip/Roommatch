{{-- partials/moderador-nav.blade.php — sidebar del panel de moderación.
     Hermano de partials/admin-nav: mismas clases (prf-sidebar, prf-nav...),
     pero solo con lo que un moderador puede hacer. Los botones son
     interruptores de pestaña por JS (moderador.js), igual que en el admin. --}}
@php
  $rolesModNav = auth()->user()?->loadMissing('roles')->roles->pluck('nombre') ?? collect();
@endphp
<aside class="prf-sidebar">
  <p class="prf-sidebar-title">Panel de moderación</p>

  <div class="prf-nav" id="modNav">
    <button class="prf-nav-item active" data-panel="modPanelReportes" type="button">
      <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
      Reportes
      <span class="adm-nav-badge" id="modReportesBadge" hidden>0</span>
    </button>
    <button class="prf-nav-item" data-panel="modPanelPublicaciones" type="button">
      <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
      Publicaciones
    </button>
    <button class="prf-nav-item" data-panel="modPanelRoomies" type="button">
      <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
      Roomies
    </button>
    <button class="prf-nav-item" data-panel="modPanelMensajes" type="button">
      <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M4 6l8 7 8-7"/></svg>
      Mensajes
      <span class="adm-nav-badge" id="modMensajesBadge" hidden>0</span>
    </button>
  </div>

  {{-- "Ingresar como…" para quien tiene más de un rol: abre el mismo modal
       "Cambiar de rol" de nav.js (pide contraseña al pasar a admin). --}}
  @if ($rolesModNav->count() > 1)
    <button class="prf-logout" id="modCambiarRolBtn" type="button" style="margin-bottom:8px;">
      <svg viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
      Cambiar de rol
    </button>
  @endif

  <button class="prf-logout" id="modLogoutBtn" type="button">
    <svg viewBox="0 0 24 24"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
    Cerrar sesión
  </button>
</aside>
