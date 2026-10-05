@extends('layouts.app')

@section('title', 'Roommatch – Ayuda')

@push('css')
<link rel="stylesheet" href="{{ asset('Css/ayuda.css') }}" />
@endpush

@section('content')

<!-- ENCABEZADO -->
<div class="help-hero">
  <h1>Centro de ayuda</h1>
  <p>Busca por palabra clave o revisa las preguntas más frecuentes por tema.</p>
  <div class="search-wrap help-search-wrap">
    <div class="search-bar">
      <input type="text" id="helpSearchInput" placeholder="Ej: cómo reservar, verificar mi cuenta, cancelar cita…"/>
      <button class="search-btn" id="helpSearchBtn" type="button">&#8594;</button>
    </div>
  </div>
</div>

<!-- CATEGORÍAS -->
<div class="section" id="categoriasSection">
  <p class="section-title">Temas</p>
  <div class="help-cats" id="helpCats">
    <a href="#cat-cuenta" class="help-cat-chip" data-cat="cat-cuenta">Cuenta y verificación</a>
    <a href="#cat-publicar" class="help-cat-chip" data-cat="cat-publicar">Publicar un espacio</a>
    <a href="#cat-buscar" class="help-cat-chip" data-cat="cat-buscar">Buscar y reservar</a>
    <a href="#cat-roomie" class="help-cat-chip" data-cat="cat-roomie">Perfil de roomie y citas</a>
    <a href="#cat-favoritos" class="help-cat-chip" data-cat="cat-favoritos">Favoritos y notificaciones</a>
    <a href="#cat-seguridad" class="help-cat-chip" data-cat="cat-seguridad">Seguridad y reportes</a>
  </div>
</div>

<!-- PREGUNTAS FRECUENTES -->
<div class="section" style="margin-top:8px;" id="faqSection">

  <div class="help-group" id="cat-cuenta">
    <p class="help-group-title">Cuenta y verificación</p>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo creo una cuenta en RoomMatch?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Ve al botón "Registrarse" en la parte superior, completa tus datos básicos y confirma tu correo. Al iniciar sesión por primera vez te pediremos completar tu perfil (onboarding) antes de publicar o reservar.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        Olvidé mi contraseña, ¿qué hago?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>En la pantalla de inicio de sesión encuentras la opción "¿Olvidaste tu contraseña?". Te enviaremos un enlace a tu correo registrado para crear una nueva.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Puedo editar mis datos personales luego de registrarme?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Sí. Desde tu perfil puedes actualizar tus datos personales, tu foto y tus preferencias de convivencia cuando quieras.</p>
      </div>
    </div>
  </div>

  <div class="help-group" id="cat-publicar">
    <p class="help-group-title">Publicar un espacio</p>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo publico una habitación o espacio disponible?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Entra a "Crear publicación" en el menú de usuario. El proceso es de varios pasos: datos del espacio, fotos, precio y condiciones. Puedes guardar y continuar más tarde desde "Mis publicaciones".</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Puedo editar o desactivar una publicación ya creada?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Sí, desde "Mis publicaciones" puedes editar los datos, cambiar el estado (activa/inactiva) o eliminarla por completo.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cuántas fotos puedo subir por publicación?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Recomendamos entre 3 y 8 fotos claras del espacio. Entre más completas sean, más confianza generan con quienes buscan alojamiento.</p>
      </div>
    </div>
  </div>

  <div class="help-group" id="cat-buscar">
    <p class="help-group-title">Buscar y reservar</p>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo busco espacios por zona o tipo?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Usa la barra de búsqueda del inicio o entra a "Búsqueda personalizada" en Habitaciones, donde puedes filtrar por zona, precio y tipo de alojamiento.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo reservo una visita o el espacio?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Entra a la publicación que te interesa y elige un horario disponible para reservar. Recibirás una confirmación y podrás verla en "Mis reservas".</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Puedo cancelar una reserva?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Sí, desde "Mis reservas" puedes cancelarla. Te recomendamos hacerlo con anticipación para no afectar tu historial dentro de la plataforma.</p>
      </div>
    </div>
  </div>

  <div class="help-group" id="cat-roomie">
    <p class="help-group-title">Perfil de roomie y citas</p>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Qué es el perfil de roomie?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Es un perfil aparte de tu cuenta donde cuentas tus hábitos de convivencia para que otras personas te encuentren si buscan compañero(a) de vivienda, no necesariamente un espacio.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo agendo una cita con un posible roomie?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Desde el perfil de la persona en la sección "Roomies", elige un horario disponible para agendar la cita. Podrás verla en "Mis citas".</p>
      </div>
    </div>
  </div>

  <div class="help-group" id="cat-favoritos">
    <p class="help-group-title">Favoritos y notificaciones</p>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo guardo un espacio o roomie en favoritos?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Toca el ícono de corazón en la tarjeta del espacio o del roomie. Puedes verlos todos después en tu perfil, en la sección Favoritos.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Para qué sirve la campanita de notificaciones?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Ahí te avisamos sobre el estado de tus reservas y citas, mensajes relevantes y novedades sobre tus publicaciones.</p>
      </div>
    </div>
  </div>

  <div class="help-group" id="cat-seguridad">
    <p class="help-group-title">Seguridad y reportes</p>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo reporto una publicación o un usuario?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>En una reserva o cita ya realizada encontrarás el botón "Reportar". Cuéntanos qué pasó y nuestro equipo revisará el caso.</p>
      </div>
    </div>

    <div class="help-item">
      <button class="help-question" type="button">
        ¿Cómo funcionan las calificaciones?
        <svg class="help-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
      </button>
      <div class="help-answer">
        <p>Al finalizar una estadía o cita confirmada, puedes calificar la experiencia. Esto ayuda a que la comunidad tenga más confianza al elegir con quién alojarse.</p>
      </div>
    </div>
  </div>

  <p class="help-no-results" id="helpNoResults" style="display:none;">
    No encontramos preguntas relacionadas con tu búsqueda.
  </p>

</div>

<!-- CTA CONTACTO -->
<div class="help-cta-wrap">
  <div class="help-cta">
    <div>
      <p class="help-cta-title">¿No encontraste lo que buscabas?</p>
      <p class="help-cta-text">Escríbenos y te ayudamos personalmente.</p>
    </div>
    {{-- Conectado a la página Contáctanos --}}
    <a class="btn-custom" href="{{ route('contactanos') }}">Contáctanos</a>
  </div>
</div>

@endsection

@push('js')
<script src="{{ asset('Js/ayuda.js') }}"></script>
@endpush
