@extends('layouts.app')

@section('title', 'Roommatch – Encuentra tu espacio')

@section('content')

<!-- HERO -->
<div class="hero">
  <img src="https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80" alt="Hero room"/>
  <div class="hero-text">
    <h1>Encuentra tu espacio</h1>
    <p>Mas de 1000 lugares para ti!</p>
  </div>
</div>

<!-- SEARCH BAR -->
<div class="search-wrap">
  <div class="search-bar">
    <input type="text" id="searchInput" placeholder="¿Dónde quieres quedarte? (zona o tipo)"/>
    <button class="search-btn" id="searchBtn">&#8594;</button>
  </div>
</div>

<!-- LUGARES POPULARES -->
<div class="section" id="popularesSection">
  <p class="section-title">Lugares populares</p>
  @include('partials.lugares-populares')
</div>

<!-- RECOMENDADOS -->
<div class="section" style="margin-top:28px;" id="recomSection">
  <div class="section-title-row">
    <p class="section-title">Recomendados</p>
    <p class="results-count" id="resultsCount"></p>
  </div>
  <div class="recom-grid" id="recomGrid">
    <!-- Las tarjetas se generan dinámicamente desde index.js -->
  </div>
</div>

<!-- BÚSQUEDA PERSONALIZADA -->
<div class="custom-search-wrap">
  <a class="btn-custom" href="{{ route('habitaciones') }}">Búsqueda Personalizada</a>
</div>

@endsection

@push('js')
@vite('resources/js/pages/index.js')
@endpush
