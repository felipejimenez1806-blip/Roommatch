// ============================================================
// SELECT PERSONALIZADO (panel de administración)
// ------------------------------------------------------------
// Reemplaza al <select> nativo (cuyo panel desplegable no se puede
// estilizar) con el mismo diseño de los wizards de publicación y
// perfil roomie, pero reutilizable: sirve tanto para los modales de
// admin.js (HTML que se inyecta dinámicamente) como para el
// formulario de crear-usuario.js.
//
// Uso:
//   1) Pintar:   selectHtml({ id: "miSelect", opciones: [["admin", "Administrador"]], valor: "admin" })
//   2) Enlazar:  enlazarSelects(contenedor)        // una vez inyectado el HTML
//   3) Leer:     valorSelect(document.getElementById("miSelect"))
//   4) Escuchar: el wrapper emite "select-cambio" (burbujea) con detail.valor
//   5) Escribir: establecerValorSelect(wrapper, "moderador")
// ============================================================

function esc(valor) {
  return String(valor ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

const CHEVRON = `<svg class="adm-select-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>`;
const CHECK = `<svg class="adm-select-check" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>`;

/**
 * HTML del select. `opciones` es un arreglo de [valor, etiqueta].
 * `valor` vacío = sin selección (se muestra el placeholder).
 */
export function selectHtml({ id, opciones, valor = "", placeholder = "Selecciona una opción", labelledby = "" }) {
  const actual = opciones.find(([v]) => v === valor);
  const items = opciones.map(([v, etiqueta]) => `
      <li class="adm-select-option${actual && actual[0] === v ? " is-selected" : ""}" role="option" tabindex="-1" data-value="${esc(v)}" aria-selected="${actual && actual[0] === v}">
        <span>${esc(etiqueta)}</span>${CHECK}
      </li>`).join("");

  return `
    <div class="adm-select" id="${esc(id)}" data-adm-select data-value="${esc(actual ? actual[0] : "")}" data-placeholder="${esc(placeholder)}">
      <button type="button" class="adm-select-trigger" aria-haspopup="listbox" aria-expanded="false"${labelledby ? ` aria-labelledby="${esc(labelledby)}"` : ""}>
        <span class="adm-select-value${actual ? "" : " is-placeholder"}" data-adm-select-value>${esc(actual ? actual[1] : placeholder)}</span>
        ${CHEVRON}
      </button>
      <ul class="adm-select-menu" role="listbox" hidden>${items}
      </ul>
    </div>`;
}

export function valorSelect(wrapper) {
  return wrapper?.dataset.value || "";
}

export function establecerValorSelect(wrapper, valor, { emitir = false } = {}) {
  const opciones = Array.from(wrapper.querySelectorAll(".adm-select-option"));
  const elegida = opciones.find((o) => o.dataset.value === valor);
  const valueEl = wrapper.querySelector("[data-adm-select-value]");

  opciones.forEach((o) => {
    const activa = o === elegida;
    o.classList.toggle("is-selected", activa);
    o.setAttribute("aria-selected", String(activa));
  });

  wrapper.dataset.value = elegida ? elegida.dataset.value : "";
  valueEl.textContent = elegida ? elegida.querySelector("span").textContent : wrapper.dataset.placeholder;
  valueEl.classList.toggle("is-placeholder", !elegida);

  if (emitir) {
    wrapper.dispatchEvent(new CustomEvent("select-cambio", { bubbles: true, detail: { valor: wrapper.dataset.value } }));
  }
}

export function cerrarSelects(excepto = null) {
  document.querySelectorAll(".adm-select.is-open").forEach((w) => {
    if (w === excepto) return;
    w.classList.remove("is-open");
    w.querySelector(".adm-select-menu").hidden = true;
    w.querySelector(".adm-select-trigger").setAttribute("aria-expanded", "false");
  });
}

// Un solo listener global para cerrar al hacer clic fuera.
let clicFueraRegistrado = false;
function registrarClicFuera() {
  if (clicFueraRegistrado) return;
  clicFueraRegistrado = true;
  document.addEventListener("click", (e) => {
    document.querySelectorAll(".adm-select.is-open").forEach((w) => {
      if (!w.contains(e.target)) cerrarSelects();
    });
  });
}

/** Activa todos los selects personalizados dentro de `raiz` (idempotente). */
export function enlazarSelects(raiz = document) {
  registrarClicFuera();

  raiz.querySelectorAll("[data-adm-select]").forEach((wrapper) => {
    if (wrapper.dataset.enlazado === "1") return;
    wrapper.dataset.enlazado = "1";

    const trigger = wrapper.querySelector(".adm-select-trigger");
    const menu = wrapper.querySelector(".adm-select-menu");
    const opciones = Array.from(menu.querySelectorAll(".adm-select-option"));

    const abrir = () => {
      cerrarSelects(wrapper);
      menu.hidden = false;
      wrapper.classList.add("is-open");
      trigger.setAttribute("aria-expanded", "true");
    };
    const cerrar = () => {
      menu.hidden = true;
      wrapper.classList.remove("is-open");
      trigger.setAttribute("aria-expanded", "false");
    };
    const elegir = (opt) => {
      establecerValorSelect(wrapper, opt.dataset.value, { emitir: true });
      cerrar();
      trigger.focus();
    };

    trigger.addEventListener("click", () => (menu.hidden ? abrir() : cerrar()));

    trigger.addEventListener("keydown", (e) => {
      if (["Enter", " ", "ArrowDown"].includes(e.key)) {
        e.preventDefault();
        abrir();
        (menu.querySelector(".is-selected") || opciones[0])?.focus();
      } else if (e.key === "Escape") {
        cerrar();
      }
    });

    opciones.forEach((opt, i) => {
      opt.addEventListener("click", () => elegir(opt));
      opt.addEventListener("keydown", (e) => {
        if (e.key === "Enter" || e.key === " ") {
          e.preventDefault();
          elegir(opt);
        } else if (e.key === "ArrowDown") {
          e.preventDefault();
          opciones[Math.min(i + 1, opciones.length - 1)].focus();
        } else if (e.key === "ArrowUp") {
          e.preventDefault();
          (opciones[i - 1] || trigger).focus();
        } else if (e.key === "Home") {
          e.preventDefault();
          opciones[0].focus();
        } else if (e.key === "End") {
          e.preventDefault();
          opciones[opciones.length - 1].focus();
        } else if (e.key === "Escape") {
          e.stopPropagation();
          cerrar();
          trigger.focus();
        } else if (e.key === "Tab") {
          cerrar();
        }
      });
    });
  });
}
