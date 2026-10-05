<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Roommatch | Términos y condiciones</title>
  <link rel="stylesheet" href="{{ asset('Css/terminos.css') }}">
</head>
<body>

  <header class="terminos-header">
    <a href="{{ route('index') }}" class="terminos-logo-link">
      <img src="{{ asset('Img/logo_roommatch.png') }}" alt="Roommatch" class="terminos-logo">
    </a>
  </header>

  <main class="terminos-wrapper">
    <div class="terminos-card">

      <h1>Términos y condiciones</h1>
      <p class="terminos-actualizado">Última actualización: septiembre de 2026</p>

      <p>
        Bienvenido a Roommatch. Al crear una cuenta o usar la plataforma aceptas los
        términos descritos en este documento. Léelos con atención antes de continuar.
      </p>

      <h2>1. ¿Qué es Roommatch?</h2>
      <p>
        Roommatch es una plataforma que conecta a personas que ofrecen habitaciones o
        espacios de vivienda con personas que buscan un lugar para vivir
        o un compañero de vivienda. Roommatch actúa únicamente como
        intermediario tecnológico: no somos propietarios, arrendadores ni parte de los
        acuerdos de arrendamiento o convivencia que se concreten entre usuarios.
      </p>

      <h2>2. Registro y cuenta</h2>
      <p>
        Para usar Roommatch debes registrarte con información veraz y completa. Eres
        responsable de mantener la confidencialidad de tu contraseña y de toda la
        actividad que ocurra bajo tu cuenta. Debes notificarnos de inmediato si
        sospechas un uso no autorizado de tu cuenta.
      </p>
      <p>
        Nos reservamos el derecho de suspender o bloquear cuentas que incumplan estos
        términos, que presenten actividad fraudulenta, o por motivos de seguridad
        (por ejemplo, múltiples intentos fallidos de inicio de sesión).
      </p>

      <h2>3. Publicaciones y perfiles</h2>
      <p>
        Los usuarios son responsables de que la información, fotos y condiciones de
        sus publicaciones sean veraces y estén actualizadas. Los buscadores son
        responsables de la información que incluyen en su perfil de roomie. Roommatch
        puede eliminar publicaciones o perfiles que infrinjan la ley, contengan
        información falsa o violen estos términos.
      </p>

      <h2>4. Reservas y citas</h2>
      <p>
        Las reservas de habitaciones y las citas con roomies agendadas a través de la
        plataforma son acuerdos entre los usuarios involucrados. Roommatch facilita el
        proceso de agendamiento, pero no garantiza la disponibilidad, condiciones
        finales, ni el cumplimiento de lo pactado entre las partes.
      </p>

      <h2>5. Conducta prohibida</h2>
      <p>Al usar Roommatch, te comprometes a NO:</p>
      <ul>
        <li>Publicar información falsa, engañosa o discriminatoria.</li>
        <li>Acosar, amenazar o discriminar a otros usuarios.</li>
        <li>Usar la plataforma para actividades fraudulentas o ilegales.</li>
        <li>Intentar acceder a cuentas o datos de otros usuarios sin autorización.</li>
      </ul>
      <p>
        El incumplimiento puede resultar en la suspensión o eliminación de tu cuenta,
        sin perjuicio de las acciones legales que correspondan.
      </p>

      <h2>6. Datos personales</h2>
      <p>
        El tratamiento de tus datos personales se rige por la Ley 1581 de 2012 y demás
        normas colombianas aplicables sobre protección de datos y habeas data.
        Usamos tu información únicamente para operar la plataforma, verificar tu
        identidad y mejorar el servicio. No vendemos tus datos a terceros.
      </p>

      <h2>7. Propiedad intelectual</h2>
      <p>
        El nombre, logo, diseño e interfaz de Roommatch son propiedad de sus
        creadores. El contenido que subas (fotos, descripciones, etc.) sigue siendo
        tuyo, pero nos otorgas licencia para mostrarlo dentro de la plataforma con el
        único fin de operar el servicio.
      </p>

      <h2>8. Limitación de responsabilidad</h2>
      <p>
        Roommatch no garantiza la veracidad absoluta de las publicaciones ni la
        idoneidad de los usuarios como arrendadores, arrendatarios o roomies.
        Recomendamos siempre verificar en persona la información antes de tomar
        decisiones de vivienda o firmar acuerdos con otros usuarios.
      </p>

      <h2>9. Cambios a estos términos</h2>
      <p>
        Podemos actualizar estos términos en cualquier momento. Los cambios
        importantes se notificarán a través de la plataforma. El uso continuado de
        Roommatch después de una actualización implica la aceptación de los nuevos
        términos.
      </p>

      <h2>10. Contacto</h2>
      <p>
        Si tienes preguntas sobre estos términos, puedes escribirnos desde la sección
        <a href="{{ route('contactanos') }}">Contáctanos</a> o a
        <a href="mailto:roommatch.soporte2026@gmail.com">roommatch.soporte2026@gmail.com</a>.
      </p>

      <a href="{{ route('signup') }}" class="terminos-volver">Volver al registro</a>
    </div>
  </main>

  <footer class="terminos-footer">
    <p>© Roommatch 2026 &nbsp;|&nbsp; <a href="{{ route('ayuda') }}">ayuda</a> &nbsp;|&nbsp; <a href="{{ route('faq') }}">faq</a> &nbsp;|&nbsp; <a href="{{ route('terminos') }}">términos y condiciones</a></p>
  </footer>

</body>
</html>
