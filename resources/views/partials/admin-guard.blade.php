{{--
  partials/admin-guard.blade.php

  Antes: public/Js/admin-guard.js, cargado con <script src> bloqueante.
  Ahora va INLINE a propósito: debe ejecutarse síncronamente en el <head>
  para redirigir ANTES de que se pinte el <body>. Un módulo de Vite es
  diferido (se ejecuta después de pintar) y un <script src> depende de una
  petición de red; inline no tiene ninguno de los dos problemas, y funciona
  igual con `npm run dev` que con el build.

  Va DESPUÉS de partials.app-config (usa window.APP) y antes de cualquier
  @vite.

  No es la protección real de datos (esa es el middleware 'es.admin' sobre
  /api/admin/*). Solo cuida la experiencia: qué shell HTML se pinta y a
  dónde se manda a cada quien.

  Con roles N:N hay que mirar el rol ACTIVO de la sesión
  (roommatch_rol_activo, fijado por "Ingresar como…" / "Cambiar de rol"),
  no si el usuario TIENE el rol admin: un usuario con ambos roles puede
  estar navegando como cliente.

  Subdirectorio: las rutas se comparan SIN el prefijo de la app
  (APP.basePath) y las redirecciones se arman con APP.baseUrl; escribirlas
  como "/admin" a secas apuntaría a la raíz del dominio.
--}}
<script>
(function () {
  const STORAGE_TOKEN_KEY = 'roommatch_token';
  const STORAGE_USER_KEY = 'roommatch_user';
  const STORAGE_ROL_ACTIVO_KEY = 'roommatch_rol_activo';

  const RUTA_ADMIN = '/admin';
  const RUTA_DASHBOARD_CLIENTE = '/dashboard';
  const RUTA_LOGIN = '/login';

  const APP = window.APP;
  const irA = (ruta) => window.location.replace(APP.baseUrl + ruta);

  const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
  const rolActivo = sessionStorage.getItem(STORAGE_ROL_ACTIVO_KEY);
  let usuario = null;
  try {
    usuario = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || 'null');
  } catch (e) {
    usuario = null;
  }

  const pathname = window.location.pathname;
  const ruta = (APP.basePath && pathname.startsWith(APP.basePath))
    ? (pathname.slice(APP.basePath.length) || '/')
    : pathname;
  const enZonaAdmin = ruta === RUTA_ADMIN || ruta.startsWith(RUTA_ADMIN + '/');
  const esAdmin = rolActivo === 'admin';

  if (!token || !usuario) {
    // Sin sesión: si la página exige login, que lo resuelva su propio
    // chequeo. Aquí solo se corta el caso admin, que exige sesión sí o sí.
    if (enZonaAdmin) irA(RUTA_LOGIN);
    return;
  }

  if (enZonaAdmin && !esAdmin) {
    irA(RUTA_DASHBOARD_CLIENTE);
    return;
  }

  if (!enZonaAdmin && esAdmin) {
    irA(RUTA_ADMIN);
  }
})();
</script>
