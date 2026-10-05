@extends('layouts.app')

@section('title', 'Roommatch – Roomies')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/roomies.css') }}" />
@endpush

@section('content')

<div class="roomies-page">

  <!-- SIDEBAR DE FILTROS -->
  <aside class="roomies-filters">
    <a class="roomies-back" href="{{ route('index') }}">&larr; Volver</a>

    <div class="filter-block">
      <label class="filter-label" for="filtroZonaRoomie">Zona donde busca</label>
      <input type="text" id="filtroZonaRoomie" placeholder="Ej: Chapinero, Suba..."/>
    </div>

    <div class="filter-block">
      <p class="filter-title">Ocupación</p>
      <label class="check-row"><input type="checkbox" class="filtroOcupacion" value="Estudiante"/> Estudiante</label>
      <label class="check-row"><input type="checkbox" class="filtroOcupacion" value="Profesional"/> Profesional</label>
      <label class="check-row"><input type="checkbox" class="filtroOcupacion" value="Freelance"/> Freelance</label>
      <label class="check-row"><input type="checkbox" class="filtroOcupacion" value="Otro"/> Otro</label>
    </div>

    <div class="filter-block">
      <p class="filter-title">Género</p>
      <label class="check-row"><input type="checkbox" class="filtroGenero" value="Mujer"/> Mujer</label>
      <label class="check-row"><input type="checkbox" class="filtroGenero" value="Hombre"/> Hombre</label>
      <label class="check-row"><input type="checkbox" class="filtroGenero" value="Otro"/> Otro</label>
    </div>

    <div class="filter-block">
      <p class="filter-title">Presupuesto mensual (COP)</p>
      <div class="price-range-row">
        <input type="number" id="presupuestoMin" placeholder="Mínimo" min="0" step="50000"/>
        <span>—</span>
        <input type="number" id="presupuestoMax" placeholder="Máximo" min="0" step="50000"/>
      </div>
    </div>

    <div class="filter-block">
      <p class="filter-title">Calificación</p>
      <label class="check-row"><input type="radio" name="calificacionRoomie" value="0" checked/> Cualquiera</label>
      <label class="check-row"><input type="radio" name="calificacionRoomie" value="9"/> 9.0 o más</label>
      <label class="check-row"><input type="radio" name="calificacionRoomie" value="8"/> 8.0 o más</label>
      <label class="check-row"><input type="radio" name="calificacionRoomie" value="7"/> 7.0 o más</label>
    </div>

    <hr class="filter-divider"/>
    <p class="filter-section-label">Filtros detallados</p>

    <details class="filter-group" open>
      <summary>Estilo de vida</summary>
      <label class="check-row"><input type="checkbox" data-filtro="fumador"/> Fumador/a</label>
      <label class="check-row"><input type="checkbox" data-filtro="tiene_mascota"/> Tiene mascota propia</label>
      <label class="check-row"><input type="checkbox" data-filtro="ordenado"/> Ordenado/a</label>
      <label class="check-row"><input type="checkbox" data-filtro="sociable"/> Sociable</label>
      <label class="check-row"><input type="checkbox" data-filtro="madrugador"/> Madrugador/a</label>
      <label class="check-row"><input type="checkbox" data-filtro="trasnochador"/> Trasnochador/a</label>
      <label class="check-row"><input type="checkbox" data-filtro="fiestero"/> Le gustan las reuniones/fiestas</label>
    </details>

    <details class="filter-group">
      <summary>Convivencia</summary>
      <label class="filter-sublabel" for="filtroAmbienteRoomie">Ambiente que busca</label>
      <select id="filtroAmbienteRoomie">
        <option value="">Cualquiera</option>
        <option value="estudiantes">Estudiantes</option>
        <option value="profesionales">Profesionales</option>
        <option value="otro">Otro</option>
      </select>
      <label class="check-row"><input type="checkbox" data-filtro="acepta_mascotas"/> Acepta mascotas en casa</label>
      <label class="check-row"><input type="checkbox" data-filtro="acepta_fumadores"/> Acepta convivir con fumadores</label>
      <label class="check-row"><input type="checkbox" data-filtro="acepta_visitas"/> Acepta visitas frecuentes</label>
      <label class="check-row"><input type="checkbox" data-filtro="acepta_parejas"/> Acepta convivir con parejas</label>
    </details>

    <details class="filter-group">
      <summary>Rutina y ocupación</summary>
      <label class="filter-sublabel" for="filtroHorario">Horario habitual</label>
      <select id="filtroHorario">
        <option value="">Cualquiera</option>
        <option value="diurno">Diurno</option>
        <option value="nocturno">Nocturno</option>
        <option value="mixto">Mixto</option>
      </select>
      <label class="check-row"><input type="checkbox" data-filtro="trabaja_desde_casa"/> Trabaja/estudia desde casa</label>
      <label class="check-row"><input type="checkbox" data-filtro="viaja_frecuentemente"/> Viaja frecuentemente</label>
    </details>

    <details class="filter-group">
      <summary>Disponibilidad</summary>
      <label class="filter-sublabel" for="filtroTiempoBusqueda">Tiempo de estadía buscado</label>
      <select id="filtroTiempoBusqueda">
        <option value="">Cualquiera</option>
        <option value="corto">Corto plazo</option>
        <option value="largo">Largo plazo</option>
      </select>
      <label class="filter-sublabel" for="filtroMudanza">Fecha de mudanza</label>
      <select id="filtroMudanza">
        <option value="">Cualquiera</option>
        <option value="inmediata">Inmediata</option>
        <option value="1mes">En un mes</option>
        <option value="flexible">Flexible</option>
      </select>
    </details>

    <details class="filter-group">
      <summary>Preferencias del hogar</summary>
      <label class="check-row"><input type="checkbox" data-filtro="quiere_amueblado"/> Busca lugar amueblado</label>
      <label class="check-row"><input type="checkbox" data-filtro="quiere_bano_privado"/> Busca baño privado</label>
      <label class="check-row"><input type="checkbox" data-filtro="quiere_parqueadero"/> Busca parqueadero</label>
      <label class="check-row"><input type="checkbox" data-filtro="cerca_universidad"/> Cerca a universidad</label>
      <label class="check-row"><input type="checkbox" data-filtro="cerca_transporte_publico"/> Cerca a transporte público</label>
    </details>

    <details class="filter-group">
      <summary>Otros</summary>
      <label class="check-row"><input type="checkbox" data-filtro="tiene_vehiculo"/> Tiene vehículo propio</label>
      <label class="check-row"><input type="checkbox" data-filtro="comparte_gastos"/> Dispuesto/a a compartir gastos comunes</label>
      <label class="check-row"><input type="checkbox" data-filtro="referencias_verificadas"/> Referencias verificadas</label>
    </details>

    <button type="button" id="limpiarFiltrosRoomieBtn" class="btn-limpiar-filtros">Limpiar filtros</button>
  </aside>

  <!-- RESULTADOS -->
  <main class="roomies-results">
    <div class="roomies-results-header">
      <p class="roomies-results-count" id="roomiesResultsCount"></p>
      <div class="roomies-sort">
        <label for="roomiesSort">Ordenar por</label>
        <select id="roomiesSort">
          <option value="recientes">Más recientes</option>
          <option value="presupuesto-asc">Presupuesto: menor a mayor</option>
          <option value="presupuesto-desc">Presupuesto: mayor a menor</option>
          <option value="rating">Mejor calificados</option>
        </select>
      </div>
    </div>

    <div class="roomies-list" id="roomiesList"></div>

    <div class="roomies-pagination" id="roomiesPagination"></div>
  </main>
</div>

@endsection

@push('js')
<script src="{{ asset('Js/roomies.js') }}"></script>
@endpush
