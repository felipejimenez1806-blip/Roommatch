// ============================================================
// ROOMMATCH – lógica de la página CONFIRMACIÓN DE SOLICITUD
// Página privada: requiere sesión activa, igual que reserva.js.
// Recibe ?reservaId=X (el id generado al crear la solicitud en
// reserva.js) y consulta GET /api/reservas/{id}, que ya trae la
// reserva + los datos de la publicación asociada.
//
// CAMBIO PRINCIPAL: ya no depende de `publicaciones` (data.js) ni
// de `usuarioActual.reservas` en localStorage.
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

function estrellas(rating) {
  const llenas = Math.round(rating / 2); // rating es sobre 10, estrellas sobre 5
  return "★".repeat(llenas) + "☆".repeat(5 - llenas);
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

const ETIQUETAS_DURACION = {
  "1-3": "1 a 3 meses",
  "3-6": "3 a 6 meses",
  "6-12": "6 a 12 meses",
  "12+": "Más de 12 meses",
};

// ---------- CARGA INICIAL DESDE LA API ----------
async function cargarConfirmacion() {
  const contenedor = document.getElementById("confPage");
  const reservaId = new URLSearchParams(window.location.search).get("reservaId");

  if (!reservaId) {
    mostrarNoEncontrada(contenedor);
    return;
  }

  try {
    const resp = await fetch(`${API_BASE}/reservas/${reservaId}`, { headers: cabecerasAutenticadas(false) });
    if (!resp.ok) throw new Error("Solicitud no encontrada");

    const { reserva } = await resp.json();

    if (reserva.estado === "cancelada") {
      mostrarNoEncontrada(contenedor);
      return;
    }

    renderConfirmacion(reserva);
  } catch (err) {
    console.error(err);
    mostrarNoEncontrada(contenedor);
  }
}

function mostrarNoEncontrada(contenedor) {
  contenedor.innerHTML = `
    <div class="conf-not-found">
      <p>No encontramos esta solicitud. Puede que ya haya sido cancelada.</p>
      <a href="${esc(appUrl('/habitaciones'))}">← Volver a la búsqueda</a>
    </div>`;
}

// ---------- TEXTOS SEGÚN EL ESTADO DE LA RESERVA ----------
// pendiente: esperando al anfitrión · aceptada: confirmada (se puede cancelar) · rechazada: sin acciones.
function vistaSegunEstado(reserva) {
  const cuando = `${formatFechaCorta(reserva.fecha_visita)}${reserva.hora_visita ? ` a las ${formatHora12(reserva.hora_visita)}` : ""}`;

  if (reserva.estado === "aceptada") {
    return {
      icono: "✓", clase: "is-aceptada",
      titulo: "¡Tu reserva fue aceptada!",
      lead: "El anfitrión confirmó tu visita. Si ya no puedes asistir, cancélala desde aquí para que le avisemos.",
      encabezado: `Tu visita está confirmada para el ${cuando}`,
      puedeCancelar: true, textoCancelar: "Cancelar visita",
    };
  }
  if (reserva.estado === "rechazada") {
    return {
      icono: "✕", clase: "is-rechazada",
      titulo: "El anfitrión no aceptó tu solicitud",
      lead: "Esta vez no fue posible. Puedes seguir buscando otras habitaciones disponibles.",
      encabezado: `Visita solicitada para el ${cuando}`,
      puedeCancelar: false, textoCancelar: "",
    };
  }
  return {
    icono: "✓", clase: "",
    titulo: "Tu solicitud fue enviada",
    lead: "Le avisamos al anfitrión sobre tu interés. Te contactaremos por correo en cuanto la confirme o la rechace.",
    encabezado: `Tu visita queda programada para el ${cuando}`,
    puedeCancelar: true, textoCancelar: "Cancelar solicitud",
  };
}

// ---------- RENDER PRINCIPAL ----------
function renderConfirmacion(reserva) {
  const contenedor = document.getElementById("confPage");
  const item = reserva.publicacion;
  const imagenPrincipal = (item.imagenes && item.imagenes[0]) || "";
  const nombreAnfitrion = item.propietario?.nombre || "Anfitrión no especificado";
  const vista = vistaSegunEstado(reserva);

  contenedor.innerHTML = `
    <div class="conf-body">
      <div class="conf-left">
        <div class="conf-card">
          <img class="conf-card-img" src="${esc(imagenPrincipal)}" alt="${esc(item.titulo)}"/>
          <div class="conf-card-body">
            <div class="conf-card-title">${esc(item.titulo)}</div>
            <div class="conf-card-rating">${esc(estrellas(item.rating))}</div>
            <div class="conf-card-location">${esc(item.direccion)}, ${esc(item.zona)}, ${esc(item.ciudad)}</div>
            <div class="conf-card-row"><span>Fecha de visita</span><span>${esc(formatFechaCorta(reserva.fecha_visita))}</span></div>
            <div class="conf-card-row"><span>Hora de visita</span><span>${esc(formatHora12(reserva.hora_visita) || "Sin definir")}</span></div>
            <div class="conf-card-row"><span>Duración estimada</span><span>${esc(ETIQUETAS_DURACION[reserva.duracion_estimada] || "Sin definir")}</span></div>
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
          <div><strong>Fecha y hora de visita</strong><br>${esc(formatFechaCorta(reserva.fecha_visita))}${esc(reserva.hora_visita ? ` · ${formatHora12(reserva.hora_visita)}` : "")}</div>
        </div>
        <div class="conf-detail">
          <span class="conf-detail-icon">⏳</span>
          <div><strong>Duración estimada de estadía</strong><br>${esc(ETIQUETAS_DURACION[reserva.duracion_estimada] || "Sin definir")}</div>
        </div>

        <div class="conf-info-row"><span>Dirección</span><span>${esc(item.direccion)}</span></div>
        <div class="conf-info-row"><span>Anfitrión</span><span>${esc(nombreAnfitrion)}</span></div>

        <div class="conf-price-row"><span>Costo mensual estimado</span><span>${esc(formatCOP(item.precio))}</span></div>

        <div class="conf-buttons">
          ${vista.puedeCancelar ? `
          <button type="button" class="conf-btn-solid" id="contactarBtn">Contactar al propietario</button>
          <button type="button" class="conf-btn-outline" id="cancelarBtn">${esc(vista.textoCancelar)}</button>` : `
          <button type="button" class="conf-btn-solid" id="buscarOtrasBtn">Buscar otras habitaciones</button>`}
        </div>
      </div>
    </div>`;

  if (vista.puedeCancelar) {
    document.getElementById("contactarBtn").addEventListener("click", () => contactarPropietario(item));
    document.getElementById("cancelarBtn").addEventListener("click", () => cancelarSolicitud(reserva));
  } else {
    document.getElementById("buscarOtrasBtn").addEventListener("click", () => { window.location.href = appUrl("/habitaciones"); });
  }
}

// ---------- CONTACTAR AL PROPIETARIO POR WHATSAPP ----------
function contactarPropietario(item) {
  const telefono = item.propietario?.telefono;
  if (!telefono) {
    alert("Este anfitrión todavía no tiene un número de contacto registrado.");
    return;
  }
  const mensaje = encodeURIComponent(`Hola, vi tu publicación "${item.titulo}" en RoomMatch y quisiera más información.`);
  // Solo dígitos en la ruta: el teléfono viene de otro usuario y no debe poder alterar la URL.
  window.open(`https://wa.me/${String(telefono).replace(/\D/g, "")}?text=${mensaje}`, "_blank", "noopener");
}

// ---------- CANCELAR LA SOLICITUD (modal propio, sin confirm() ni alert()) ----------
function cancelarSolicitud(reserva) {
  const estabaAceptada = reserva.estado === "aceptada";
  const cuando = `${formatFechaCorta(reserva.fecha_visita)}${reserva.hora_visita ? ` a las ${formatHora12(reserva.hora_visita)}` : ""}`;
  const pregunta = estabaAceptada ? "¿Cancelar esta visita?" : "¿Cancelar esta solicitud?";
  const aviso = estabaAceptada
    ? `Le avisaremos al anfitrión que ya no podrás asistir a la visita del ${cuando}.`
    : `Le avisaremos al anfitrión que ya no te interesa la visita del ${cuando}.`;

  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-warning">❓</div>
      <div class="cc-modal-title">${esc(pregunta)}</div>
      <p class="cc-modal-text">${esc(aviso)}</p>
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
      const resp = await fetch(`${API_BASE}/reservas/${reserva.id}/cancelar`, {
        method: "PATCH",
        headers: cabecerasAutenticadas(false),
      });
      if (!resp.ok) {
        // Laravel explica el motivo (ej. "Esta solicitud ya no se puede cancelar.").
        const datos = await resp.json().catch(() => ({}));
        throw new Error(datos.message || datos.mensaje || "No se pudo cancelar la solicitud");
      }
      mostrarAvisoCancelacion(estabaAceptada ? "Visita cancelada" : "Solicitud cancelada");
    } catch (err) {
      console.error(err);
      mostrarErrorCancelacion(err.message || "No pudimos cancelar tu solicitud. Intenta de nuevo.");
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

// ---------- AVISO DE SOLICITUD CANCELADA (redirige a Habitaciones) ----------
function mostrarAvisoCancelacion(titulo) {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-success">✓</div>
      <div class="cc-modal-title">${esc(titulo)}</div>
      <p class="cc-modal-text">Te llevamos de vuelta a Habitaciones para que sigas buscando tu próximo espacio.</p>
      <div class="cc-toast-progress"><span></span></div>
    </div>`;
  document.body.appendChild(overlay);

  overlay.addEventListener("click", () => { window.location.href = appUrl("/habitaciones"); });
  setTimeout(() => { window.location.href = appUrl("/habitaciones"); }, 1900);
}

cargarConfirmacion();
