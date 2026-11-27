// ============================================================
// ROOMMATCH – lógica de la página CONFIRMACIÓN DE CITA
// Página privada: requiere sesión activa, igual que cita-roomie.js.
// Recibe ?citaId=X (el id generado al crear la solicitud en
// cita-roomie.js) y consulta GET /api/citas/{id}, que ya trae la
// cita + los datos del perfil de roomie asociado.
//
// CAMBIO PRINCIPAL: ya no depende de `personas` (roomies-data.js)
// ni de `usuarioActual.citas` en localStorage.
// ============================================================

import { API_BASE, appUrl } from "../core/config.js";
import { requerirSesion, cabecerasAutenticadas } from "../core/auth.js";
import { esc } from "../core/html.js";

// Página privada: sin sesión no hay nada que hacer aquí.
requerirSesion();

// ---------- HELPERS ----------
function formatCOP(n) {
  return "$" + Math.round(n).toLocaleString("es-CO") + " COP";
}

function palabraCalificacion(rating) {
  if (rating >= 9) return "Excelente";
  if (rating >= 8) return "Muy bueno";
  if (rating >= 7) return "Bueno";
  return "Regular";
}

function formatFechaCorta(fechaISO) {
  if (!fechaISO) return null;
  return new Date(fechaISO + "T00:00:00").toLocaleDateString("es-CO", {
    day: "2-digit", month: "short", year: "numeric",
  });
}

function formatHora12(horaISO) {
  if (!horaISO) return null;
  const [h, m] = horaISO.split(":").map(Number);
  const periodo = h >= 12 ? "p. m." : "a. m.";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${String(m).padStart(2, "0")} ${periodo}`;
}

const ETIQUETAS_TIPO_ENCUENTRO = {
  sitio: "En el sitio que compartirían",
  publico: "En un lugar público cercano",
  otro: "Otro punto de encuentro",
};

// ---------- CARGA INICIAL DESDE LA API ----------
async function cargarConfirmacion() {
  const contenedor = document.getElementById("confPage");
  const citaId = new URLSearchParams(window.location.search).get("citaId");

  if (!citaId) {
    mostrarNoEncontrada(contenedor);
    return;
  }

  try {
    const resp = await fetch(`${API_BASE}/citas/${citaId}`, { headers: cabecerasAutenticadas(false) });
    if (!resp.ok) throw new Error("Cita no encontrada");

    const { cita } = await resp.json();

    if (cita.estado === "cancelada") {
      mostrarNoEncontrada(contenedor);
      return;
    }

    renderConfirmacion(cita);
  } catch (err) {
    console.error(err);
    mostrarNoEncontrada(contenedor);
  }
}

function mostrarNoEncontrada(contenedor) {
  contenedor.innerHTML = `
    <div class="conf-not-found">
      <p>No encontramos esta cita. Puede que ya haya sido cancelada.</p>
      <a href="${esc(appUrl('/roomies'))}">← Volver a la búsqueda</a>
    </div>`;
}

// ---------- TEXTOS SEGÚN EL ESTADO DE LA CITA ----------
// pendiente: esperando al roomie · aceptada: confirmada (se puede cancelar) · rechazada: sin acciones.
function vistaSegunEstado(cita, primerNombre) {
  const fecha = formatFechaCorta(cita.fecha_cita);

  if (cita.estado === "aceptada") {
    return {
      icono: "✓", clase: "is-aceptada",
      titulo: "¡Tu cita fue aceptada!",
      lead: `${primerNombre} confirmó el encuentro. Si ya no puedes asistir, cancélala desde aquí para que le avisemos.`,
      encabezado: `Su encuentro está confirmado para el ${fecha}`,
      puedeCancelar: true, textoCancelar: "Cancelar cita",
    };
  }
  if (cita.estado === "rechazada") {
    return {
      icono: "✕", clase: "is-rechazada",
      titulo: "No se pudo concretar la cita",
      lead: `${primerNombre} no aceptó esta vez. Puedes seguir buscando a tu compañero/a ideal.`,
      encabezado: `Encuentro solicitado para el ${fecha}`,
      puedeCancelar: false, textoCancelar: "",
    };
  }
  return {
    icono: "✓", clase: "",
    titulo: "Tu solicitud de cita fue enviada",
    lead: `Le avisamos a ${primerNombre} sobre tu interés en conocerse. Te contactaremos por correo en cuanto confirme o proponga otro horario.`,
    encabezado: `Su encuentro queda propuesto para el ${fecha}`,
    puedeCancelar: true, textoCancelar: "Cancelar cita",
  };
}

// ---------- RENDER PRINCIPAL ----------
function renderConfirmacion(cita) {
  const contenedor = document.getElementById("confPage");
  const item = cita.persona;
  const primerNombre = item.nombre.split(" ")[0];
  const vista = vistaSegunEstado(cita, primerNombre);

  contenedor.innerHTML = `
    <div class="conf-body">
      <div class="conf-left">
        <div class="conf-card has-avatar">
          <img class="conf-card-img is-avatar" src="${esc(item.img || "")}" alt="${esc(item.nombre)}"/>
          <div class="conf-card-body">
            <div class="conf-card-title">${esc(item.nombre)}</div>
            <div class="conf-card-rating">${esc(palabraCalificacion(item.rating))} · ${esc(item.rating)}/10</div>
            <div class="conf-card-location">Busca en ${esc(item.zona)}, ${esc(item.ciudad)}</div>
            <div class="conf-card-row"><span>Fecha</span><span>${esc(formatFechaCorta(cita.fecha_cita))}</span></div>
            <div class="conf-card-row"><span>Hora</span><span>${esc(formatHora12(cita.hora_cita))}</span></div>
          </div>
        </div>
      </div>

      <div class="conf-right">
        <div class="conf-icon ${esc(vista.clase)}">${esc(vista.icono)}</div>
        <div class="conf-title">${esc(vista.titulo)}</div>
        <p class="conf-lead">${esc(vista.lead)}</p>

        <div class="conf-trip-heading">${esc(vista.encabezado)}</div>

        <div class="conf-detail">
          <span class="conf-detail-icon">📅</span>
          <div><strong>Fecha y hora</strong><br>${esc(formatFechaCorta(cita.fecha_cita))} · ${esc(formatHora12(cita.hora_cita))}</div>
        </div>
        <div class="conf-detail">
          <span class="conf-detail-icon">📍</span>
          <div><strong>${esc(ETIQUETAS_TIPO_ENCUENTRO[cita.tipo_encuentro] || "Punto de encuentro")}</strong><br>${esc(cita.lugar_encuentro)}</div>
        </div>
        ${cita.mensaje ? `
        <div class="conf-detail">
          <span class="conf-detail-icon">💬</span>
          <div><strong>Tu mensaje</strong><br>${esc(cita.mensaje)}</div>
        </div>` : ""}

        <div class="conf-info-row"><span>Con quién</span><span>${esc(item.nombre)}</span></div>
        <div class="conf-info-row"><span>Presupuesto declarado</span><span>${esc(formatCOP(item.presupuesto))}</span></div>

        <div class="conf-buttons">
          ${vista.puedeCancelar ? `
          <button type="button" class="conf-btn-solid" id="contactarBtn">💬 Contactar a ${esc(primerNombre)}</button>
          <button type="button" class="conf-btn-outline" id="cancelarCitaBtn">${esc(vista.textoCancelar)}</button>` : `
          <button type="button" class="conf-btn-solid" id="buscarOtrosBtn">Buscar otros roomies</button>`}
        </div>
      </div>
    </div>`;

  if (vista.puedeCancelar) {
    document.getElementById("contactarBtn").addEventListener("click", () => contactarRoomie(item));
    document.getElementById("cancelarCitaBtn").addEventListener("click", () => confirmarCancelacion(item, cita));
  } else {
    document.getElementById("buscarOtrosBtn").addEventListener("click", () => { window.location.href = appUrl("/roomies"); });
  }
}

// ---------- CONTACTAR AL ROOMIE POR EL CHAT ----------
// Este botón solo existe en esta pantalla, que solo se ve cuando la cita ya fue solicitada
// (y desaparece si se cancela o se rechaza). Lleva a la pestaña Chat del perfil
// con la conversación del roomie ya abierta (?chat=id_usuario del roomie).
function contactarRoomie(item) {
  if (!item.id_usuario) return;
  window.location.href = appUrl(`/perfil?chat=${encodeURIComponent(item.id_usuario)}#panelChat`);
}

// ---------- CANCELAR LA CITA (modal propio, sin confirm() ni alert()) ----------
function confirmarCancelacion(item, cita) {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-warning">❓</div>
      <div class="cc-modal-title">¿Cancelar esta cita?</div>
      <p class="cc-modal-text">Le avisaremos a ${esc(item.nombre.split(" ")[0])} que ya no podrás asistir al encuentro del ${esc(formatFechaCorta(cita.fecha_cita))}.</p>
      <div class="cc-modal-buttons">
        <button type="button" class="cc-modal-btn-outline" id="ccMantenerBtn">No, mantener</button>
        <button type="button" class="cc-modal-btn-danger" id="ccConfirmarBtn">Sí, cancelar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = () => overlay.remove();
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
  document.getElementById("ccMantenerBtn").addEventListener("click", cerrar);
  document.getElementById("ccConfirmarBtn").addEventListener("click", async () => {
    cerrar();
    try {
      const resp = await fetch(`${API_BASE}/citas/${cita.id}/cancelar`, {
        method: "PATCH",
        headers: cabecerasAutenticadas(false),
      });
      if (!resp.ok) {
        // Laravel explica el motivo (ej. "Esta cita ya no se puede cancelar.").
        const datos = await resp.json().catch(() => ({}));
        throw new Error(datos.message || datos.mensaje || "No se pudo cancelar la cita");
      }
      mostrarAvisoCancelacion();
    } catch (err) {
      console.error(err);
      mostrarErrorCancelacion(err.message || "No pudimos cancelar tu cita. Intenta de nuevo.");
    }
  });
}

// ---------- ERROR AL CANCELAR (modal propio, sin alert()) ----------
function mostrarErrorCancelacion(mensaje) {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-warning">!</div>
      <div class="cc-modal-title">No pudimos cancelar</div>
      <p class="cc-modal-text">${esc(mensaje)}</p>
      <div class="cc-modal-buttons">
        <button type="button" class="cc-modal-btn-outline" id="ccEntendidoBtn">Entendido</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = () => { overlay.remove(); window.location.reload(); };
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
  document.getElementById("ccEntendidoBtn").addEventListener("click", cerrar);
}

// ---------- AVISO DE CITA CANCELADA (redirige solo a Roomies) ----------
function mostrarAvisoCancelacion() {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-success">✓</div>
      <div class="cc-modal-title">Cita cancelada</div>
      <p class="cc-modal-text">Te llevamos de vuelta a Roomies para que sigas buscando a tu compañero/a ideal.</p>
      <div class="cc-toast-progress"><span></span></div>
    </div>`;
  document.body.appendChild(overlay);

  overlay.addEventListener("click", () => { window.location.href = appUrl("/roomies"); });
  setTimeout(() => { window.location.href = appUrl("/roomies"); }, 1900);
}

cargarConfirmacion();
