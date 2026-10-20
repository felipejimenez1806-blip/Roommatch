/**
 * core/validacion.js
 *
 * Validaciones del navegador para los wizards de crear publicación y crear
 * perfil de roomie, con los MISMOS topes que el servidor (config/roommatch.php,
 * que llega por window.APP.limites). Cada validador devuelve:
 *   - un texto con el problema concreto ("El precio no puede superar..."), o
 *   - null si el valor está bien.
 *
 * Además traduce las respuestas de error del servidor (422 con `errors`) a un
 * mensaje por campo; antes el JS buscaba `datos.mensaje`, que Laravel nunca
 * manda en un 422, y por eso siempre salía el texto genérico.
 */
import { LIMITES as L } from "./config.js";

const cop = (n) => "$" + Number(n).toLocaleString("es-CO");
const vacio = (v) => v === "" || v === null || v === undefined;

// ------------------------------------------------------------
// Números
// ------------------------------------------------------------

/** Monto en pesos al mes (precio de la publicación o presupuesto del roomie). */
export function validarMonto(valor, { sujeto = "El precio", min, max }) {
  if (vacio(valor)) return `Ingresa ${sujeto.toLowerCase()} mensual.`;
  const n = Number(valor);
  if (!Number.isFinite(n)) return `${sujeto} debe ser un número.`;
  if (!Number.isInteger(n)) return `${sujeto} debe ser un número entero en pesos, sin decimales.`;
  if (n < min) return `${sujeto} mínimo es ${cop(min)} COP al mes.`;
  if (n > max) return `${sujeto} no puede superar ${cop(max)} COP al mes. Revisa que no tengas ceros de más.`;
  return null;
}
export const validarPrecio = (v) => validarMonto(v, { sujeto: "El precio", min: L.precio_min, max: L.precio_max });
export const validarPresupuesto = (v) => validarMonto(v, { sujeto: "El presupuesto", min: L.presupuesto_min, max: L.presupuesto_max });

/** Habitantes actuales: opcional, entero entre 0 y el máximo. */
export function validarHabitantes(valor) {
  if (vacio(valor)) return null;
  const n = Number(valor);
  if (!Number.isFinite(n)) return "El número de habitantes debe ser un número.";
  if (!Number.isInteger(n)) return "El número de habitantes debe ser un número entero.";
  if (n < L.habitantes_min) return "El número de habitantes no puede ser negativo.";
  if (n > L.habitantes_max) return `El número de habitantes no puede ser mayor a ${L.habitantes_max}.`;
  return null;
}

export function validarEdad(valor) {
  if (vacio(valor)) return `Ingresa tu edad (${L.edad_min}-${L.edad_max}).`;
  const n = Number(valor);
  if (!Number.isFinite(n) || !Number.isInteger(n)) return "La edad debe ser un número entero.";
  if (n < L.edad_min) return `Debes tener al menos ${L.edad_min} años para publicar tu perfil.`;
  if (n > L.edad_max) return `Ingresa una edad válida (máximo ${L.edad_max}).`;
  return null;
}

// ------------------------------------------------------------
// Texto, teléfono y fecha
// ------------------------------------------------------------

/** Texto obligatorio con largo mínimo y máximo. `sujeto`: "El título", "La descripción"... */
export function validarTexto(valor, { sujeto, min, max }) {
  const largo = (valor ?? "").toString().trim().length;
  if (largo < min) return `${sujeto} debe tener al menos ${min} caracteres.`;
  if (largo > max) return `${sujeto} no puede superar ${max} caracteres (llevas ${largo}).`;
  return null;
}

/** Texto opcional: solo se comprueba el máximo. */
export function validarTextoOpcional(valor, { sujeto, max }) {
  const largo = (valor ?? "").toString().trim().length;
  if (largo > max) return `${sujeto} no puede superar ${max} caracteres (llevas ${largo}).`;
  return null;
}

export const MENSAJE_TELEFONO = "Ingresa un celular colombiano de 10 dígitos que empiece por 3 (ej. 300 123 4567). El indicativo +57 es opcional.";

/**
 * Formato ÚNICO de teléfono: "+57" + celular de 10 dígitos (App\Support\Telefono
 * en PHP hace lo mismo; si cambias una, cambia la otra). Acepta "300 111 2233",
 * "300-111-2233", "(300) 1112233", "+57 300 111 2233", "573001112233", "0057...".
 * Devuelve "+573001112233" o null si no es un celular colombiano.
 */
export function normalizarTelefono(valor) {
  let d = String(valor ?? "").replace(/\D/g, "");
  if (d.startsWith("0057")) d = d.slice(4);
  else if (d.length === 12 && d.startsWith("57")) d = d.slice(2);
  return /^3\d{9}$/.test(d) ? `+57${d}` : null;
}

/** Para pintar el campo de un formulario: los 10 dígitos, sin +57 (el indicativo se pone solo). */
export const telefonoNacional = (valor) => (normalizarTelefono(valor) ?? String(valor ?? "")).replace(/^\+57/, "");

/** "+57 300 111 2233" (solo para mostrar). */
export function formatearTelefono(valor) {
  const n = normalizarTelefono(valor);
  return n ? `+57 ${n.slice(3, 6)} ${n.slice(6, 9)} ${n.slice(9)}` : String(valor ?? "");
}

export const validarTelefono = (valor) => (normalizarTelefono(valor) ? null : MENSAJE_TELEFONO);

/** Fecha "YYYY-MM-DD" de un <input type=date>. `permitirPasado` en modo edición. */
export function validarFecha(valor, { permitirPasado = false } = {}) {
  if (!valor) return "Selecciona la fecha desde la que está disponible.";
  const fecha = new Date(`${valor}T00:00:00`);
  if (Number.isNaN(fecha.getTime())) return "La fecha de disponibilidad no es válida.";
  const hoy = new Date();
  hoy.setHours(0, 0, 0, 0);
  if (!permitirPasado && fecha < hoy) return "La fecha de disponibilidad no puede ser anterior a hoy.";
  const limite = new Date(hoy);
  limite.setFullYear(limite.getFullYear() + 2);
  if (fecha > limite) return "La fecha de disponibilidad no puede ser mayor a 2 años desde hoy.";
  return null;
}

// ------------------------------------------------------------
// Entradas numéricas: no dejar teclear signos ni decimales
// ------------------------------------------------------------

/**
 * Un <input type="number"> deja escribir "-", "e", "+", "." y ",". En campos
 * enteros y sin negativos (precio, habitantes, edad) se bloquean esas teclas.
 * Pegar "-5" sí es posible: lo atrapa el validador al salir del campo.
 */
export function bloquearTeclasNoEnteras(input) {
  input.addEventListener("keydown", (e) => {
    if (["-", "+", "e", "E", ".", ","].includes(e.key)) e.preventDefault();
  });
}

// ------------------------------------------------------------
// Respuestas del servidor
// ------------------------------------------------------------

/** JSON de la respuesta; si el cuerpo no es JSON (p. ej. un 413 en HTML) devuelve {}. */
export async function leerRespuesta(respuesta) {
  try {
    return await respuesta.json();
  } catch {
    return {};
  }
}

/**
 * Convierte el `errors` de un 422 ({ "caracteristicas.horario_entrada": ["..."] })
 * en { horario_entrada: "..." }: sin el prefijo `caracteristicas.` y sin el
 * índice final de las listas (imagenes.2 -> imagenes). Solo el primer mensaje.
 */
export function erroresPorCampo(datos) {
  const porCampo = {};
  const errors = datos?.errors;
  if (!errors || typeof errors !== "object") return porCampo;
  for (const [clave, mensajes] of Object.entries(errors)) {
    const campo = clave.replace(/^caracteristicas\./, "").replace(/\.\d+$/, "");
    if (!(campo in porCampo)) porCampo[campo] = Array.isArray(mensajes) ? mensajes[0] : String(mensajes);
  }
  return porCampo;
}

/**
 * Mensaje general para respuestas que NO son un 422 con campos. Laravel manda
 * `message` (no `mensaje`) en abort(403/404/409, "..."), así que se lee ese.
 * En un 500 `message` es "Server Error": no se le muestra al usuario.
 */
export function mensajeGeneral(respuesta, datos, porDefecto) {
  if (respuesta.status === 413) return "Las fotos pesan demasiado en conjunto. Quita alguna o usa imágenes más livianas.";
  if (respuesta.status === 429) return "Demasiados intentos seguidos. Espera un momento e intenta de nuevo.";
  if (respuesta.status >= 500) return "Tuvimos un problema en el servidor. Intenta de nuevo en unos minutos.";
  return datos?.mensaje || datos?.message || porDefecto;
}
