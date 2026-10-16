/**
 * core/auth.js
 *
 * ÚNICO lugar del frontend que sabe CÓMO viaja y se identifica la sesión.
 *
 * Sesión por cookie de Laravel (HttpOnly) + token CSRF:
 *  - La identidad vive en el servidor. JavaScript NUNCA ve la cookie de
 *    sesión ni un token de acceso, así que un XSS ya no puede robarlos.
 *  - El servidor entrega quién es el usuario en cada página, dentro de
 *    window.APP (partials/app-config.blade.php): user, rolActivo y, si el
 *    login quedó a medias, rolesPendientes.
 *  - Las peticiones a /api llevan la cookie solas (mismo origen) y este
 *    módulo añade el header X-CSRF-TOKEN, leído del <meta name="csrf-token">.
 *  - Quién puede ver qué página lo decide el servidor con middleware
 *    (auth, zona.cliente, zona.admin): requerirSesion() es solo una red de
 *    seguridad.
 *
 * Las páginas no deben tocar window.APP ni armar headers por su cuenta.
 */
import { API_BASE, appUrl } from "./config.js";

// Copia en memoria del usuario que renderizó el servidor. Se actualiza con
// actualizarUsuario() cuando la página edita el perfil.
let usuarioEnMemoria = window.APP?.user ?? null;
let rolActivoEnMemoria = window.APP?.rolActivo ?? null;

// ============================================================
// LECTURA DE LA SESIÓN
// ============================================================

/** Usuario con sesión (Usuario::paraFrontend()), o null si no hay sesión. */
export function obtenerUsuario() {
  return usuarioEnMemoria;
}

/** Rol con el que se inició la sesión actual: "cliente" | "moderador" | "admin" | null. */
export function obtenerRolActivo() {
  return rolActivoEnMemoria;
}

/** true si la página la sirvió el servidor con una sesión iniciada. */
export function haySesion() {
  return usuarioEnMemoria !== null;
}

/** Roles entre los que debe elegir un login que quedó a medias (OAuth). */
export function obtenerRolesPendientes() {
  return window.APP?.rolesPendientes ?? [];
}

/**
 * ¿El rol activo tiene este permiso? (p. ej. tienePermiso("bloquear-usuarios")).
 * Solo para mostrar u ocultar controles: el servidor valida cada acción.
 */
export function tienePermiso(permiso) {
  return (window.APP?.permisos ?? []).includes(permiso);
}

// ============================================================
// ESCRITURA (solo en memoria: la verdad está en el servidor)
// ============================================================

/**
 * Tras login, cambio de rol o registro. El servidor ya abrió o modificó la
 * sesión; esto solo deja la copia en memoria al día por si la página la lee
 * antes de navegar. El token ya no existe y se ignora si llega.
 */
export function guardarSesion({ usuario, rolActivo }) {
  usuarioEnMemoria = usuario ?? usuarioEnMemoria;
  rolActivoEnMemoria = rolActivo ?? rolActivoEnMemoria;
}

/** Actualiza la copia en memoria del usuario (tras editar perfil, onboarding). */
export function actualizarUsuario(usuario) {
  usuarioEnMemoria = usuario;
}

/** Olvida la sesión en memoria. No avisa al servidor: para eso está cerrarSesion(). */
export function limpiarSesion() {
  usuarioEnMemoria = null;
  rolActivoEnMemoria = null;
}

// ============================================================
// PROTECCIÓN DE PÁGINAS
// ============================================================

/**
 * Red de seguridad en páginas privadas. El middleware del servidor ya
 * impidió llegar aquí sin sesión (o sin el rol), así que esto normalmente no
 * hace nada; se mantiene por si el servidor sirvió la página sin usuario.
 *
 *   requerirSesion();                    // cualquier usuario con sesión
 *   requerirSesion({ rol: "admin" });    // además exige tener el rol admin
 */
export function requerirSesion({ rol = null } = {}) {
  if (!haySesion()) {
    window.location.href = appUrl("/login");
    throw new Error("Redirigiendo a login: no hay sesión activa.");
  }
  if (rol && !(usuarioEnMemoria.roles || []).includes(rol)) {
    window.location.href = appUrl("/dashboard");
    throw new Error(`Acceso restringido: la cuenta no tiene el rol "${rol}".`);
  }
}

// ============================================================
// PETICIONES A LA API
// ============================================================

function tokenCsrf() {
  return document.querySelector('meta[name="csrf-token"]')?.content ?? null;
}

/**
 * Cabeceras para un fetch a la API. La cookie de sesión viaja sola; aquí se
 * añade el token CSRF que Laravel exige en las peticiones que escriben.
 * Sirve igual para llamadas públicas (login, contacto, recuperar contraseña).
 *
 * `conJson = false` para cuerpos FormData (el navegador pone el
 * Content-Type con su boundary) y para peticiones sin cuerpo.
 */
export function cabecerasAutenticadas(conJson = true) {
  const cabeceras = {
    Accept: "application/json",
    "X-Requested-With": "XMLHttpRequest",
  };
  const csrf = tokenCsrf();
  if (csrf) cabeceras["X-CSRF-TOKEN"] = csrf;
  if (conJson) cabeceras["Content-Type"] = "application/json";
  return cabeceras;
}

/**
 * fetch contra la API con las cabeceras de sesión ya puestas.
 * `ruta` es relativa a API_BASE ("/mis-reservas") o una URL absoluta.
 * Si el cuerpo es FormData no se fuerza Content-Type; en cualquier otro
 * caso con cuerpo se manda JSON.
 */
export function apiFetch(ruta, opciones = {}) {
  const url = /^https?:\/\//i.test(ruta)
    ? ruta
    : `${API_BASE}${ruta.startsWith("/") ? ruta : "/" + ruta}`;
  const conJson = opciones.body !== undefined && !(opciones.body instanceof FormData);
  return fetch(url, {
    ...opciones,
    credentials: "same-origin",
    headers: { ...cabecerasAutenticadas(conJson), ...(opciones.headers || {}) },
  });
}

/**
 * Úsalo justo después de un fetch protegido:
 *
 *   if (manejarNoAutorizado(respuesta)) return;
 *
 * 401 -> no hay sesión válida (venció o la cerraron): a /login.
 * 419 -> el token CSRF no coincide: casi siempre una sesión vencida: a /login.
 * 403 -> solo si `redirigir403` (panel admin: la cuenta perdió el rol).
 * Devuelve true si redirigió (el llamador debe cortar).
 */
export function manejarNoAutorizado(respuesta, { redirigir403 = false } = {}) {
  if (respuesta.status === 401 || respuesta.status === 419) {
    limpiarSesion();
    window.location.href = appUrl("/login");
    return true;
  }
  if (redirigir403 && respuesta.status === 403) {
    window.location.href = appUrl("/dashboard");
    return true;
  }
  return false;
}

// ============================================================
// OPERACIONES DE SESIÓN CONTRA EL SERVIDOR
// ------------------------------------------------------------
// Devuelven { ok, datos }. El servidor ya abre/modifica la sesión: el
// llamador solo decide qué mostrar y a dónde navegar. Un fallo de red lanza
// excepción (el llamador ya tiene su try/catch).
// ============================================================

// `estricto`: si la respuesta no es JSON válido, lanza (el llamador lo trata
// como error de conexión). Sin `estricto`, un cuerpo ilegible queda como {}.
async function enviarJson(ruta, { cuerpo, estricto = false } = {}) {
  const respuesta = await fetch(`${API_BASE}${ruta}`, {
    method: "POST",
    credentials: "same-origin",
    headers: cabecerasAutenticadas(cuerpo !== undefined),
    ...(cuerpo !== undefined ? { body: JSON.stringify(cuerpo) } : {}),
  });
  const datos = estricto ? await respuesta.json() : await respuesta.json().catch(() => ({}));
  return { ok: respuesta.ok, datos };
}

/**
 * POST /login. Si la cuenta tiene varios roles, `datos.requiereSeleccionRol`
 * viene en true y `datos.roles` lista las opciones: el login queda
 * pendiente en el servidor hasta llamar a seleccionarRol().
 */
export function iniciarSesion(email, password) {
  return enviarJson("/login", { cuerpo: { email, password }, estricto: true });
}

/** POST /seleccionar-rol: segundo paso de un login con más de un rol. */
export function seleccionarRol(rol) {
  return enviarJson("/seleccionar-rol", { cuerpo: { rol } });
}

/** POST /cambiar-rol. Pasar a admin o a moderador exige la contraseña actual. */
export function cambiarRol(rol, password) {
  const cuerpo = rol === "admin" || rol === "moderador" ? { rol, password } : { rol };
  return enviarJson("/cambiar-rol", { cuerpo });
}

/** POST /agregar-rol-cliente (un admin puro se agrega el rol cliente). */
export function agregarRolCliente() {
  return enviarJson("/agregar-rol-cliente");
}

/**
 * Mantiene viva la sesión del servidor (que caduca por falta de peticiones,
 * no por falta de actividad en pantalla). Devuelve true si sigue válida. Si
 * ya venció, manejarNoAutorizado() redirige al login.
 */
export async function renovarSesion() {
  if (!haySesion()) return false;
  try {
    const respuesta = await fetch(`${API_BASE}/usuario`, {
      credentials: "same-origin",
      headers: cabecerasAutenticadas(false),
    });
    if (manejarNoAutorizado(respuesta)) return false;
    return respuesta.ok;
  } catch (e) {
    return false;
  }
}

/**
 * Cierra la sesión en el servidor (invalida la cookie) y olvida la copia en
 * memoria. Si la llamada falla (sin conexión, sesión ya vencida) igual se
 * limpia: el usuario quiere salir.
 */
export async function cerrarSesion() {
  try {
    if (haySesion()) {
      await fetch(`${API_BASE}/logout`, {
        method: "POST",
        credentials: "same-origin",
        headers: cabecerasAutenticadas(false),
      });
    }
  } catch (e) {
    // se ignora a propósito, ver comentario de arriba
  }
  limpiarSesion();
}
