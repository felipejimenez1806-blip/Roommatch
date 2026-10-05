@extends('layouts.app')

@section('title', 'Roommatch – Contáctanos')

@push('css')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="{{ asset('Css/contactanos.css') }}" />
@endpush

@section('content')

<!-- ENCABEZADO -->
<div class="contact-hero">
  <h1>Contáctanos</h1>
  <p>¿Tienes una pregunta, una idea o un problema con la plataforma? Escríbenos.</p>
</div>

<div class="section contact-section">
  <div class="contact-grid">

    <!-- FORMULARIO -->
    <form class="contact-form" id="contactForm" novalidate>

      <div class="contact-field">
        <label for="contactNombre">Nombre</label>
        <input type="text" id="contactNombre" name="nombre" placeholder="Tu nombre completo" required>
        <span class="contact-error" data-for="contactNombre"></span>
      </div>

      <div class="contact-field">
        <label for="contactCorreo">Correo</label>
        <input type="email" id="contactCorreo" name="correo" placeholder="tucorreo@ejemplo.com" required>
        <span class="contact-error" data-for="contactCorreo"></span>
      </div>

      <div class="contact-field">
        <label for="contactAsunto">Asunto</label>
        <select id="contactAsunto" name="asunto" required>
          <option value="" disabled selected>Selecciona un motivo</option>
          <option value="soporte">Problema con una publicación, reserva o cita</option>
          <option value="cuenta">Problema con mi cuenta</option>
          <option value="reporte">Quiero reportar algo</option>
          <option value="sugerencia">Sugerencia o idea</option>
          <option value="otro">Otro</option>
        </select>
        <span class="contact-error" data-for="contactAsunto"></span>
      </div>

      <div class="contact-field">
        <label for="contactMensaje">Mensaje</label>
        <textarea id="contactMensaje" name="mensaje" rows="5" placeholder="Cuéntanos con detalle qué necesitas…" required></textarea>
        <span class="contact-error" data-for="contactMensaje"></span>
      </div>

      <button type="submit" class="btn-solid contact-submit" id="contactSubmit">Enviar mensaje</button>

      <div class="contact-success" id="contactSuccess">
        <i class="bi bi-check-circle-fill"></i>
        <span>¡Listo! Recibimos tu mensaje y te responderemos pronto.</span>
      </div>

      <div class="contact-general-error" id="contactGeneralError">
        <i class="bi bi-exclamation-circle-fill"></i>
        <span>No pudimos enviar tu mensaje. Intenta de nuevo en unos minutos.</span>
      </div>
    </form>

    <!-- OTRAS FORMAS DE CONTACTO -->
    <aside class="contact-side">

      <div class="contact-side-card">
        <div class="contact-side-icon"><i class="bi bi-envelope"></i></div>
        <p class="contact-side-title">Escríbenos directo</p>
        <a class="contact-side-link" href="mailto:contacto@roommatch.co">contacto@roommatch.co</a>
      </div>

      <div class="contact-side-card">
        <div class="contact-side-icon"><i class="bi bi-share"></i></div>
        <p class="contact-side-title">Síguenos en redes</p>
        <p class="contact-side-text">Novedades y anuncios en tiempo real.</p>
        <a class="contact-side-link" href="{{ route('siguenos') }}">Ver redes sociales</a>
      </div>

      <div class="contact-side-card">
        <div class="contact-side-icon"><i class="bi bi-question-circle"></i></div>
        <p class="contact-side-title">¿Pregunta rápida?</p>
        <p class="contact-side-text">Puede que ya esté resuelta en Ayuda o Preguntas frecuentes.</p>
        <a class="contact-side-link" href="{{ route('ayuda') }}">Ir a Ayuda</a>
        <a class="contact-side-link" href="{{ route('faq') }}">Ver FAQ</a>
      </div>

    </aside>

  </div>
</div>

@endsection

@push('js')
<script src="{{ asset('Js/contactanos.js') }}"></script>
@endpush
