@extends('layouts.app')

@section('title', 'Roommatch – Dashboard')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/dashboard.css') }}" />
@endpush

@section('content')

<!-- HERO -->
<div class="hero">
  <img src="https://images.unsplash.com/photo-1631049307264-da0ec9d70304?w=1200&q=80" alt="Hero room"/>
  <div class="hero-text">
    <h1 id="heroGreeting">Encuentra tu espacio</h1>
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
  <div class="lugares-grid">

    <div class="lugar-card tall" data-zone="Chapinero">
      <img src="https://images.unsplash.com/photo-1618773928121-c32242e63f39?w=600&q=80" alt="Chapinero" style="height:270px"/>
      <span class="lugar-label">Chapinero</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;">
      <div class="lugar-card short" data-zone="Kennedy">
        <img src="https://images.unsplash.com/photo-1560185007-c5ca9d2c014d?w=400&q=80" alt="Kennedy"/>
        <span class="lugar-label">Kennedy</span>
      </div>
      <div class="lugar-card short" data-zone="Santa Fe">
        <img src="https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?w=400&q=80" alt="Santa Fe"/>
        <span class="lugar-label">Santa Fe</span>
      </div>
    </div>

    <div class="lugar-card tall" data-zone="Puente Aranda">
      <img src="https://images.unsplash.com/photo-1584132967334-10e028bd69f7?w=600&q=80" alt="Puente Aranda" style="height:270px"/>
      <span class="lugar-label">Puente Aranda</span>
    </div>

    <div style="display:flex;flex-direction:column;gap:10px;">
      <div class="lugar-card short" data-zone="Suba">
        <img src="https://images.unsplash.com/photo-1611892440504-42a792e24d32?w=400&q=80" alt="Suba"/>
        <span class="lugar-label">Suba</span>
      </div>
      <div class="lugar-card short" data-zone="Teusaquillo">
        <img src="https://images.unsplash.com/photo-1566073771259-6a8506099945?w=400&q=80" alt="Teusaquillo"/>
        <span class="lugar-label">Teusaquillo</span>
      </div>
    </div>

  </div>
</div>

<!-- RECOMENDADOS -->
<div class="section" style="margin-top:28px;" id="recomSection">
  <div class="section-title-row">
    <p class="section-title">Recomendados</p>
    <p class="results-count" id="resultsCount"></p>
  </div>
  <div class="recom-grid" id="recomGrid">
    <!-- Las tarjetas se generan dinámicamente desde dashboard.js -->
  </div>
</div>

<!-- BÚSQUEDA PERSONALIZADA -->
<div class="custom-search-wrap">
  <a class="btn-custom" href="{{ route('habitaciones') }}">Búsqueda Personalizada</a>
</div>

@endsection

@push('js')
<script src="{{ asset('Js/dashboard.js') }}"></script>
@endpush
