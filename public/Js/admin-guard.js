/**
 * admin-guard.js
 *
 * Se incluye en <head>, ANTES de cualquier otro <script> y antes de que
 * el <body> se pinte, tanto en layouts.app como en layouts.admin. Evita
 * que un usuario vea (aunque sea un instante) el HTML de la zona que no
 * le corresponde.
 *
 * No es la protección real de datos — esa ya la tienes con el
 * middleware 'es.admin' sobre /api/admin/*. Esto solo cuida la
 * experiencia: qué shell HTML se pinta y a dónde se manda a cada quien.
 *
 * CAMBIO (roles N:N, Fase 4): con roles N:N un usuario puede tener
 * 'cliente' y 'admin' a la vez, así que ya no basta con mirar si el
 * usuario TIENE el rol admin (usuario.roles) — hay que mirar cuál es
 * el rol ACTIVO de la sesión en este momento (el que quedó fijado en
 * el token via "Ingresar como…" o "Cambiar de rol"), porque un usuario
 * con ambos roles puede estar navegando activamente como cliente.
 * Ese rol activo se guarda en su propia clave de sessionStorage
 * (roommatch_rol_activo) por login.js / nav.js, separado del snapshot
 * del usuario (roommatch_user), que sigue trayendo el arreglo completo
 * 'roles' pero no dice cuál está activo ahora mismo.
 */
(function () {
  const STORAGE_TOKEN_KEY = 'roommatch_token';
  const STORAGE_USER_KEY = 'roommatch_user';
  const STORAGE_ROL_ACTIVO_KEY = 'roommatch_rol_activo';

  const RUTA_ADMIN = '/admin';
  const RUTA_DASHBOARD_CLIENTE = '/dashboard';
  const RUTA_LOGIN = '/login';

  const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
  const rolActivo = sessionStorage.getItem(STORAGE_ROL_ACTIVO_KEY);
  let usuario = null;
  try {
    usuario = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || 'null');
  } catch (e) {
    usuario = null;
  }

  const enZonaAdmin = window.location.pathname.startsWith(RUTA_ADMIN);
  const esAdmin = rolActivo === 'admin';

  if (!token || !usuario) {
    // Sin sesión: si la página exige login, que lo resuelva su propio
    // chequeo (igual que hoy). Aquí solo cortamos el caso admin, que sí
    // exige sesión sí o sí.
    if (enZonaAdmin) {
      window.location.replace(RUTA_LOGIN);
    }
    return;
  }

  if (enZonaAdmin && !esAdmin) {
    window.location.replace(RUTA_DASHBOARD_CLIENTE);
    return;
  }

  if (!enZonaAdmin && esAdmin) {
    window.location.replace(RUTA_ADMIN);
  }
})();
