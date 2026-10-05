@extends('layouts.admin')

@section('title', 'Roommatch – Panel de administración')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/admin.css') }}" />
@endpush

@section('content')

<!-- RESUMEN + ESTADÍSTICAS -->
<section class="prf-panel active" id="admPanelResumen">
  <h1 class="prf-title">Resumen general</h1>
  <p class="prf-subtitle">Estado actual de la plataforma</p>

  <div class="adm-stats-grid" id="admStatsGrid"></div>

  <div class="adm-chart-card">
    <p class="adm-chart-title">Reportes por día (últimos 14 días)</p>
    <p class="adm-chart-sub">Publicaciones y usuarios/roomies reportados por la comunidad, día a día</p>
    <div id="admGraficaReportes"></div>
  </div>
</section>

<!-- USUARIOS (HU-11) -->
<section class="prf-panel" id="admPanelUsuarios">
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
      <h1 class="prf-title">Usuarios registrados</h1>
      <p class="prf-subtitle">Consulta y modera las cuentas de la plataforma</p>
    </div>
    <a href="/admin/nuevo-administrador" class="adm-row-btn is-primary" style="white-space:nowrap;text-decoration:none;">
      + Crear administrador
    </a>
  </div>

  <div class="adm-toolbar">
    <input class="adm-search" id="admUsuariosSearch" type="text" placeholder="Buscar por nombre o correo..." autocomplete="off" name="adm_buscar_usuario_no_autofill"/>
    <div class="prf-subtabs" id="admUsuariosFiltros">
      <button class="prf-subtab active" data-filtro="todos" type="button">Todos</button>
      <button class="prf-subtab" data-filtro="cliente" type="button">Clientes</button>
      <button class="prf-subtab" data-filtro="admin" type="button">Administradores</button>
      <button class="prf-subtab" data-filtro="bloqueado" type="button">Bloqueados</button>
    </div>
  </div>

  <div class="adm-table-wrap">
    <table class="adm-table" id="admUsuariosTabla">
      <thead>
        <tr><th>Usuario</th><th>Correo</th><th>Tipo</th><th>Teléfono</th><th>Estado</th><th></th></tr>
      </thead>
      <tbody id="admUsuariosBody"></tbody>
    </table>
  </div>
  <div class="prf-empty" id="admUsuariosEmpty" hidden>Aún no hay usuarios registrados.</div>
</section>

<!-- PUBLICACIONES (HU-12) -->
<section class="prf-panel" id="admPanelPublicaciones">
  <h1 class="prf-title">Publicaciones</h1>
  <p class="prf-subtitle">Supervisa el contenido publicado por los usuarios</p>

  <div class="adm-toolbar">
    <input class="adm-search" id="admPubsSearch" type="text" placeholder="Buscar por título, zona o propietario..."/>
    <!-- Con soft delete, "Eliminada" ya no significa "borrada de la BD":
         el registro sigue ahí y se puede restaurar. Estos subtabs dejan
         auditar lo eliminado sin mezclarlo con el catálogo activo. -->
    <div class="prf-subtabs" id="admPubsFiltros">
      <button class="prf-subtab active" data-filtro="activas" type="button">Activas</button>
      <button class="prf-subtab" data-filtro="eliminadas" type="button">Eliminadas</button>
      <button class="prf-subtab" data-filtro="todas" type="button">Todas</button>
    </div>
  </div>

  <div class="adm-pubs-grid" id="admPubsGrid"></div>
  <div class="prf-empty" id="admPubsEmpty" hidden>No hay publicaciones que coincidan con la búsqueda.</div>
</section>

<!-- ROOMIES -->
<section class="prf-panel" id="admPanelRoomies">
  <h1 class="prf-title">Roomies</h1>
  <p class="prf-subtitle">Supervisa los perfiles de roomie publicados por los usuarios</p>

  <div class="adm-toolbar">
    <input class="adm-search" id="admRoomiesSearch" type="text" placeholder="Buscar por nombre o zona..."/>
    <div class="prf-subtabs" id="admRoomiesFiltros">
      <button class="prf-subtab active" data-filtro="activos" type="button">Activos</button>
      <button class="prf-subtab" data-filtro="eliminados" type="button">Eliminados</button>
      <button class="prf-subtab" data-filtro="todos" type="button">Todos</button>
    </div>
  </div>

  <div class="adm-pubs-grid" id="admRoomiesGrid"></div>
  <div class="prf-empty" id="admRoomiesEmpty" hidden>No hay perfiles de roomie que coincidan con la búsqueda.</div>
</section>

<!-- REPORTES (HU-13) -->
<section class="prf-panel" id="admPanelReportes">
  <h1 class="prf-title">Reportes</h1>
  <p class="prf-subtitle">Revisa el contenido y los usuarios reportados por la comunidad</p>

  <div class="prf-subtabs" id="admReportesFiltros">
    <button class="prf-subtab active" data-filtro="pendiente" type="button">Pendientes</button>
    <button class="prf-subtab" data-filtro="resuelto" type="button">Resueltos</button>
    <button class="prf-subtab" data-filtro="descartado" type="button">Descartados</button>
    <button class="prf-subtab" data-filtro="todos" type="button">Todos</button>
  </div>

  <div class="adm-reportes-list" id="admReportesList"></div>
  <div class="prf-empty" id="admReportesEmpty" hidden>No hay reportes en esta categoría.</div>
</section>

<!-- MENSAJES DE CONTACTO -->
<section class="prf-panel" id="admPanelMensajes">
  <h1 class="prf-title">Mensajes de contacto</h1>
  <p class="prf-subtitle">Mensajes enviados desde el formulario de Contáctanos</p>

  <div class="prf-subtabs" id="admMensajesFiltros">
    <button class="prf-subtab active" data-filtro="pendiente" type="button">Pendientes</button>
    <button class="prf-subtab" data-filtro="atendido" type="button">Atendidos</button>
    <button class="prf-subtab" data-filtro="todos" type="button">Todos</button>
  </div>

  <div class="adm-reportes-list" id="admMensajesList"></div>
  <div class="prf-empty" id="admMensajesEmpty" hidden>No hay mensajes en esta categoría.</div>
</section>

@endsection

@push('js')
<script src="{{ asset('Js/admin.js') }}"></script>
@endpush
