// ============================================================
// ROOMMATCH – PANEL DE MODERACIÓN
// ------------------------------------------------------------
// Tres secciones: Reportes (resolver, descartar, ESCALAR a un
// administrador), Publicaciones (ver, bajar con motivo, restaurar) y
// Mensajes de contacto (responder por correo). Mismas clases CSS que el
// panel de administración (admin.css), así que se ve igual.
//
// Seguridad: esta pantalla solo MUESTRA u OCULTA botones según lo que el
// servidor dice que el rol activo puede hacer (campo "acciones" de cada
// reporte y window.APP.permisos). Cada acción se vuelve a validar en el
// servidor con Gates/Policies y el middleware `can:`; si alguien fuerza una
// petición a mano, recibe 403.
//
// Todo texto que escribe un usuario (descripciones de reportes, mensajes
// de contacto, títulos de publicaciones) se pasa por esc() antes de entrar
// al HTML: un moderador lee contenido que no controla.
// ============================================================

import { API_BASE } from "../core/config.js";
import { requerirSesion, cabecerasAutenticadas, manejarNoAutorizado } from "../core/auth.js";

requerirSesion({ rol: "moderador" });

// ============================================================
// HELPERS
// ============================================================
function esc(valor) {
  return String(valor ?? "")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}
function formatCOP(n) {
  return "$" + Math.round(Number(n) || 0).toLocaleString("es-CO") + " COP";
}
function formatFecha(iso) {
  if (!iso) return "Sin fecha";
  return new Date(iso).toLocaleDateString("es-CO", { day: "2-digit", month: "short", year: "numeric" });
}
function mostrarToast(mensaje) {
  const toast = document.getElementById("admToast");
  toast.textContent = mensaje;
  toast.classList.add("show");
  clearTimeout(mostrarToast._t);
  mostrarToast._t = setTimeout(() => toast.classList.remove("show"), 2600);
}
function abrirModal(html) {
  document.getElementById("admModal").innerHTML = html;
  document.getElementById("admModalOverlay").hidden = false;
}
function cerrarModal() {
  document.getElementById("admModalOverlay").hidden = true;
  document.getElementById("admModal").innerHTML = "";
}
document.getElementById("admModalOverlay").addEventListener("click", (e) => {
  if (e.target.id === "admModalOverlay") cerrarModal();
});
function debounce(fn, ms) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), ms); };
}

/**
 * fetch contra /api con la sesión por cookie. Devuelve { ok, status, datos },
 * o null si la sesión venció (ya se redirigió al login). Un 403 NO expulsa de
 * la pantalla: significa "el servidor no te deja hacer esto" y se muestra su
 * mensaje (la Policy explica por qué).
 */
async function api(ruta, { metodo = "GET", cuerpo } = {}) {
  const respuesta = await fetch(`${API_BASE}${ruta}`, {
    method: metodo,
    credentials: "same-origin",
    headers: cabecerasAutenticadas(cuerpo !== undefined),
    ...(cuerpo !== undefined ? { body: JSON.stringify(cuerpo) } : {}),
  });
  if (manejarNoAutorizado(respuesta)) return null;
  const datos = await respuesta.json().catch(() => ({}));
  return { ok: respuesta.ok, status: respuesta.status, datos };
}

/** Primer mensaje de error legible de una respuesta fallida (422 con errors o 'mensaje'). */
function mensajeDeError(res, porDefecto) {
  if (res?.datos?.errors) {
    const primero = Object.values(res.datos.errors)[0];
    if (Array.isArray(primero) && primero[0]) return primero[0];
  }
  return res?.datos?.mensaje || porDefecto;
}

document.getElementById("modLogoutBtn").addEventListener("click", () => {
  window.RoommatchNav.cerrarSesion();
});
document.getElementById("modCambiarRolBtn")?.addEventListener("click", () => {
  window.RoommatchNav.abrirModalCambiarRol();
});

// ============================================================
// NAVEGACIÓN ENTRE SECCIONES
// ============================================================
document.querySelectorAll("#modNav .prf-nav-item").forEach((btn) => {
  btn.addEventListener("click", () => {
    document.querySelectorAll("#modNav .prf-nav-item").forEach((b) => b.classList.remove("active"));
    document.querySelectorAll(".prf-panel").forEach((p) => p.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById(btn.dataset.panel).classList.add("active");
  });
});

function activarPanelPorHash() {
  const panelId = window.location.hash.replace("#", "");
  if (!panelId) return;
  document.querySelector(`#modNav .prf-nav-item[data-panel="${panelId}"]`)?.click();
}
window.addEventListener("hashchange", activarPanelPorHash);

function activarSubtabs(selector, alCambiar) {
  document.querySelectorAll(`${selector} .prf-subtab`).forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(`${selector} .prf-subtab`).forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      alCambiar(btn.dataset.filtro);
    });
  });
}

function actualizarBadge(id, cantidad) {
  const badge = document.getElementById(id);
  badge.hidden = !cantidad;
  badge.textContent = cantidad;
}

// ============================================================
// REPORTES
// ============================================================
let filtroReporteActual = "pendiente";

const ETIQUETAS_ESTADO = { pendiente: "Pendiente", escalado: "Escalado", resuelto: "Resuelto", descartado: "Descartado" };
const ETIQUETAS_TIPO = { publicacion: "Publicación", persona: "Roomie", usuario: "Usuario" };
const ACCION_RESOLVER = {
  publicacion: "Bajar la publicación reportada",
  persona: "Eliminar el perfil de roomie reportado",
  usuario: "Resolver el reporte",
};

async function renderReportes() {
  const cont = document.getElementById("modReportesList");
  const empty = document.getElementById("modReportesEmpty");

  const res = await api(`/admin/reportes?estado=${encodeURIComponent(filtroReporteActual)}`);
  if (!res) return;
  if (!res.ok) {
    cont.innerHTML = "";
    empty.hidden = false;
    empty.textContent = mensajeDeError(res, "No se pudieron cargar los reportes.");
    return;
  }

  const reportes = res.datos.reportes || [];
  actualizarBadge("modReportesBadge", res.datos.pendientes || 0);

  if (!reportes.length) {
    cont.innerHTML = "";
    empty.hidden = false;
    empty.textContent = "No hay reportes en esta categoría.";
    return;
  }
  empty.hidden = true;

  cont.innerHTML = reportes.map((r) => {
    const a = r.acciones || {};
    const tieneAcciones = a.resolver || a.descartar || a.escalar;

    const nota = r.estado === "escalado" && r.nota_escalado
      ? `<div class="adm-reporte-nota"><strong>Escalado${r.escalado_por ? ` por ${esc(r.escalado_por)}` : ""}${r.fecha_escalado ? ` · ${esc(formatFecha(r.fecha_escalado))}` : ""}:</strong> ${esc(r.nota_escalado)}</div>`
      : "";

    let lateral;
    if (tieneAcciones) {
      lateral = `<div class="adm-reporte-actions">
        ${a.resolver ? `<button type="button" class="adm-row-btn is-danger" data-resolver="${r.id}">${esc(ACCION_RESOLVER[r.tipo] || "Resolver")}</button>` : ""}
        ${a.escalar ? `<button type="button" class="adm-row-btn is-primary" data-escalar="${r.id}">Escalar a administrador</button>` : ""}
        ${a.descartar ? `<button type="button" class="adm-row-btn" data-descartar="${r.id}">Descartar</button>` : ""}
      </div>`;
    } else if (r.estado === "escalado") {
      lateral = `<span class="adm-reporte-none">Esperando la decisión de un administrador</span>`;
    } else {
      lateral = `<span class="adm-reporte-none">${r.gestionado_por ? `Gestionado por ${esc(r.gestionado_por)}` : "Sin acciones pendientes"}</span>`;
    }

    return `
    <div class="adm-reporte-card">
      <div class="adm-reporte-icon">
        <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
      </div>
      <div class="adm-reporte-body">
        <div class="adm-reporte-top">
          <span class="adm-reporte-motivo">${esc(r.motivo)}</span>
          <span class="adm-reporte-tipo">${esc(ETIQUETAS_TIPO[r.tipo] || r.tipo)}</span>
          <span class="prf-badge is-${esc(r.estado)}">${esc(ETIQUETAS_ESTADO[r.estado] || r.estado)}</span>
        </div>
        <p class="adm-reporte-desc">${esc(r.descripcion)}</p>
        <div class="adm-reporte-meta">${esc(ETIQUETAS_TIPO[r.tipo] || r.tipo)}: ${esc(r.objetivo_titulo || "—")} · Reportado por ${esc(r.reportante)} · ${esc(formatFecha(r.fecha))}</div>
        ${nota}
      </div>
      <div class="adm-reporte-side">${lateral}</div>
    </div>`;
  }).join("");

  const buscar = (id) => reportes.find((x) => x.id === Number(id));

  cont.querySelectorAll("[data-resolver]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const r = buscar(btn.dataset.resolver);
      if (r) abrirModalResolver(r);
    });
  });
  cont.querySelectorAll("[data-escalar]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const r = buscar(btn.dataset.escalar);
      if (r) abrirModalEscalar(r);
    });
  });
  cont.querySelectorAll("[data-descartar]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      btn.disabled = true;
      const res2 = await api(`/admin/reportes/${btn.dataset.descartar}/descartar`, { metodo: "PATCH" });
      if (!res2) return;
      if (!res2.ok) {
        mostrarToast(mensajeDeError(res2, "No se pudo descartar el reporte."));
        btn.disabled = false;
        return;
      }
      mostrarToast("Reporte descartado.");
      renderReportes();
    });
  });
}

function abrirModalResolver(r) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="modCerrarX">&times;</button>
    <p class="adm-modal-title">Resolver reporte</p>
    <p class="adm-modal-sub">${esc(r.motivo)} — ${esc(r.objetivo_titulo || "—")}</p>
    <p style="font-size:13.5px;color:#555;line-height:1.5;margin:0 0 18px;">${esc(r.descripcion)}</p>
    <p class="adm-modal-sub" id="modResolverError" style="color:#c0261c;min-height:15px;"></p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="modCancelarResolver">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="modAplicarResolver">${esc(ACCION_RESOLVER[r.tipo] || "Resolver")}</button>
    </div>
  `);
  document.getElementById("modCerrarX").addEventListener("click", cerrarModal);
  document.getElementById("modCancelarResolver").addEventListener("click", cerrarModal);
  document.getElementById("modAplicarResolver").addEventListener("click", async (e) => {
    e.currentTarget.disabled = true;
    const res = await api(`/admin/reportes/${r.id}/resolver`, { metodo: "PATCH" });
    if (!res) return;
    if (!res.ok) {
      document.getElementById("modResolverError").textContent = mensajeDeError(res, "No se pudo resolver el reporte.");
      e.currentTarget.disabled = false;
      return;
    }
    cerrarModal();
    mostrarToast("Reporte resuelto.");
    renderReportes();
    renderPublicaciones();
  });
}

function abrirModalEscalar(r) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="modCerrarX">&times;</button>
    <p class="adm-modal-title">Escalar a un administrador</p>
    <p class="adm-modal-sub">${esc(r.motivo)} — ${esc(r.objetivo_titulo || "—")}. Los administradores recibirán una notificación y decidirán (por ejemplo, si hay que bloquear al usuario).</p>
    <textarea class="adm-modal-textarea" id="modNotaEscalar" maxlength="500" placeholder="Explica por qué lo escalas y qué encontraste (mínimo 10 caracteres)..."></textarea>
    <p class="adm-modal-sub" id="modEscalarError" style="color:#c0261c;min-height:15px;margin-top:-6px;"></p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="modCancelarEscalar">Cancelar</button>
      <button type="button" class="adm-row-btn is-primary" id="modConfirmarEscalar">Escalar reporte</button>
    </div>
  `);
  document.getElementById("modCerrarX").addEventListener("click", cerrarModal);
  document.getElementById("modCancelarEscalar").addEventListener("click", cerrarModal);
  document.getElementById("modConfirmarEscalar").addEventListener("click", async (e) => {
    const nota = document.getElementById("modNotaEscalar").value.trim();
    const errorEl = document.getElementById("modEscalarError");
    if (nota.length < 10) {
      errorEl.textContent = "La nota debe tener al menos 10 caracteres.";
      return;
    }
    e.currentTarget.disabled = true;
    const res = await api(`/admin/reportes/${r.id}/escalar`, { metodo: "PATCH", cuerpo: { nota } });
    if (!res) return;
    if (!res.ok) {
      errorEl.textContent = mensajeDeError(res, "No se pudo escalar el reporte.");
      e.currentTarget.disabled = false;
      return;
    }
    cerrarModal();
    mostrarToast("Reporte escalado a los administradores.");
    renderReportes();
  });
}

// ============================================================
// PUBLICACIONES
// ============================================================
let busquedaPubActual = "";
let filtroPubActual = "activas"; // 'activas' | 'eliminadas' | 'todas'

async function renderPublicaciones() {
  const grid = document.getElementById("modPubsGrid");
  const empty = document.getElementById("modPubsEmpty");

  const params = new URLSearchParams({ estado: filtroPubActual });
  if (busquedaPubActual) params.set("q", busquedaPubActual);

  const res = await api(`/admin/publicaciones?${params}`);
  if (!res) return;
  if (!res.ok) {
    grid.innerHTML = "";
    empty.hidden = false;
    empty.textContent = mensajeDeError(res, "No se pudieron cargar las publicaciones.");
    return;
  }

  const lista = res.datos.publicaciones || [];
  if (!lista.length) {
    grid.innerHTML = "";
    empty.hidden = false;
    empty.textContent = filtroPubActual === "eliminadas" ? "No hay publicaciones bajadas." : "No se encontraron publicaciones.";
    return;
  }
  empty.hidden = true;

  grid.innerHTML = lista.map((p) => `
    <div class="adm-pub-card">
      <img class="adm-pub-img" src="${esc(p.img || "")}" alt="${esc(p.titulo)}"/>
      <div class="adm-pub-body">
        <div class="adm-pub-title">
          ${esc(p.titulo)}
          ${p.eliminada ? `<span class="prf-badge is-bloqueado" style="margin-left:6px;">Bajada</span>` : ""}
        </div>
        <div class="adm-pub-sub">${esc(p.zona)} · ${esc(p.ciudad)} · ★ ${esc(p.rating ?? "—")}</div>
        <div class="adm-pub-price">${esc(formatCOP(p.precio))}</div>
        <div class="adm-pub-owner">Propietario: ${esc(p.propietario || "—")}</div>
        <div class="adm-pub-actions">
          ${p.eliminada
            ? `<button type="button" class="adm-row-btn is-primary" data-restaurar-pub="${p.id}">Restaurar</button>`
            : `<button type="button" class="adm-row-btn" data-ver-pub="${p.id}">Ver detalle</button>
               <button type="button" class="adm-row-btn is-danger" data-bajar-pub="${p.id}" data-titulo="${esc(p.titulo)}">Bajar</button>`}
        </div>
      </div>
    </div>`).join("");

  grid.querySelectorAll("[data-ver-pub]").forEach((btn) => {
    btn.addEventListener("click", () => abrirModalPublicacion(Number(btn.dataset.verPub)));
  });
  grid.querySelectorAll("[data-bajar-pub]").forEach((btn) => {
    btn.addEventListener("click", () => abrirModalBajar(Number(btn.dataset.bajarPub), btn.dataset.titulo));
  });
  grid.querySelectorAll("[data-restaurar-pub]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      btn.disabled = true;
      const res2 = await api(`/admin/publicaciones/${btn.dataset.restaurarPub}/restaurar`, { metodo: "PATCH" });
      if (!res2) return;
      if (!res2.ok) {
        mostrarToast(mensajeDeError(res2, "No se pudo restaurar la publicación."));
        btn.disabled = false;
        return;
      }
      mostrarToast("Publicación restaurada.");
      renderPublicaciones();
    });
  });
}

// Reutiliza el endpoint público GET /api/publicaciones/{id} (mismo criterio
// que el panel admin): ya trae características, propietario y reseñas.
async function abrirModalPublicacion(id) {
  const res = await api(`/publicaciones/${id}`);
  if (!res || !res.ok) return;
  const p = res.datos.publicacion;
  const c = p.caracteristicas || {};

  abrirModal(`
    <button class="adm-modal-close" type="button" id="modCerrarX">&times;</button>
    <p class="adm-modal-title">${esc(p.titulo)}</p>
    <p class="adm-modal-sub">${esc(p.direccion || "")} · ${esc(p.zona)}, ${esc(p.ciudad)}</p>
    <img class="adm-modal-img" src="${esc((p.imagenes && p.imagenes[0]) || "")}" alt="${esc(p.titulo)}"/>
    <dl class="adm-modal-grid">
      <dt>Precio</dt><dd>${esc(formatCOP(p.precio))}</dd>
      <dt>Tipo de espacio</dt><dd>${esc(p.tipo_espacio || "—")}</dd>
      <dt>Propietario</dt><dd>${esc(p.propietario?.nombre || "—")}</dd>
      <dt>Rating</dt><dd>★ ${esc(p.rating ?? "—")} (${(p.resenas || []).length} reseñas)</dd>
      <dt>Disponible desde</dt><dd>${esc(p.fecha_disponible || "—")}</dd>
      <dt>Amueblado</dt><dd>${c.amueblado ? "Sí" : "No"}</dd>
      <dt>Baño privado</dt><dd>${c.bano_privado ? "Sí" : "No"}</dd>
      <dt>Ambiente</dt><dd>${esc(c.ambiente_hogar || "—")}</dd>
      <dt>Nº habitantes</dt><dd>${esc(c.numero_habitantes ?? "—")}</dd>
    </dl>
    <p style="font-size:13.5px;color:#555;line-height:1.5;margin:0 0 18px;">${esc(p.descripcion || "")}</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="modCerrarDetalle">Cerrar</button>
      <button type="button" class="adm-row-btn is-danger" id="modBajarDesdeDetalle">Bajar publicación</button>
    </div>
  `);
  document.getElementById("modCerrarX").addEventListener("click", cerrarModal);
  document.getElementById("modCerrarDetalle").addEventListener("click", cerrarModal);
  document.getElementById("modBajarDesdeDetalle").addEventListener("click", () => {
    cerrarModal();
    abrirModalBajar(p.id, p.titulo);
  });
}

function abrirModalBajar(id, titulo) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="modCerrarX">&times;</button>
    <p class="adm-modal-title">¿Bajar esta publicación?</p>
    <p class="adm-modal-sub">"${esc(titulo)}" dejará de verse en el sitio, se rechazarán sus reservas pendientes y el propietario recibirá una notificación con el motivo. El registro no se borra: se puede restaurar desde el filtro "Bajadas".</p>
    <textarea class="adm-modal-textarea" id="modMotivoBajar" maxlength="255" placeholder="Motivo (obligatorio, mínimo 5 caracteres). Lo verá el propietario."></textarea>
    <p class="adm-modal-sub" id="modBajarError" style="color:#c0261c;min-height:15px;margin-top:-6px;"></p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="modCancelarBajar">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="modConfirmarBajar">Sí, bajar</button>
    </div>
  `);
  document.getElementById("modCerrarX").addEventListener("click", cerrarModal);
  document.getElementById("modCancelarBajar").addEventListener("click", cerrarModal);
  document.getElementById("modConfirmarBajar").addEventListener("click", async (e) => {
    const motivo = document.getElementById("modMotivoBajar").value.trim();
    const errorEl = document.getElementById("modBajarError");
    if (motivo.length < 5) {
      errorEl.textContent = "Indica el motivo (mínimo 5 caracteres).";
      return;
    }
    e.currentTarget.disabled = true;
    const res = await api(`/admin/publicaciones/${id}`, { metodo: "DELETE", cuerpo: { motivo } });
    if (!res) return;
    if (!res.ok) {
      errorEl.textContent = mensajeDeError(res, "No se pudo bajar la publicación.");
      e.currentTarget.disabled = false;
      return;
    }
    cerrarModal();
    mostrarToast("Publicación bajada.");
    renderPublicaciones();
  });
}

// ============================================================
// MENSAJES DE CONTACTO
// ============================================================
let filtroMensajeActual = "pendiente";

async function renderMensajes() {
  const cont = document.getElementById("modMensajesList");
  const empty = document.getElementById("modMensajesEmpty");

  const res = await api(`/admin/mensajes-contacto?estado=${encodeURIComponent(filtroMensajeActual)}`);
  if (!res) return;
  if (!res.ok) {
    cont.innerHTML = "";
    empty.hidden = false;
    empty.textContent = mensajeDeError(res, "No se pudieron cargar los mensajes.");
    return;
  }

  const mensajes = res.datos.mensajes || [];
  actualizarBadge("modMensajesBadge", res.datos.pendientes || 0);

  if (!mensajes.length) {
    cont.innerHTML = "";
    empty.hidden = false;
    empty.textContent = "No hay mensajes en esta categoría.";
    return;
  }
  empty.hidden = true;

  cont.innerHTML = mensajes.map((m) => `
    <div class="adm-reporte-card">
      <div class="adm-reporte-icon">
        <svg viewBox="0 0 24 24"><path d="M4 4h16v16H4z"/><path d="M4 6l8 7 8-7"/></svg>
      </div>
      <div class="adm-reporte-body">
        <div class="adm-reporte-top">
          <span class="adm-reporte-motivo">${esc(m.nombre)}</span>
          <span class="adm-reporte-tipo">${esc(m.asunto_label)}</span>
          <span class="prf-badge ${m.atendido ? "is-resuelto" : "is-pendiente"}">${m.atendido ? "Atendido" : "Pendiente"}</span>
        </div>
        <p class="adm-reporte-desc">${esc(m.mensaje)}</p>
        <div class="adm-reporte-meta">${esc(m.correo)} · ${esc(formatFecha(m.fecha))}${m.atendido && m.atendido_por ? ` · Atendido por ${esc(m.atendido_por)}` : ""}</div>
        ${m.respuesta ? `<div class="adm-reporte-nota"><strong>Respuesta enviada:</strong> ${esc(m.respuesta)}</div>` : ""}
      </div>
      <div class="adm-reporte-side">
        ${!m.atendido ? `
          <div class="adm-reporte-actions">
            <button type="button" class="adm-row-btn is-primary" data-responder="${m.id}">Responder por correo</button>
            <button type="button" class="adm-row-btn" data-atender="${m.id}">Marcar atendido</button>
          </div>` : `<span class="adm-reporte-none">Sin acciones pendientes</span>`}
      </div>
    </div>`).join("");

  cont.querySelectorAll("[data-responder]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const m = mensajes.find((x) => x.id === Number(btn.dataset.responder));
      if (m) abrirModalResponder(m);
    });
  });
  cont.querySelectorAll("[data-atender]").forEach((btn) => {
    btn.addEventListener("click", () => confirmarAtenderSinResponder(Number(btn.dataset.atender)));
  });
}

function abrirModalResponder(m) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="modCerrarX">&times;</button>
    <p class="adm-modal-title">Responder a ${esc(m.nombre)}</p>
    <p class="adm-modal-sub">${esc(m.asunto_label)} · ${esc(m.correo)}</p>
    <div class="adm-reporte-nota" style="margin:0 0 14px;"><strong>Su mensaje:</strong> ${esc(m.mensaje)}</div>
    <textarea class="adm-modal-textarea" id="modTextoRespuesta" maxlength="5000" style="min-height:140px;" placeholder="Escribe la respuesta (mínimo 10 caracteres). Se enviará por correo a ${esc(m.correo)}."></textarea>
    <p class="adm-modal-sub" id="modResponderError" style="color:#c0261c;min-height:15px;margin-top:-6px;"></p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="modCancelarResponder">Cancelar</button>
      <button type="button" class="adm-row-btn is-primary" id="modEnviarRespuesta">Enviar respuesta</button>
    </div>
  `);
  document.getElementById("modCerrarX").addEventListener("click", cerrarModal);
  document.getElementById("modCancelarResponder").addEventListener("click", cerrarModal);
  document.getElementById("modEnviarRespuesta").addEventListener("click", async (e) => {
    const respuesta = document.getElementById("modTextoRespuesta").value.trim();
    const errorEl = document.getElementById("modResponderError");
    if (respuesta.length < 10) {
      errorEl.textContent = "La respuesta debe tener al menos 10 caracteres.";
      return;
    }
    const btn = e.currentTarget;
    btn.disabled = true;
    btn.textContent = "Enviando...";
    const res = await api(`/admin/mensajes-contacto/${m.id}/responder`, { metodo: "POST", cuerpo: { respuesta } });
    if (!res) return;
    if (!res.ok) {
      errorEl.textContent = mensajeDeError(res, "No se pudo enviar la respuesta.");
      btn.disabled = false;
      btn.textContent = "Enviar respuesta";
      // 409: otra persona del equipo ya lo atendió; se refresca la lista.
      if (res.status === 409) renderMensajes();
      return;
    }
    cerrarModal();
    mostrarToast("Respuesta enviada por correo.");
    renderMensajes();
  });
}

function confirmarAtenderSinResponder(id) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="modCerrarX">&times;</button>
    <p class="adm-modal-title">¿Marcar como atendido sin responder?</p>
    <p class="adm-modal-sub">No se enviará ningún correo. Úsalo solo si el caso ya se resolvió por otro medio.</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="modCancelarAtender">Cancelar</button>
      <button type="button" class="adm-row-btn is-primary" id="modConfirmarAtender">Sí, marcar atendido</button>
    </div>
  `);
  document.getElementById("modCerrarX").addEventListener("click", cerrarModal);
  document.getElementById("modCancelarAtender").addEventListener("click", cerrarModal);
  document.getElementById("modConfirmarAtender").addEventListener("click", async () => {
    const res = await api(`/admin/mensajes-contacto/${id}/atender`, { metodo: "PATCH" });
    if (!res) return;
    cerrarModal();
    mostrarToast(res.ok ? "Mensaje marcado como atendido." : mensajeDeError(res, "No se pudo marcar el mensaje."));
    renderMensajes();
  });
}

// ============================================================
// INICIALIZACIÓN
// ============================================================
activarSubtabs("#modReportesFiltros", (f) => { filtroReporteActual = f; renderReportes(); });
activarSubtabs("#modPubsFiltros", (f) => { filtroPubActual = f; renderPublicaciones(); });
activarSubtabs("#modMensajesFiltros", (f) => { filtroMensajeActual = f; renderMensajes(); });
document.getElementById("modPubsSearch").addEventListener("input", debounce((e) => {
  busquedaPubActual = e.target.value.trim();
  renderPublicaciones();
}, 300));

renderReportes();
renderPublicaciones();
renderMensajes();
activarPanelPorHash();
