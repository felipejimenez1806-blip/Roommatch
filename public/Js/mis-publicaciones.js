// ============================================================
// ROOMMATCH – lógica de la página MIS PUBLICACIONES
// ------------------------------------------------------------
// La sección de "Mis espacios publicados" + solicitudes de reserva
// ya usa la API real (GET/PATCH/DELETE sobre /api/mis-publicaciones
// y /api/reservas). La sección "Mi perfil de roomie" queda con un
// estado vacío honesto por ahora: ese backend (persona_roomie, cita)
// todavía no está migrado a Laravel.
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
const usuarioActual = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");

if (!token || !usuarioActual) {
  window.location.href = "/login";
  throw new Error("Redirigiendo a login: no hay sesión activa.");
}

function cabecerasAutenticadas(conJson = true) {
  const cabeceras = { Authorization: `Bearer ${token}`, Accept: "application/json" };
  if (conJson) cabeceras["Content-Type"] = "application/json";
  return cabeceras;
}

function manejarNoAutorizado(respuesta) {
  if (respuesta.status === 401) {
    sessionStorage.removeItem(STORAGE_TOKEN_KEY);
    sessionStorage.removeItem(STORAGE_USER_KEY);
    window.location.href = "/login";
    return true;
  }
  return false;
}

// ---------- HELPERS ----------
function formatCOP(n) {
  return "$" + Math.round(Number(n) || 0).toLocaleString("es-CO") + " COP";
}
function formatFechaCorta(fechaISO) {
  if (!fechaISO) return "Sin definir";
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
const ETIQUETAS_DURACION = { "1-3": "1 a 3 meses", "3-6": "3 a 6 meses", "6-12": "6 a 12 meses", "12+": "Más de 12 meses" };
// El backend usa 'aceptada' (no 'aprobada') para el estado de reserva/cita, ver schema.
const ETIQUETAS_BADGE = { pendiente: "Pendiente", aceptada: "Aceptada", rechazada: "Rechazada", cancelada: "Cancelada" };
const ETIQUETAS_TIPO_ENCUENTRO = { sitio: "En el lugar", publico: "Sitio público", otro: "Otro" };
const ETIQUETAS_ESTADO_INMUEBLE = { disponible: "Disponible", reservado: "Reservado / Ocupado", no_disponible: "No disponible" };
const ETIQUETAS_ESTADO_BUSQUEDA = { buscando: "Buscando roomie", ya_encontro: "Ya encontró", pausado: "Pausado" };

const mpPage = document.getElementById("mpPage");
let misPublicaciones = [];
let miPerfilRoomie = null;
let citasRecibidas = [];

// ---------- CARGA DE DATOS ----------
async function cargarMisPublicaciones() {
  const respuesta = await fetch(`${API_BASE}/mis-publicaciones`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return [];
  const datos = await respuesta.json();
  return datos.publicaciones || [];
}

// Si el usuario no se ha publicado como roomie, PersonaRoomieController::miPerfil()
// responde 200 con { perfil: null } (no un 404) — hay que leer esa clave, no "persona".
async function cargarMiPerfilRoomie() {
  const respuesta = await fetch(`${API_BASE}/mi-perfil-roomie`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return null;
  if (!respuesta.ok) return null;
  const datos = await respuesta.json();
  return datos.perfil || null;
}

async function cargarCitasRecibidas() {
  const respuesta = await fetch(`${API_BASE}/mis-citas-recibidas`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return [];
  if (!respuesta.ok) return [];
  const datos = await respuesta.json();
  return datos.citas || [];
}

// ---------- RENDER PRINCIPAL ----------
function render() {
  mpPage.innerHTML = `
    <a class="mp-back-link" href="/dashboard">&larr; Volver al inicio</a>
    <h1 class="mp-title">Mis publicaciones</h1>
    <p class="mp-subtitle">Gestiona tus espacios y responde a las solicitudes que recibas</p>

    <div class="mp-section-block">
      <p class="mp-section-heading">Mis espacios publicados</p>
      <div id="mpPubList"></div>
    </div>

    <div class="mp-section-block">
      <p class="mp-section-heading">Mi perfil de roomie</p>
      <div id="mpRoomieBlock"></div>
    </div>`;

  renderPublicaciones();
  renderRoomie();
}

// ---------- SECCIÓN: MIS ESPACIOS PUBLICADOS ----------
function renderPublicaciones() {
  const lista = document.getElementById("mpPubList");

  if (!misPublicaciones.length) {
    lista.innerHTML = `
      <div class="mp-empty">
        Todavía no has publicado ningún espacio.
        <br/><a href="/crear-publicacion?tipo=espacio">Crear mi primera publicación</a>
      </div>`;
    return;
  }

  lista.innerHTML = "";

  misPublicaciones.forEach((pub) => {
    const solicitudes = pub.solicitudes || [];

    const card = document.createElement("div");
    card.className = "mp-pub-card";
    card.innerHTML = `
      <div class="mp-pub-head">
        <img class="mp-pub-img" src="${pub.img || ""}" alt="${pub.titulo}"/>
        <div class="mp-pub-body">
          <p class="mp-pub-title">${pub.titulo}</p>
          <p class="mp-pub-sub">${pub.tipo_espacio} · ${pub.zona}, ${pub.ciudad}</p>
          <span class="mp-pub-price">${formatCOP(pub.precio)} / mes</span>
          <select class="mp-estado-select is-${pub.estado_inmueble}" data-estado-select="${pub.id}">
            ${Object.entries(ETIQUETAS_ESTADO_INMUEBLE).map(([valor, etiqueta]) => `
              <option value="${valor}"${pub.estado_inmueble === valor ? " selected" : ""}>${etiqueta}</option>
            `).join("")}
          </select>
        </div>
        <div class="mp-pub-actions">
          <a class="mp-view-link" href="/publicacion/${pub.id}">Ver publicación</a>
          <a class="mp-view-link" href="/crear-publicacion?id=${pub.id}">Editar</a>
          <button type="button" class="mp-delete-btn" data-eliminar="${pub.id}">Eliminar</button>
        </div>
      </div>

      <div class="mp-requests">
        <p class="mp-requests-title">Solicitudes de reserva (${solicitudes.length})</p>
        ${solicitudes.length
          ? solicitudes.map((reserva) => `
            <div class="mp-request-row" id="request-reserva-${reserva.id}" data-request-row="${reserva.id}">
              <div class="mp-request-body">
                <div class="mp-request-name">${reserva.contacto_nombre || "Sin nombre"}</div>
                <div class="mp-request-meta">
                  Visita: ${formatFechaCorta(reserva.fecha_visita)}${reserva.hora_visita ? ` · ${formatHora12(reserva.hora_visita)}` : ""}
                  · ${ETIQUETAS_DURACION[reserva.duracion] || ""} · ${formatCOP(reserva.monto_total)}
                </div>
                <div class="mp-request-contact">
                  ${reserva.contacto_correo ? `<a href="mailto:${reserva.contacto_correo}">${reserva.contacto_correo}</a>` : ""}
                  ${reserva.contacto_telefono ? ` · ${reserva.contacto_telefono}` : ""}
                </div>
              </div>
              <div class="mp-request-actions">
                <span class="mp-badge is-${reserva.estado}" data-badge="${reserva.id}">${ETIQUETAS_BADGE[reserva.estado] || reserva.estado}</span>
                <div class="mp-request-buttons">
                  <button type="button" class="mp-btn-mini is-aceptar${reserva.estado === "aceptada" ? " is-current" : ""}" data-accion="aceptada" data-id="${reserva.id}">Aceptar</button>
                  <button type="button" class="mp-btn-mini is-rechazar${reserva.estado === "rechazada" ? " is-current" : ""}" data-accion="rechazada" data-id="${reserva.id}">Rechazar</button>
                </div>
              </div>
            </div>`).join("")
          : `<p class="mp-empty-requests">Esta publicación todavía no ha recibido solicitudes de reserva.</p>`}
      </div>`;

    lista.appendChild(card);

    card.querySelectorAll("[data-accion]").forEach((btn) => {
      btn.addEventListener("click", () => {
        actualizarEstadoReserva(Number(btn.dataset.id), btn.dataset.accion);
      });
    });

    card.querySelector("[data-eliminar]").addEventListener("click", () => {
      confirmarEliminarPublicacion(pub, solicitudes.length);
    });

    card.querySelector("[data-estado-select]").addEventListener("change", (e) => {
      manejarCambioEstado(pub, e.target.value, e.target);
    });
  });
}

// ---------- CAMBIAR ESTADO (disponible / reservado / no disponible) ----------
// No requiere borrar la publicación ni pasar por el wizard completo:
// es la acción rápida para cuando el oferente ya cerró trato con
// alguien (o quiere reactivarla después).
async function aplicarCambioEstado(pub, nuevoEstado, selectEl) {
  const respuesta = await fetch(`${API_BASE}/mis-publicaciones/${pub.id}/estado`, {
    method: "PATCH",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify({ estado_inmueble: nuevoEstado }),
  });
  if (manejarNoAutorizado(respuesta)) return;
  if (!respuesta.ok) {
    alert("No se pudo actualizar el estado. Intenta de nuevo.");
    selectEl.value = pub.estado_inmueble;
    return;
  }
  misPublicaciones = await cargarMisPublicaciones();
  render();
}

function manejarCambioEstado(pub, nuevoEstado, selectEl) {
  const pendientes = (pub.solicitudes || []).filter((s) => s.estado === "pendiente").length;

  // Solo se pide confirmación cuando el cambio realmente le corta el
  // paso a alguien que sigue esperando respuesta (solicitudes pendientes).
  if (nuevoEstado === "disponible" || pendientes === 0) {
    aplicarCambioEstado(pub, nuevoEstado, selectEl);
    return;
  }

  const overlay = document.createElement("div");
  overlay.className = "mp-overlay";
  overlay.innerHTML = `
    <div class="mp-modal">
      <div class="mp-modal-icon">⚠️</div>
      <div class="mp-modal-title">¿Marcar "${pub.titulo}" como ${ETIQUETAS_ESTADO_INMUEBLE[nuevoEstado].toLowerCase()}?</div>
      <p class="mp-modal-text">
        Tiene ${pendientes} solicitud${pendientes === 1 ? "" : "es"} de reserva pendiente${pendientes === 1 ? "" : "s"}.
        Se rechazará${pendientes === 1 ? "" : "n"} automáticamente y se avisará a quien las envió.
      </p>
      <div class="mp-modal-buttons">
        <button type="button" class="mp-modal-btn-outline" id="mpEstadoMantenerBtn">Cancelar</button>
        <button type="button" class="mp-modal-btn-danger" id="mpEstadoConfirmarBtn">Sí, continuar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = (revertir) => {
    overlay.remove();
    if (revertir) selectEl.value = pub.estado_inmueble;
  };
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(true); });
  document.getElementById("mpEstadoMantenerBtn").addEventListener("click", () => cerrar(true));
  document.getElementById("mpEstadoConfirmarBtn").addEventListener("click", () => {
    overlay.remove();
    aplicarCambioEstado(pub, nuevoEstado, selectEl);
  });
}

// ---------- ACTUALIZAR ESTADO DE UNA RESERVA (aceptar / rechazar) ----------
async function actualizarEstadoReserva(reservaId, nuevoEstado) {
  const respuesta = await fetch(`${API_BASE}/reservas/${reservaId}/estado`, {
    method: "PATCH",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify({ estado: nuevoEstado }),
  });
  if (manejarNoAutorizado(respuesta)) return;
  if (!respuesta.ok) {
    alert("No se pudo actualizar la solicitud. Intenta de nuevo.");
    return;
  }
  misPublicaciones = await cargarMisPublicaciones();
  render();
}

// ---------- ELIMINAR PUBLICACIÓN (con modal de confirmación) ----------
function confirmarEliminarPublicacion(pub, cantidadSolicitudes) {
  const overlay = document.createElement("div");
  overlay.className = "mp-overlay";
  overlay.innerHTML = `
    <div class="mp-modal">
      <div class="mp-modal-icon">🗑️</div>
      <div class="mp-modal-title">¿Eliminar esta publicación?</div>
      <p class="mp-modal-text">
        "${pub.titulo}" dejará de verse en el inicio.
        ${cantidadSolicitudes ? `Tiene ${cantidadSolicitudes} solicitud${cantidadSolicitudes === 1 ? "" : "es"} de reserva asociada${cantidadSolicitudes === 1 ? "" : "s"} pendiente${cantidadSolicitudes === 1 ? "" : "s"}, que se rechazarán automáticamente y se avisará a quien las envió.` : "Esta acción no se puede deshacer."}
      </p>
      <div class="mp-modal-buttons">
        <button type="button" class="mp-modal-btn-outline" id="mpMantenerBtn">Cancelar</button>
        <button type="button" class="mp-modal-btn-danger" id="mpConfirmarBtn">Sí, eliminar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = () => overlay.remove();
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
  document.getElementById("mpMantenerBtn").addEventListener("click", cerrar);
  document.getElementById("mpConfirmarBtn").addEventListener("click", async () => {
    const respuesta = await fetch(`${API_BASE}/mis-publicaciones/${pub.id}`, {
      method: "DELETE",
      headers: cabecerasAutenticadas(false),
    });
    if (manejarNoAutorizado(respuesta)) return;
    cerrar();
    misPublicaciones = await cargarMisPublicaciones();
    render();
  });
}

// ---------- SECCIÓN: MI PERFIL DE ROOMIE ----------
function renderRoomie() {
  const bloque = document.getElementById("mpRoomieBlock");

  if (!miPerfilRoomie) {
    bloque.innerHTML = `
      <div class="mp-empty">
        Todavía no te has publicado como roomie.
        <br/><a href="/crear-perfil-roomie">Crear mi perfil de roomie</a>
      </div>`;
    return;
  }

  bloque.innerHTML = `
    <div class="mp-pub-card">
      <div class="mp-pub-head">
        <img class="mp-pub-img" src="${miPerfilRoomie.img || ""}" alt="${usuarioActual.nombre || "Mi perfil"}"/>
        <div class="mp-pub-body">
          <p class="mp-pub-title">${usuarioActual.nombre || "Mi perfil de roomie"}</p>
          <p class="mp-pub-sub">${miPerfilRoomie.zona || ""}${miPerfilRoomie.zona && miPerfilRoomie.ciudad ? ", " : ""}${miPerfilRoomie.ciudad || ""}</p>
          <select class="mp-estado-select is-${miPerfilRoomie.estado_busqueda}" id="mpEstadoRoomieSelect">
            ${Object.entries(ETIQUETAS_ESTADO_BUSQUEDA).map(([valor, etiqueta]) => `
              <option value="${valor}"${miPerfilRoomie.estado_busqueda === valor ? " selected" : ""}>${etiqueta}</option>
            `).join("")}
          </select>
        </div>
        <div class="mp-pub-actions">
          <a class="mp-view-link" href="/roomie/${miPerfilRoomie.id}">Ver perfil</a>
          <a class="mp-view-link" href="/crear-perfil-roomie">Editar</a>
          <button type="button" class="mp-delete-btn" id="mpEliminarRoomieBtn">Eliminar</button>
        </div>
      </div>

      <div class="mp-requests">
        <p class="mp-requests-title">Solicitudes de cita (${citasRecibidas.length})</p>
        ${citasRecibidas.length
          ? citasRecibidas.map((cita) => `
            <div class="mp-request-row" id="request-cita-${cita.id}" data-request-row="${cita.id}">
              <div class="mp-request-body">
                <div class="mp-request-name">${cita.solicitante || "Sin nombre"}</div>
                <div class="mp-request-meta">
                  Cita: ${formatFechaCorta(cita.fecha_cita)}${cita.hora_cita ? ` · ${formatHora12(cita.hora_cita)}` : ""}
                  · ${ETIQUETAS_TIPO_ENCUENTRO[cita.tipo_encuentro] || ""} · ${cita.lugar_encuentro || ""}
                </div>
                ${cita.mensaje ? `<div class="mp-request-contact">"${cita.mensaje}"</div>` : ""}
                <div class="mp-request-contact">
                  ${cita.contactoCorreo ? `<a href="mailto:${cita.contactoCorreo}">${cita.contactoCorreo}</a>` : ""}
                  ${cita.contactoTelefono ? ` · ${cita.contactoTelefono}` : ""}
                </div>
              </div>
              <div class="mp-request-actions">
                <span class="mp-badge is-${cita.estado}" data-badge="${cita.id}">${ETIQUETAS_BADGE[cita.estado] || cita.estado}</span>
                <div class="mp-request-buttons">
                  <button type="button" class="mp-btn-mini is-aceptar${cita.estado === "aceptada" ? " is-current" : ""}" data-accion-cita="aceptada" data-id="${cita.id}">Aceptar</button>
                  <button type="button" class="mp-btn-mini is-rechazar${cita.estado === "rechazada" ? " is-current" : ""}" data-accion-cita="rechazada" data-id="${cita.id}">Rechazar</button>
                </div>
              </div>
            </div>`).join("")
          : `<p class="mp-empty-requests">Tu perfil de roomie todavía no ha recibido solicitudes de cita.</p>`}
      </div>`;

  bloque.querySelectorAll("[data-accion-cita]").forEach((btn) => {
    btn.addEventListener("click", () => {
      actualizarEstadoCita(Number(btn.dataset.id), btn.dataset.accionCita);
    });
  });

  document.getElementById("mpEliminarRoomieBtn").addEventListener("click", () => {
    confirmarEliminarPerfilRoomie(citasRecibidas.length);
  });

  document.getElementById("mpEstadoRoomieSelect").addEventListener("change", (e) => {
    manejarCambioEstadoRoomie(e.target.value, e.target);
  });
}

// ---------- CAMBIAR ESTADO DEL PERFIL ROOMIE (buscando / ya_encontro / pausado) ----------
async function aplicarCambioEstadoRoomie(nuevoEstado, selectEl) {
  const estadoAnterior = miPerfilRoomie.estado_busqueda;
  const respuesta = await fetch(`${API_BASE}/mi-perfil-roomie/estado`, {
    method: "PATCH",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify({ estado_busqueda: nuevoEstado }),
  });
  if (manejarNoAutorizado(respuesta)) return;
  if (!respuesta.ok) {
    alert("No se pudo actualizar el estado. Intenta de nuevo.");
    selectEl.value = estadoAnterior;
    return;
  }
  miPerfilRoomie = await cargarMiPerfilRoomie();
  citasRecibidas = miPerfilRoomie ? await cargarCitasRecibidas() : [];
  renderRoomie();
}

function manejarCambioEstadoRoomie(nuevoEstado, selectEl) {
  const pendientes = citasRecibidas.filter((c) => c.estado === "pendiente").length;

  if (nuevoEstado === "buscando" || pendientes === 0) {
    aplicarCambioEstadoRoomie(nuevoEstado, selectEl);
    return;
  }

  const overlay = document.createElement("div");
  overlay.className = "mp-overlay";
  overlay.innerHTML = `
    <div class="mp-modal">
      <div class="mp-modal-icon">⚠️</div>
      <div class="mp-modal-title">¿Marcar tu perfil como ${ETIQUETAS_ESTADO_BUSQUEDA[nuevoEstado].toLowerCase()}?</div>
      <p class="mp-modal-text">
        Tienes ${pendientes} solicitud${pendientes === 1 ? "" : "es"} de cita pendiente${pendientes === 1 ? "" : "s"}.
        Se rechazará${pendientes === 1 ? "" : "n"} automáticamente y se avisará a quien las envió.
      </p>
      <div class="mp-modal-buttons">
        <button type="button" class="mp-modal-btn-outline" id="mpEstadoRoomieMantenerBtn">Cancelar</button>
        <button type="button" class="mp-modal-btn-danger" id="mpEstadoRoomieConfirmarBtn">Sí, continuar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = (revertir) => {
    overlay.remove();
    if (revertir) selectEl.value = miPerfilRoomie.estado_busqueda;
  };
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(true); });
  document.getElementById("mpEstadoRoomieMantenerBtn").addEventListener("click", () => cerrar(true));
  document.getElementById("mpEstadoRoomieConfirmarBtn").addEventListener("click", () => {
    overlay.remove();
    aplicarCambioEstadoRoomie(nuevoEstado, selectEl);
  });
}

// ---------- ELIMINAR MI PERFIL DE ROOMIE (con modal de confirmación) ----------
function confirmarEliminarPerfilRoomie(cantidadCitas) {
  const overlay = document.createElement("div");
  overlay.className = "mp-overlay";
  overlay.innerHTML = `
    <div class="mp-modal">
      <div class="mp-modal-icon">🗑️</div>
      <div class="mp-modal-title">¿Eliminar tu perfil de roomie?</div>
      <p class="mp-modal-text">
        Dejarás de aparecer en la búsqueda de roomies.
        ${cantidadCitas ? `Tiene ${cantidadCitas} solicitud${cantidadCitas === 1 ? "" : "es"} de cita asociada${cantidadCitas === 1 ? "" : "s"} pendiente${cantidadCitas === 1 ? "" : "s"}, que se rechazarán automáticamente y se avisará a quien las envió.` : "Esta acción no se puede deshacer."}
      </p>
      <div class="mp-modal-buttons">
        <button type="button" class="mp-modal-btn-outline" id="mpRoomieMantenerBtn">Cancelar</button>
        <button type="button" class="mp-modal-btn-danger" id="mpRoomieConfirmarBtn">Sí, eliminar</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = () => overlay.remove();
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
  document.getElementById("mpRoomieMantenerBtn").addEventListener("click", cerrar);
  document.getElementById("mpRoomieConfirmarBtn").addEventListener("click", async () => {
    const respuesta = await fetch(`${API_BASE}/mi-perfil-roomie`, {
      method: "DELETE",
      headers: cabecerasAutenticadas(false),
    });
    if (manejarNoAutorizado(respuesta)) return;
    if (!respuesta.ok) {
      alert("No se pudo eliminar el perfil. Intenta de nuevo.");
      return;
    }
    cerrar();
    miPerfilRoomie = null;
    citasRecibidas = [];
    renderRoomie();
  });
}

// ---------- ACTUALIZAR ESTADO DE UNA CITA (aceptar / rechazar) ----------
async function actualizarEstadoCita(citaId, nuevoEstado) {
  const respuesta = await fetch(`${API_BASE}/citas/${citaId}/estado`, {
    method: "PATCH",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify({ estado: nuevoEstado }),
  });
  if (manejarNoAutorizado(respuesta)) return;
  if (!respuesta.ok) {
    alert("No se pudo actualizar la cita. Intenta de nuevo.");
    return;
  }
  citasRecibidas = await cargarCitasRecibidas();
  renderRoomie();
}

// ============================================================
// LLEGAR DIRECTO A UNA SOLICITUD (desde una notificación)
// ============================================================
function inyectarEstiloResaltado() {
  if (document.getElementById("mpEstiloResaltado")) return;
  const style = document.createElement("style");
  style.id = "mpEstiloResaltado";
  style.textContent = `
    .mp-request-row.is-resaltada {
      outline: 2px solid #1a9ecf;
      outline-offset: 2px;
      border-radius: 10px;
      animation: mpParpadeo 1.4s ease 2;
    }
    @keyframes mpParpadeo {
      0%, 100% { background: transparent; }
      50% { background: #eaf6fc; }
    }
  `;
  document.head.appendChild(style);
}

function resaltarSolicitudPorHash() {
  const hash = window.location.hash.replace("#", "");
  if (!hash.startsWith("request-reserva-") && !hash.startsWith("request-cita-")) return;
  const fila = document.getElementById(hash);
  if (!fila) return;
  inyectarEstiloResaltado();
  fila.scrollIntoView({ behavior: "smooth", block: "center" });
  fila.classList.add("is-resaltada");
  setTimeout(() => fila.classList.remove("is-resaltada"), 2800);
}

// ---------- INICIALIZACIÓN ----------
(async function inicializar() {
  misPublicaciones = await cargarMisPublicaciones();
  miPerfilRoomie = await cargarMiPerfilRoomie();
  citasRecibidas = miPerfilRoomie ? await cargarCitasRecibidas() : [];
  render();
  resaltarSolicitudPorHash();
})();
