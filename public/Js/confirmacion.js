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

const STORAGE_TOKEN_KEY = "roommatch_token";

function obtenerToken() {
  return sessionStorage.getItem(STORAGE_TOKEN_KEY);
}

function authHeaders(extra = {}) {
  const token = obtenerToken();
  return {
    Accept: "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extra,
  };
}

// Página privada: sin token no hay nada que hacer aquí.
if (!obtenerToken()) {
  window.location.href = "/login";
}

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
    const resp = await fetch(`${API_BASE}/reservas/${reservaId}`, { headers: authHeaders() });
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
      <a href="/habitaciones">← Volver a la búsqueda</a>
    </div>`;
}

// ---------- RENDER PRINCIPAL ----------
function renderConfirmacion(reserva) {
  const contenedor = document.getElementById("confPage");
  const item = reserva.publicacion;
  const imagenPrincipal = (item.imagenes && item.imagenes[0]) || "";
  const nombreAnfitrion = item.propietario?.nombre || "Anfitrión no especificado";

  contenedor.innerHTML = `
    <div class="conf-body">
      <div class="conf-left">
        <div class="conf-card">
          <img class="conf-card-img" src="${imagenPrincipal}" alt="${item.titulo}"/>
          <div class="conf-card-body">
            <div class="conf-card-title">${item.titulo}</div>
            <div class="conf-card-rating">${estrellas(item.rating)}</div>
            <div class="conf-card-location">${item.direccion}, ${item.zona}, ${item.ciudad}</div>
            <div class="conf-card-row"><span>Fecha de visita</span><span>${formatFechaCorta(reserva.fecha_visita)}</span></div>
            <div class="conf-card-row"><span>Hora de visita</span><span>${formatHora12(reserva.hora_visita) || "Sin definir"}</span></div>
            <div class="conf-card-row"><span>Duración estimada</span><span>${ETIQUETAS_DURACION[reserva.duracion_estimada] || "Sin definir"}</span></div>
          </div>
        </div>
      </div>

      <div class="conf-right">
        <div class="conf-icon">✓</div>
        <div class="conf-title">Tu solicitud fue enviada</div>
        <p class="conf-lead">
          Le avisamos al anfitrión sobre tu interés. Te contactaremos por correo en cuanto la confirme o la rechace.
        </p>

        <div class="conf-trip-heading">Tu visita queda programada para el ${formatFechaCorta(reserva.fecha_visita)}${reserva.hora_visita ? ` a las ${formatHora12(reserva.hora_visita)}` : ""}</div>

        <div class="conf-detail">
          <span class="conf-detail-icon">📅</span>
          <div><strong>Fecha y hora de visita</strong><br>${formatFechaCorta(reserva.fecha_visita)}${reserva.hora_visita ? ` · ${formatHora12(reserva.hora_visita)}` : ""}</div>
        </div>
        <div class="conf-detail">
          <span class="conf-detail-icon">⏳</span>
          <div><strong>Duración estimada de estadía</strong><br>${ETIQUETAS_DURACION[reserva.duracion_estimada] || "Sin definir"}</div>
        </div>

        <div class="conf-info-row"><span>Dirección</span><span>${item.direccion}</span></div>
        <div class="conf-info-row"><span>Anfitrión</span><span>${nombreAnfitrion}</span></div>

        <div class="conf-price-row"><span>Costo mensual estimado</span><span>${formatCOP(item.precio)}</span></div>

        <div class="conf-buttons">
          <button type="button" class="conf-btn-solid" id="contactarBtn">Contactar al propietario</button>
          <button type="button" class="conf-btn-outline" id="cancelarBtn">Cancelar solicitud</button>
        </div>
      </div>
    </div>`;

  document.getElementById("contactarBtn").addEventListener("click", () => contactarPropietario(item));
  document.getElementById("cancelarBtn").addEventListener("click", () => cancelarSolicitud(reserva));
}

// ---------- CONTACTAR AL PROPIETARIO POR WHATSAPP ----------
function contactarPropietario(item) {
  const telefono = item.propietario?.telefono;
  if (!telefono) {
    alert("Este anfitrión todavía no tiene un número de contacto registrado.");
    return;
  }
  const mensaje = encodeURIComponent(`Hola, vi tu publicación "${item.titulo}" en RoomMatch y quisiera más información.`);
  window.open(`https://wa.me/${telefono}?text=${mensaje}`, "_blank");
}

// ---------- CANCELAR LA SOLICITUD (modal propio, sin confirm() ni alert()) ----------
function cancelarSolicitud(reserva) {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-warning">❓</div>
      <div class="cc-modal-title">¿Cancelar esta solicitud?</div>
      <p class="cc-modal-text">Le avisaremos al anfitrión que ya no te interesa la visita del ${formatFechaCorta(reserva.fecha_visita)}${reserva.hora_visita ? ` a las ${formatHora12(reserva.hora_visita)}` : ""}.</p>
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
        headers: authHeaders(),
      });
      if (!resp.ok) throw new Error("No se pudo cancelar la solicitud");
      mostrarAvisoCancelacion();
    } catch (err) {
      console.error(err);
      alert("No pudimos cancelar tu solicitud. Intenta de nuevo.");
    }
  });
}

// ---------- AVISO DE SOLICITUD CANCELADA (redirige a Habitaciones) ----------
function mostrarAvisoCancelacion() {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-success">✓</div>
      <div class="cc-modal-title">Solicitud cancelada</div>
      <p class="cc-modal-text">Te llevamos de vuelta a Habitaciones para que sigas buscando tu próximo espacio.</p>
      <div class="cc-toast-progress"><span></span></div>
    </div>`;
  document.body.appendChild(overlay);

  overlay.addEventListener("click", () => { window.location.href = "/habitaciones"; });
  setTimeout(() => { window.location.href = "/habitaciones"; }, 1900);
}

cargarConfirmacion();
