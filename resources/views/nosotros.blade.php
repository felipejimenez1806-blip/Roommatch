@extends('layouts.app')

@section('title', 'Roommatch – Nosotros')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('Css/nosotros.css') }}" />
@endpush

@section('content')

<!-- ENCABEZADO -->
<div class="about-hero">
  <h1>Nosotros</h1>
  <p>Un proyecto de estudiantes del SENA que se convirtió en una plataforma real para encontrar espacio y compañía en Bogotá.</p>
</div>

<!-- HISTORIA -->
<div class="section about-story-section">
  <div class="about-story">
    <div class="about-story-text">
      <p class="about-eyebrow">Qué es RoomMatch</p>
      <p>RoomMatch es una plataforma para encontrar dónde vivir y con quién vivir en Bogotá. Por un lado conecta a quienes ofrecen una habitación o un apartamento con quienes lo buscan; por otro, conecta directamente a personas que buscan compañero o compañera de vivienda —tengan o no un espacio propio— según sus hábitos y preferencias de convivencia. Todo desde un mismo lugar: buscar, agendar una visita o una cita, reservar, y calificar la experiencia al final.</p>
    </div>
    <div class="about-story-stats">
      <div class="about-stat">
        <p class="about-stat-num">19</p>
        <p class="about-stat-label">Localidades de Bogotá</p>
      </div>
      <div class="about-stat">
        <p class="about-stat-num">2</p>
        <p class="about-stat-label">Formas de conectar: espacios y roomies</p>
      </div>
      <div class="about-stat">
        <p class="about-stat-num">3</p>
        <p class="about-stat-label">Personas construyendo el proyecto</p>
      </div>
    </div>
  </div>
</div>

<!-- VALORES -->
<div class="section" id="valoresSection">
  <p class="section-title">Lo que nos guía</p>
  <div class="about-values-grid">

    <div class="about-value-card">
      <div class="about-value-icon"><i class="bi bi-shield-check"></i></div>
      <p class="about-value-title">Confianza</p>
      <p class="about-value-text">Cuentas verificadas, calificaciones después de cada reserva o cita, y un sistema de reportes para cuidar a la comunidad.</p>
    </div>

    <div class="about-value-card">
      <div class="about-value-icon"><i class="bi bi-people"></i></div>
      <p class="about-value-title">Comunidad</p>
      <p class="about-value-text">No solo buscamos llenar un espacio: ayudamos a que la convivencia funcione, conectando por hábitos y afinidad.</p>
    </div>

    <div class="about-value-card">
      <div class="about-value-icon"><i class="bi bi-lightning-charge"></i></div>
      <p class="about-value-title">Simplicidad</p>
      <p class="about-value-text">Publicar, buscar, reservar una visita o agendar una cita con un roomie debería tomar minutos, no complicaciones.</p>
    </div>

  </div>
</div>

<!-- EQUIPO -->
<div class="section" id="equipoSection">
  <p class="section-title">El equipo</p>
  <div class="about-team-grid">

    <div class="about-team-card">
      <div class="about-team-avatar">FJ</div>
      <p class="about-team-name">Felipe Jiménez</p>
      <p class="about-team-role">Equipo RoomMatch</p>
    </div>

    <div class="about-team-card">
      <div class="about-team-avatar">SL</div>
      <p class="about-team-name">Samuel López</p>
      <p class="about-team-role">Equipo RoomMatch</p>
    </div>

    <div class="about-team-card">
      <div class="about-team-avatar">DG</div>
      <p class="about-team-name">Diego Gamboa</p>
      <p class="about-team-role">Equipo RoomMatch</p>
    </div>

  </div>
</div>

<!-- CTA -->
<div class="about-cta-wrap">
  <div class="about-cta">
    <div>
      <p class="about-cta-title">¿Tienes una idea o pregunta sobre el proyecto?</p>
      <p class="about-cta-text">Nos encanta escuchar a quienes usan RoomMatch.</p>
    </div>
    {{-- Conectado a la página Contáctanos --}}
    <a class="btn-custom" href="{{ route('contactanos') }}">Contáctanos</a>
  </div>
</div>

@endsection
