@extends('layouts.app')

@section('title', 'Roommatch – Preguntas frecuentes')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/faq.css') }}" />
@endpush

@section('content')

<!-- ENCABEZADO -->
<div class="faq-hero">
  <h1>Preguntas frecuentes</h1>
  <p>Lo que más nos preguntan sobre cómo funciona RoomMatch.</p>
</div>

<!-- LISTA DE PREGUNTAS -->
<div class="section faq-section">
  <div class="faq-list" id="faqList">

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿Qué es RoomMatch?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Es una plataforma para encontrar habitaciones y espacios en arriendo en Bogotá, y también para conectarte con posibles roomies según hábitos y preferencias de convivencia.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿RoomMatch tiene algún costo para usarlo?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Buscar, publicar, reservar visitas y agendar citas con roomies no tiene costo. Cualquier cambio en este modelo se anunciará con claridad dentro de la plataforma.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿RoomMatch administra el pago del arriendo?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>No. RoomMatch conecta a quienes ofrecen un espacio con quienes lo buscan; el acuerdo de arriendo y el pago se gestionan directamente entre las partes.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿En qué ciudades está disponible?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Por ahora estamos enfocados en Bogotá, cubriendo zonas como Chapinero, Kennedy, Santa Fe, Puente Aranda, Suba y Teusaquillo.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿Cómo verifican a los usuarios?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Toda cuenta pasa por un registro con datos personales, y las reservas y citas quedan asociadas a esa cuenta. Además, el sistema de calificaciones y reportes ayuda a mantener la confianza dentro de la comunidad.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿Puedo publicar un espacio y a la vez buscar uno?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Sí. Con una sola cuenta puedes publicar tus propios espacios, buscar otros, crear un perfil de roomie y gestionar todo desde tu panel.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿Cuál es la diferencia entre reservar un espacio y agendar una cita con un roomie?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Reservar es para conocer un espacio disponible (una habitación, un apartamento). Agendar una cita es para conocer en persona a alguien que busca compañero(a) de vivienda, tenga o no un espacio propio.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿Qué hago si tengo un problema con otro usuario?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Desde la reserva o cita correspondiente puedes usar el botón "Reportar" para que nuestro equipo revise el caso. Si necesitas algo adicional, puedes escribirnos por Contáctanos.</p>
      </div>
    </div>

    <div class="faq-item">
      <button class="faq-question" type="button">
        ¿Mis datos están seguros?
        <svg class="faq-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="faq-answer">
        <p>Sí, tus credenciales y datos de sesión se manejan de forma segura y solo se usan para el funcionamiento de la plataforma, nunca se comparten con terceros sin tu consentimiento.</p>
      </div>
    </div>

  </div>
</div>

<!-- CTA -->
<div class="faq-cta-wrap">
  <div class="faq-cta">
    <div>
      <p class="faq-cta-title">¿Buscas algo más específico o técnico?</p>
      <p class="faq-cta-text">En Ayuda tenemos guías paso a paso por cada sección de RoomMatch.</p>
    </div>
    <a class="btn-custom" href="{{ route('ayuda') }}">Ir a Ayuda</a>
  </div>
</div>

@endsection

@push('js')
<script src="{{ asset('Js/faq.js') }}"></script>
@endpush
