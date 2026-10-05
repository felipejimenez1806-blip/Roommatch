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
    const resp = await fetch(`${API_BASE}/citas/${citaId}`, { headers: authHeaders() });
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
      <a href="/roomies">← Volver a la búsqueda</a>
    </div>`;
}

// ---------- RENDER PRINCIPAL ----------
function renderConfirmacion(cita) {
  const contenedor = document.getElementById("confPage");
  const item = cita.persona;
  const primerNombre = item.nombre.split(" ")[0];

  contenedor.innerHTML = `
    <div class="conf-body">
      <div class="conf-left">
        <div class="conf-card has-avatar">
          <img class="conf-card-img is-avatar" src="${item.img || ""}" alt="${item.nombre}"/>
          <div class="conf-card-body">
            <div class="conf-card-title">${item.nombre}</div>
            <div class="conf-card-rating">${palabraCalificacion(item.rating)} · ${item.rating}/10</div>
            <div class="conf-card-location">Busca en ${item.zona}, ${item.ciudad}</div>
            <div class="conf-card-row"><span>Fecha</span><span>${formatFechaCorta(cita.fecha_cita)}</span></div>
            <div class="conf-card-row"><span>Hora</span><span>${formatHora12(cita.hora_cita)}</span></div>
          </div>
        </div>
      </div>

      <div class="conf-right">
        <div class="conf-icon">✓</div>
        <div class="conf-title">Tu solicitud de cita fue enviada</div>
        <p class="conf-lead">
          Le avisamos a ${primerNombre} sobre tu interés en conocerse. Te contactaremos por correo en cuanto confirme o proponga otro horario.
        </p>

        <div class="conf-trip-heading">Su encuentro queda propuesto para el ${formatFechaCorta(cita.fecha_cita)}</div>

        <div class="conf-detail">
          <span class="conf-detail-icon">📅</span>
          <div><strong>Fecha y hora</strong><br>${formatFechaCorta(cita.fecha_cita)} · ${formatHora12(cita.hora_cita)}</div>
        </div>
        <div class="conf-detail">
          <span class="conf-detail-icon">📍</span>
          <div><strong>${ETIQUETAS_TIPO_ENCUENTRO[cita.tipo_encuentro] || "Punto de encuentro"}</strong><br>${cita.lugar_encuentro}</div>
        </div>
        ${cita.mensaje ? `
        <div class="conf-detail">
          <span class="conf-detail-icon">💬</span>
          <div><strong>Tu mensaje</strong><br>${cita.mensaje}</div>
        </div>` : ""}

        <div class="conf-info-row"><span>Con quién</span><span>${item.nombre}</span></div>
        <div class="conf-info-row"><span>Presupuesto declarado</span><span>${formatCOP(item.presupuesto)}</span></div>

        <div class="conf-buttons">
          <button type="button" class="conf-btn-solid" id="contactarBtn">💬 Contactar a ${primerNombre}</button>
          <button type="button" class="conf-btn-outline" id="cancelarCitaBtn">Cancelar cita</button>
        </div>
      </div>
    </div>`;

  document.getElementById("contactarBtn").addEventListener("click", () => contactarRoomie(item));
  document.getElementById("cancelarCitaBtn").addEventListener("click", () => confirmarCancelacion(item, cita));
}

// ---------- CONTACTAR AL ROOMIE POR WHATSAPP ----------
function contactarRoomie(item) {
  if (!item.telefono) {
    alert("Este roomie todavía no tiene un número de contacto registrado.");
    return;
  }
  const mensaje = encodeURIComponent(
    `Hola ${item.nombre.split(" ")[0]}, vi tu perfil en RoomMatch y me gustaría que nos conozcamos para visitar juntos el lugar que compartiríamos.`
  );
  window.open(`https://wa.me/${item.telefono}?text=${mensaje}`, "_blank");
}

// ---------- CANCELAR LA CITA (modal propio, sin confirm() ni alert()) ----------
function confirmarCancelacion(item, cita) {
  const overlay = document.createElement("div");
  overlay.className = "cc-overlay";
  overlay.innerHTML = `
    <div class="cc-modal">
      <div class="cc-modal-icon is-warning">❓</div>
      <div class="cc-modal-title">¿Cancelar esta cita?</div>
      <p class="cc-modal-text">Le avisaremos a ${item.nombre.split(" ")[0]} que ya no podrás asistir al encuentro del ${formatFechaCorta(cita.fecha_cita)}.</p>
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
        headers: authHeaders(),
      });
      if (!resp.ok) throw new Error("No se pudo cancelar la cita");
      mostrarAvisoCancelacion();
    } catch (err) {
      console.error(err);
      alert("No pudimos cancelar tu cita. Intenta de nuevo.");
    }
  });
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

  overlay.addEventListener("click", () => { window.location.href = "/roomies"; });
  setTimeout(() => { window.location.href = "/roomies"; }, 1900);
}

cargarConfirmacion();
