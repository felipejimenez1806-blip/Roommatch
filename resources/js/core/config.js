/**
 * core/config.js
 *
 * Única puerta de entrada a la configuración que viene del servidor.
 * El layout (partials/app-config.blade.php) define window.APP antes de que
 * se ejecute cualquier módulo; aquí se lee UNA vez y el resto del código
 * hace `import { API_BASE, appUrl } from ".../core/config.js"` en lugar de
 * depender de variables globales ni de rutas escritas a mano ("/login").
 *
 * Por qué importa: en producción la app puede vivir en un subdirectorio
 * (https://dominio.com/roommatch-laravel/public). Una ruta como "/login"
 * apunta a https://dominio.com/login (404); appUrl("/login") apunta a
 * https://dominio.com/roommatch-laravel/public/login.
 */
const APP = window.APP;

if (!APP) {
  throw new Error(
    "window.APP no está definido: falta @include('partials.app-config') en el <head> de esta vista."
  );
}

/** URL base de la app, sin barra final. Ej: https://dominio.com/roommatch-laravel/public */
export const BASE_URL = APP.baseUrl;

/** URL base de la API. Ej: https://dominio.com/roommatch-laravel/public/api */
export const API_BASE = APP.apiBase;

/** Parte de ruta del subdirectorio, sin barra final. "" si vive en la raíz. */
export const BASE_PATH = APP.basePath;

/**
 * Convierte una ruta interna ("/login", "/publicacion/5?x=1#a") en una URL
 * completa que respeta el subdirectorio. Si ya recibe una URL absoluta
 * (http://..., https://..., //...) la devuelve tal cual.
 */
export function appUrl(path = "/") {
  if (/^(?:[a-z][a-z0-9+.-]*:)?\/\//i.test(path)) return path;
  return BASE_URL + (path.startsWith("/") ? path : "/" + path);
}

/**
 * Ruta actual SIN el subdirectorio, para comparar contra rutas de la app.
 * Ej: en /roommatch-laravel/public/onboarding devuelve "/onboarding".
 */
export function rutaActual() {
  const ruta = window.location.pathname;
  const sinBase = BASE_PATH && ruta.startsWith(BASE_PATH) ? ruta.slice(BASE_PATH.length) : ruta;
  return sinBase || "/";
}
