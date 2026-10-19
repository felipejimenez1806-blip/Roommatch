/**
 * core/html.js
 *
 * Escape de texto para insertarlo dentro de HTML (innerHTML / insertAdjacentHTML
 * / outerHTML). TODO dato que no escribió el propio código — nombres, títulos,
 * descripciones, mensajes, direcciones, lo que venga de la API o del
 * formulario de otro usuario — debe pasar por esc() antes de entrar en una
 * plantilla HTML:
 *
 *   el.innerHTML = `<p>${esc(usuario.nombre)}</p>`;
 *
 * Sirve igual dentro de atributos ("..." o '...'): escapa & < > " '.
 * Si lo que se interpola YA es un fragmento HTML construido por el código
 * (otra plantilla, el resultado de .map(...).join("")), NO se escapa de
 * nuevo: sus propios datos ya se escaparon donde se construyó.
 *
 * Para texto plano sin HTML es mejor `elemento.textContent = valor`, que no
 * necesita escape.
 */
export function esc(valor) {
  return String(valor ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}
