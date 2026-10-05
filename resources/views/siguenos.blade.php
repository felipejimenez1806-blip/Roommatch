@extends('layouts.app')

@section('title', 'Roommatch – Síguenos')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('Css/siguenos.css') }}" />
@endpush

@section('content')

<!-- ENCABEZADO -->
<div class="social-hero">
  <div class="social-hero-badge">
    <i class="bi bi-broadcast"></i> Comunidad RoomMatch
  </div>
  <h1>Síguenos</h1>
  <p>Nuevos espacios, tips de convivencia y novedades de RoomMatch, directo en tus redes.</p>
</div>

<!-- REDES -->
<div class="section social-section">
  <div class="social-grid">

    <a class="social-card" href="#" target="_blank" rel="noopener">
      <div class="social-icon social-icon--instagram">
        <i class="bi bi-instagram"></i>
      </div>
      <div class="social-body">
        <div class="social-name-row">
          <p class="social-name">Instagram</p>
          <span class="social-handle">@roommatch.co</span>
        </div>
        <p class="social-desc">Espacios destacados, historias de roomies y detrás de cámaras.</p>
      </div>
      <span class="social-follow">
        Seguir <i class="bi bi-arrow-up-right"></i>
      </span>
    </a>

    <a class="social-card" href="#" target="_blank" rel="noopener">
      <div class="social-icon social-icon--tiktok">
        <i class="bi bi-tiktok"></i>
      </div>
      <div class="social-body">
        <div class="social-name-row">
          <p class="social-name">TikTok</p>
          <span class="social-handle">@roommatch.co</span>
        </div>
        <p class="social-desc">Tips rápidos de convivencia, mudanzas y vida en arriendo compartido.</p>
      </div>
      <span class="social-follow">
        Seguir <i class="bi bi-arrow-up-right"></i>
      </span>
    </a>

    <a class="social-card" href="#" target="_blank" rel="noopener">
      <div class="social-icon social-icon--facebook">
        <i class="bi bi-facebook"></i>
      </div>
      <div class="social-body">
        <div class="social-name-row">
          <p class="social-name">Facebook</p>
          <span class="social-handle">RoomMatch Colombia</span>
        </div>
        <p class="social-desc">Comunidad, avisos importantes y grupos de zonas por ciudad.</p>
      </div>
      <span class="social-follow">
        Seguir <i class="bi bi-arrow-up-right"></i>
      </span>
    </a>

    <a class="social-card" href="#" target="_blank" rel="noopener">
      <div class="social-icon social-icon--linkedin">
        <i class="bi bi-linkedin"></i>
      </div>
      <div class="social-body">
        <div class="social-name-row">
          <p class="social-name">LinkedIn</p>
          <span class="social-handle">RoomMatch</span>
        </div>
        <p class="social-desc">Avances del proyecto y novedades del equipo detrás de la plataforma.</p>
      </div>
      <span class="social-follow">
        Seguir <i class="bi bi-arrow-up-right"></i>
      </span>
    </a>

  </div>
</div>

@endsection
