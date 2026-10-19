@extends('layouts.moderador')

@section('title', 'Roommatch – Panel de moderación')

@push('css')
@vite('resources/css/pages/admin.css')
@endpush

@section('content')

<!-- REPORTES -->
<section class="prf-panel active" id="modPanelReportes">
  <h1 class="prf-title">Reportes</h1>
  <p class="prf-subtitle">Resuelve los reportes de la comunidad. Si la acción necesaria es bloquear a un usuario, escálalo a un administrador.</p>

  <div class="prf-subtabs" id="modReportesFiltros">
    <button class="prf-subtab active" data-filtro="pendiente" type="button">Pendientes</button>
    <button class="prf-subtab" data-filtro="escalado" type="button">Escalados</button>
    <button class="prf-subtab" data-filtro="resuelto" type="button">Resueltos</button>
    <button class="prf-subtab" data-filtro="descartado" type="button">Descartados</button>
    <button class="prf-subtab" data-filtro="todos" type="button">Todos</button>
  </div>

  <div class="adm-reportes-list" id="modReportesList"></div>
  <div class="prf-empty" id="modReportesEmpty" hidden>No hay reportes en esta categoría.</div>
</section>

<!-- PUBLICACIONES -->
<section class="prf-panel" id="modPanelPublicaciones">
  <h1 class="prf-title">Publicaciones</h1>
  <p class="prf-subtitle">Revisa el contenido publicado y baja lo que incumpla las normas. Una publicación bajada se puede restaurar.</p>

  <div class="adm-toolbar">
    <input class="adm-search" id="modPubsSearch" type="text" placeholder="Buscar por título, zona o propietario..." autocomplete="off"/>
    <div class="prf-subtabs" id="modPubsFiltros">
      <button class="prf-subtab active" data-filtro="activas" type="button">Activas</button>
      <button class="prf-subtab" data-filtro="eliminadas" type="button">Bajadas</button>
      <button class="prf-subtab" data-filtro="todas" type="button">Todas</button>
    </div>
  </div>

  <div class="adm-pubs-grid" id="modPubsGrid"></div>
  <div class="prf-empty" id="modPubsEmpty" hidden>No hay publicaciones que coincidan con la búsqueda.</div>
</section>

<!-- ROOMIES -->
<section class="prf-panel" id="modPanelRoomies">
  <h1 class="prf-title">Roomies</h1>
  <p class="prf-subtitle">Revisa los perfiles de roomie publicados y baja los que incumplan las normas. Un perfil bajado se puede restaurar.</p>

  <div class="adm-toolbar">
    <input class="adm-search" id="modRoomiesSearch" type="text" placeholder="Buscar por nombre o zona..." autocomplete="off"/>
    <div class="prf-subtabs" id="modRoomiesFiltros">
      <button class="prf-subtab active" data-filtro="activos" type="button">Activos</button>
      <button class="prf-subtab" data-filtro="eliminados" type="button">Bajados</button>
      <button class="prf-subtab" data-filtro="todos" type="button">Todos</button>
    </div>
  </div>

  <div class="adm-pubs-grid" id="modRoomiesGrid"></div>
  <div class="prf-empty" id="modRoomiesEmpty" hidden>No hay perfiles de roomie que coincidan con la búsqueda.</div>
</section>

<!-- MENSAJES DE CONTACTO -->
<section class="prf-panel" id="modPanelMensajes">
  <h1 class="prf-title">Mensajes de contacto</h1>
  <p class="prf-subtitle">Responde los mensajes enviados desde el formulario de Contáctanos. La respuesta le llega por correo a la persona.</p>

  <div class="prf-subtabs" id="modMensajesFiltros">
    <button class="prf-subtab active" data-filtro="pendiente" type="button">Pendientes</button>
    <button class="prf-subtab" data-filtro="atendido" type="button">Atendidos</button>
    <button class="prf-subtab" data-filtro="todos" type="button">Todos</button>
  </div>

  <div class="adm-reportes-list" id="modMensajesList"></div>
  <div class="prf-empty" id="modMensajesEmpty" hidden>No hay mensajes en esta categoría.</div>
</section>

@endsection

@push('js')
@vite('resources/js/pages/moderador.js')
@endpush
