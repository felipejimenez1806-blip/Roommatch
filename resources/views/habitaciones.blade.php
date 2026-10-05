@extends('layouts.app')

@section('title', 'Roommatch – Habitaciones')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/habitaciones.css') }}" />
@endpush

@section('content')

<div class="rooms-page">

  <!-- SIDEBAR DE FILTROS -->
  <aside class="rooms-filters">
    <a class="rooms-back" href="{{ route('index') }}">&larr; Volver</a>

    <div class="filter-block">
      <label class="filter-label" for="filtroZona">Zona o localidad</label>
      <input type="text" id="filtroZona" placeholder="Ej: Chapinero, Suba..."/>
    </div>

    <div class="filter-block">
      <p class="filter-title">Tipo de espacio</p>
      <label class="check-row"><input type="checkbox" class="filtroTipo" value="Habitación"/> Habitación</label>
      <label class="check-row"><input type="checkbox" class="filtroTipo" value="Apartamento"/> Apartamento</label>
      <label class="check-row"><input type="checkbox" class="filtroTipo" value="Casa"/> Casa</label>
      <label class="check-row"><input type="checkbox" class="filtroTipo" value="Estudio"/> Estudio</label>
    </div>

    <div class="filter-block">
      <p class="filter-title">Precio mensual (COP)</p>
      <div class="price-range-row">
        <input type="number" id="precioMin" placeholder="Mínimo" min="0" step="50000"/>
        <span>—</span>
        <input type="number" id="precioMax" placeholder="Máximo" min="0" step="50000"/>
      </div>
    </div>

    <div class="filter-block">
      <p class="filter-title">Calificación</p>
      <label class="check-row"><input type="radio" name="calificacion" value="0" checked/> Cualquiera</label>
      <label class="check-row"><input type="radio" name="calificacion" value="9"/> 9.0 o más</label>
      <label class="check-row"><input type="radio" name="calificacion" value="8"/> 8.0 o más</label>
      <label class="check-row"><input type="radio" name="calificacion" value="7"/> 7.0 o más</label>
    </div>

    <hr class="filter-divider"/>
    <p class="filter-section-label">Filtros detallados</p>

    <details class="filter-group" open>
      <summary>Espacio</summary>
      <label class="check-row"><input type="checkbox" data-filtro="amueblado"/> Amueblado</label>
      <label class="check-row"><input type="checkbox" data-filtro="bano_privado"/> Baño privado</label>
      <label class="check-row"><input type="checkbox" data-filtro="cocina_compartida"/> Cocina compartida</label>
      <label class="check-row"><input type="checkbox" data-filtro="lavadora"/> Lavadora</label>
      <label class="check-row"><input type="checkbox" data-filtro="secadora"/> Secadora</label>
      <label class="check-row"><input type="checkbox" data-filtro="parqueadero"/> Parqueadero</label>
      <label class="check-row"><input type="checkbox" data-filtro="balcon"/> Balcón</label>
      <label class="check-row"><input type="checkbox" data-filtro="terraza"/> Terraza</label>
    </details>

    <details class="filter-group">
      <summary>Servicios incluidos</summary>
      <label class="check-row"><input type="checkbox" data-filtro="incluye_agua"/> Agua</label>
      <label class="check-row"><input type="checkbox" data-filtro="incluye_luz"/> Luz</label>
      <label class="check-row"><input type="checkbox" data-filtro="incluye_internet"/> Internet</label>
      <label class="check-row"><input type="checkbox" data-filtro="incluye_gas"/> Gas</label>
    </details>

    <details class="filter-group">
      <summary>Convivencia</summary>
      <label class="filter-sublabel" for="filtroAmbiente">Ambiente del hogar</label>
      <select id="filtroAmbiente">
        <option value="">Cualquiera</option>
        <option value="estudiantes">Estudiantes</option>
        <option value="profesionales">Profesionales</option>
        <option value="otro">Otro</option>
      </select>
      <label class="check-row"><input type="checkbox" data-filtro="sin_fumadores" /> Sin fumadores en casa</label>
      <label class="check-row"><input type="checkbox" data-filtro="mascotas_en_casa"/> Ya hay mascotas en casa</label>
    </details>

    <details class="filter-group">
      <summary>Habitación</summary>
      <label class="filter-sublabel" for="filtroTamano">Tamaño</label>
      <select id="filtroTamano">
        <option value="">Cualquiera</option>
        <option value="pequena">Pequeña</option>
        <option value="mediana">Mediana</option>
        <option value="grande">Grande</option>
      </select>
      <label class="filter-sublabel" for="filtroCama">Tipo de cama</label>
      <select id="filtroCama">
        <option value="">Cualquiera</option>
        <option value="sencilla">Sencilla</option>
        <option value="semidoble">Semidoble</option>
        <option value="doble">Doble</option>
        <option value="queen">Queen</option>
        <option value="king">King</option>
      </select>
      <label class="check-row"><input type="checkbox" data-filtro="habitacion_compartida"/> Habitación compartida</label>
    </details>

    <details class="filter-group">
      <summary>Reglas del hogar</summary>
      <label class="check-row"><input type="checkbox" data-filtro="permite_mascotas"/> Permite mascotas</label>
      <label class="check-row"><input type="checkbox" data-filtro="permite_visitas"/> Permite visitas</label>
      <label class="check-row"><input type="checkbox" data-filtro="permite_fumar"/> Permite fumar</label>
      <label class="check-row"><input type="checkbox" data-filtro="permite_fiestas"/> Permite fiestas/reuniones</label>
      <label class="check-row"><input type="checkbox" data-filtro="permite_parejas"/> Permite parejas</label>
    </details>

    <details class="filter-group">
      <summary>Transporte</summary>
      <label class="check-row"><input type="checkbox" data-filtro="cerca_transporte_publico"/> Cerca a transporte público</label>
    </details>

    <details class="filter-group">
      <summary>Seguridad</summary>
      <label class="check-row"><input type="checkbox" data-filtro="porteria"/> Portería</label>
      <label class="check-row"><input type="checkbox" data-filtro="camaras_seguridad"/> Cámaras de seguridad</label>
    </details>

    <details class="filter-group">
      <summary>Trabajo / Estudio</summary>
      <label class="check-row"><input type="checkbox" data-filtro="espacio_trabajo"/> Espacio de trabajo/estudio</label>
    </details>

    <details class="filter-group">
      <summary>Edificio</summary>
      <label class="check-row"><input type="checkbox" data-filtro="ascensor"/> Ascensor</label>
      <label class="check-row"><input type="checkbox" data-filtro="gimnasio"/> Gimnasio</label>
      <label class="check-row"><input type="checkbox" data-filtro="zona_comun"/> Zona común</label>
    </details>

    <details class="filter-group">
      <summary>Servicios cercanos</summary>
      <label class="check-row"><input type="checkbox" data-filtro="cerca_supermercado"/> Supermercado cerca</label>
      <label class="check-row"><input type="checkbox" data-filtro="cerca_universidad"/> Universidad cerca</label>
    </details>

    <button type="button" id="limpiarFiltrosBtn" class="btn-limpiar-filtros">Limpiar filtros</button>
  </aside>

  <!-- RESULTADOS -->
  <main class="rooms-results">
    <div class="rooms-results-header">
      <p class="rooms-results-count" id="roomsResultsCount"></p>
      <div class="rooms-sort">
        <label for="roomsSort">Ordenar por</label>
        <select id="roomsSort">
          <option value="recientes">Más recientes</option>
          <option value="precio-asc">Precio: menor a mayor</option>
          <option value="precio-desc">Precio: mayor a menor</option>
          <option value="rating">Mejor calificados</option>
        </select>
      </div>
    </div>

    <div class="rooms-list" id="roomsList"></div>

    <div class="rooms-pagination" id="roomsPagination"></div>
  </main>
</div>

@endsection

@push('js')
<script src="{{ asset('Js/habitaciones.js') }}"></script>
@endpush
