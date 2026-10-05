// ============================================================
// ROOMMATCH – PANEL DE ADMINISTRACIÓN (migrado a la API real)
// ------------------------------------------------------------
// Cubre HU-11 (usuarios), HU-12 (publicaciones) y HU-13 (reportes),
// consumiendo /api/admin/* en vez de localStorage. Mismo patrón de
// autenticación que perfil.js (token Sanctum guardado en sessionStorage).
//
// La protección real de "solo admin" vive en el servidor
// (middleware es.admin sobre /api/admin/*). Aquí solo se evita
// mostrar el panel a quien no sea admin, igual que hacía la versión
// anterior con el usuario sembrado en localStorage.
//
// CAMBIO (roles N:N, Fase 1-3): tipo_usuario (ENUM) ya no existe.
// Usuario::paraFrontend() ahora manda 'roles' (arreglo, ej. ["cliente"]
// o ["cliente","admin"]) en vez de 'tipoUsuario', así que el guard de
// acceso de abajo se actualizó para leer ese arreglo. La TABLA de
// usuarios sigue trayendo 'tipoUsuario' desde el backend (ver
// Admin\UsuarioController::paraTablaAdmin()) — es un shim transicional
// que prioriza "admin" si el usuario tiene ambos roles, mientras no se
// rediseñe esta vista para pintar los dos badges a la vez (Fase 4).
//
// CAMBIO (soft delete): las publicaciones ya no se borran físicamente
// (ver App\Models\Publicacion::SoftDeletes). El panel de Publicaciones
// ahora deja ver "Activas" (default), "Eliminadas" y "Todas", y permite
// restaurar una eliminada — útil si un admin la borró por error o si
// tras revisar un reporte se concluye que no incumplía nada.
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
const usuarioActual = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");

if (!token || !usuarioActual) {
  window.location.href = "/login";
  throw new Error("Redirigiendo a login: no hay sesión activa.");
}
if (!(usuarioActual.roles || []).includes("admin")) {
  window.location.href = "/dashboard";
  throw new Error("Acceso restringido: la cuenta activa no tiene rol de administrador.");
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
  if (respuesta.status === 403) {
    window.location.href = "/dashboard";
    return true;
  }
  return false;
}

document.getElementById("admLogoutBtn").addEventListener("click", () => {
  window.RoommatchNav.cerrarSesion();
});

// ============================================================
// "USAR TAMBIÉN COMO CLIENTE" (Fase 4 - ajuste)
// ------------------------------------------------------------
// El botón equivalente en nav.js pinta dentro de #navActions, que NO
// existe en layouts.admin — y un admin puro nunca puede salir de
// /admin para verlo en una página con layouts.app (admin-guard.js lo
// rebota de vuelta). Así que aquí, en el panel mismo, se inyecta un
// botón junto al de "Cerrar sesión" (reutiliza su misma clase CSS,
// sea cual sea, para no depender de estilos que no están en admin.css)
// y reusa el modal ya construido en nav.js vía window.RoommatchNav.
// ============================================================
function inicializarUsarComoClienteAdmin() {
  const roles = usuarioActual.roles || [];
  const esSoloAdmin = roles.includes("admin") && !roles.includes("cliente");
  if (!esSoloAdmin) return;

  const logoutBtn = document.getElementById("admLogoutBtn");
  if (!logoutBtn || !window.RoommatchNav?.abrirModalUsarComoCliente) return;

  const btn = document.createElement("button");
  btn.type = "button";
  btn.id = "admUsarClienteBtn";
  btn.className = logoutBtn.className;
  btn.textContent = "Usar también como cliente";
  btn.style.marginBottom = "8px";

  logoutBtn.insertAdjacentElement("beforebegin", btn);
  btn.addEventListener("click", () => window.RoommatchNav.abrirModalUsarComoCliente());
}
inicializarUsarComoClienteAdmin();

// ============================================================
// HELPERS GENERALES
// ============================================================
function formatCOP(n) {
  return "$" + Math.round(n).toLocaleString("es-CO") + " COP";
}
function formatFecha(iso) {
  if (!iso) return "Sin fecha";
  return new Date(iso).toLocaleDateString("es-CO", { day: "2-digit", month: "short", year: "numeric" });
}
function inicial(nombre) {
  return (nombre || "U").trim().charAt(0).toUpperCase();
}
function mostrarToast(mensaje) {
  const toast = document.getElementById("admToast");
  toast.textContent = mensaje;
  toast.classList.add("show");
  clearTimeout(mostrarToast._t);
  mostrarToast._t = setTimeout(() => toast.classList.remove("show"), 2200);
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

// ============================================================
// NAVEGACIÓN ENTRE SECCIONES (sidebar)
// ============================================================
document.querySelectorAll("#admNav .prf-nav-item").forEach((btn) => {
  btn.addEventListener("click", () => {
    document.querySelectorAll("#admNav .prf-nav-item").forEach((b) => b.classList.remove("active"));
    document.querySelectorAll(".prf-panel").forEach((p) => p.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById(btn.dataset.panel).classList.add("active");
  });
});

function activarPanelPorHash() {
  const panelId = window.location.hash.replace("#", "");
  if (!panelId) return;
  const btn = document.querySelector(`#admNav .prf-nav-item[data-panel="${panelId}"]`);
  if (btn) btn.click();
}
window.addEventListener("hashchange", activarPanelPorHash);

// ============================================================
// USUARIOS (HU-11)
// ============================================================
let filtroUsuarioActual = "todos";
let busquedaUsuarioActual = "";

async function renderUsuarios() {
  const body = document.getElementById("admUsuariosBody");
  const empty = document.getElementById("admUsuariosEmpty");
  const tabla = document.getElementById("admUsuariosTabla");

  const params = new URLSearchParams({ filtro: filtroUsuarioActual });
  if (busquedaUsuarioActual) params.set("q", busquedaUsuarioActual);

  const respuesta = await fetch(`${API_BASE}/admin/usuarios?${params}`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const lista = datos.usuarios || [];

  if (!lista.length) {
    tabla.hidden = true;
    empty.hidden = false;
    empty.textContent = busquedaUsuarioActual || filtroUsuarioActual !== "todos"
      ? "No hay usuarios que coincidan con la búsqueda o el filtro."
      : "Aún no hay usuarios registrados.";
    return;
  }
  tabla.hidden = false;
  empty.hidden = true;

  body.innerHTML = lista.map((u) => {
    const avatar = u.fotoPerfil ? `<img src="${u.fotoPerfil}" alt="${u.nombre}"/>` : inicial(u.nombre);
    const esAdmin = u.tipoUsuario === "admin";
    const tipoClase = esAdmin ? "is-admin" : "is-cliente";
    const tipoLabel = esAdmin ? "Administrador" : "Cliente";

    // Fase 3: promover/revocar admin. Un admin nunca se puede bloquear
    // (ver UsuarioController::bloquear), así que esa fila solo ofrece
    // "Revocar admin"; una fila que no es admin ofrece "Promover a
    // admin" además del bloquear/desbloquear de siempre.
    const accionesHtml = esAdmin
      ? `<button type="button" class="adm-row-btn is-danger" data-revocar-admin="${u.id}" data-nombre="${u.nombre}">Revocar admin</button>`
      : `<button type="button" class="adm-row-btn" data-promover-admin="${u.id}" data-nombre="${u.nombre}">Promover a admin</button>
         <button type="button" class="adm-row-btn ${u.bloqueado ? "" : "is-danger"}" data-toggle-bloqueo="${u.id}" data-bloqueado="${u.bloqueado}" data-nombre="${u.nombre}">
           ${u.bloqueado ? "Desbloquear" : "Bloquear"}
         </button>`;

    return `
      <tr>
        <td data-label="Usuario">
          <div class="adm-user-cell">
            <span class="adm-user-avatar">${avatar}</span>
            <span class="adm-user-name">${u.nombre || "Sin nombre"}</span>
          </div>
        </td>
        <td data-label="Correo">${u.email || "—"}</td>
        <td data-label="Tipo"><span class="adm-tipo-badge ${tipoClase}">${tipoLabel}</span></td>
        <td data-label="Teléfono">${u.telefono || "—"}</td>
        <td data-label="Estado">
          <span class="prf-badge ${u.bloqueado ? "is-bloqueado" : "is-activo"}">${u.bloqueado ? "Bloqueado" : "Activo"}</span>
        </td>
        <td data-label="">
          <div style="display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;">
            ${accionesHtml}
          </div>
        </td>
      </tr>`;
  }).join("");

  body.querySelectorAll("[data-toggle-bloqueo]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const id = Number(btn.dataset.toggleBloqueo);
      const bloqueado = btn.dataset.bloqueado === "true";
      bloqueado ? desbloquearUsuario(id, btn.dataset.nombre) : abrirModalBloqueo(id, btn.dataset.nombre);
    });
  });
  body.querySelectorAll("[data-promover-admin]").forEach((btn) => {
    btn.addEventListener("click", () => {
      abrirModalPromoverAdmin(Number(btn.dataset.promoverAdmin), btn.dataset.nombre);
    });
  });
  body.querySelectorAll("[data-revocar-admin]").forEach((btn) => {
    btn.addEventListener("click", () => {
      confirmarRevocarAdmin(Number(btn.dataset.revocarAdmin), btn.dataset.nombre);
    });
  });
}

async function desbloquearUsuario(id, nombre) {
  const respuesta = await fetch(`${API_BASE}/admin/usuarios/${id}/desbloquear`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  mostrarToast(`${nombre} fue desbloqueado.`);
  renderUsuarios();
  renderResumen();
}

function abrirModalBloqueo(id, nombre) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">Bloquear a ${nombre}</p>
    <textarea class="adm-modal-textarea" id="admMotivoBloqueo" placeholder="Motivo del bloqueo (ej. incumplimiento de las normas de la comunidad)..."></textarea>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCancelarBloqueo">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="admConfirmarBloqueo">Bloquear usuario</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCancelarBloqueo").addEventListener("click", cerrarModal);
  document.getElementById("admConfirmarBloqueo").addEventListener("click", async () => {
    const motivo = document.getElementById("admMotivoBloqueo").value.trim();
    const respuesta = await fetch(`${API_BASE}/admin/usuarios/${id}/bloquear`, {
      method: "PATCH",
      headers: cabecerasAutenticadas(),
      body: JSON.stringify({ motivo }),
    });
    if (manejarNoAutorizado(respuesta)) return;
    cerrarModal();
    mostrarToast(`${nombre} fue bloqueado.`);
    renderUsuarios();
    renderResumen();
  });
}

// ---------- Fase 3: Promover / revocar admin ----------

function abrirModalPromoverAdmin(id, nombre) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">Promover a ${nombre}</p>
    <p class="adm-modal-sub">Tendrá acceso completo al panel de administración. Confirma tu propia contraseña para autorizar esta acción.</p>
    <input type="password" class="adm-modal-textarea" id="admPasswordActualPromover" style="min-height:auto;" placeholder="Tu contraseña actual" autocomplete="off"/>
    <p class="admcta-error" id="admPromoverError" style="color:#c0261c;font-size:12.5px;margin:-8px 0 12px;"></p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCancelarPromover">Cancelar</button>
      <button type="button" class="adm-row-btn is-primary" id="admConfirmarPromover">Promover a administrador</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCancelarPromover").addEventListener("click", cerrarModal);
  document.getElementById("admConfirmarPromover").addEventListener("click", async () => {
    const passwordActual = document.getElementById("admPasswordActualPromover").value;
    const errorEl = document.getElementById("admPromoverError");

    const respuesta = await fetch(`${API_BASE}/admin/usuarios/${id}/promover-admin`, {
      method: "PATCH",
      headers: cabecerasAutenticadas(),
      body: JSON.stringify({ password_actual: passwordActual }),
    });
    if (manejarNoAutorizado(respuesta)) return;
    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
      errorEl.textContent = datos.mensaje || "No se pudo promover al usuario.";
      return;
    }

    cerrarModal();
    mostrarToast(datos.mensaje || `${nombre} ahora es administrador.`);
    renderUsuarios();
    renderResumen();
  });
}

function confirmarRevocarAdmin(id, nombre) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">¿Revocar el rol de administrador a ${nombre}?</p>
    <p class="adm-modal-sub">Dejará de tener acceso al panel de administración.</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCancelarRevocar">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="admConfirmarRevocar">Sí, revocar</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCancelarRevocar").addEventListener("click", cerrarModal);
  document.getElementById("admConfirmarRevocar").addEventListener("click", async () => {
    const respuesta = await fetch(`${API_BASE}/admin/usuarios/${id}/revocar-admin`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
    if (manejarNoAutorizado(respuesta)) return;
    const datos = await respuesta.json().catch(() => ({}));

    if (!respuesta.ok) {
      cerrarModal();
      mostrarToast(datos.mensaje || "No se pudo revocar el rol.");
      return;
    }

    cerrarModal();
    mostrarToast(datos.mensaje || `Se revocó el rol de administrador a ${nombre}.`);
    renderUsuarios();
    renderResumen();
  });
}

function inicializarUsuarios() {
  document.getElementById("admUsuariosSearch").addEventListener("input", debounce((e) => {
    busquedaUsuarioActual = e.target.value.trim();
    renderUsuarios();
  }, 300));
  document.querySelectorAll("#admUsuariosFiltros .prf-subtab").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll("#admUsuariosFiltros .prf-subtab").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroUsuarioActual = btn.dataset.filtro;
      renderUsuarios();
    });
  });
  renderUsuarios();
}

// ============================================================
// PUBLICACIONES (HU-12)
// ============================================================
let busquedaPubActual = "";
let filtroPubActual = "activas"; // 'activas' | 'eliminadas' | 'todas' — ver Admin\PublicacionController::index()

async function renderPublicaciones() {
  const grid = document.getElementById("admPubsGrid");
  const empty = document.getElementById("admPubsEmpty");

  const params = new URLSearchParams({ estado: filtroPubActual });
  if (busquedaPubActual) params.set("q", busquedaPubActual);

  const respuesta = await fetch(`${API_BASE}/admin/publicaciones?${params}`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const lista = datos.publicaciones || [];

  if (!lista.length) {
    grid.innerHTML = "";
    empty.hidden = false;
    empty.textContent = filtroPubActual === "eliminadas"
      ? "No hay publicaciones eliminadas."
      : "No se encontraron publicaciones.";
    return;
  }
  empty.hidden = true;

  grid.innerHTML = lista.map((p) => `
    <div class="adm-pub-card">
      <img class="adm-pub-img" src="${p.img || ""}" alt="${p.titulo}"/>
      <div class="adm-pub-body">
        <div class="adm-pub-title">
          ${p.titulo}
          ${p.eliminada ? `<span class="prf-badge is-bloqueado" style="margin-left:6px;">Eliminada</span>` : ""}
        </div>
        <div class="adm-pub-sub">${p.zona} · ${p.ciudad} · ★ ${p.rating ?? "—"}</div>
        <div class="adm-pub-price">${formatCOP(p.precio)}</div>
        <div class="adm-pub-owner">Propietario: ${p.propietario || "—"}</div>
        <div class="adm-pub-actions">
          ${p.eliminada
            ? `<button type="button" class="adm-row-btn is-primary" data-restaurar-pub="${p.id}">Restaurar</button>`
            : `<button type="button" class="adm-row-btn" data-ver-pub="${p.id}">Ver detalle</button>
               <button type="button" class="adm-row-btn is-danger" data-eliminar-pub="${p.id}" data-titulo="${p.titulo}">Eliminar</button>`}
        </div>
      </div>
    </div>`).join("");

  grid.querySelectorAll("[data-ver-pub]").forEach((btn) => {
    btn.addEventListener("click", () => abrirModalPublicacion(Number(btn.dataset.verPub)));
  });
  grid.querySelectorAll("[data-eliminar-pub]").forEach((btn) => {
    btn.addEventListener("click", () => confirmarEliminarPublicacion(Number(btn.dataset.eliminarPub), btn.dataset.titulo));
  });
  grid.querySelectorAll("[data-restaurar-pub]").forEach((btn) => {
    btn.addEventListener("click", () => restaurarPublicacion(Number(btn.dataset.restaurarPub)));
  });
}

async function restaurarPublicacion(id) {
  const respuesta = await fetch(`${API_BASE}/admin/publicaciones/${id}/restaurar`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  if (!respuesta.ok) {
    mostrarToast("No se pudo restaurar la publicación.");
    return;
  }
  mostrarToast("Publicación restaurada.");
  renderPublicaciones();
  renderResumen();
}

// Reutiliza el endpoint público GET /api/publicaciones/{id}: ya trae
// características, propietario y reseñas formateadas, y no requiere
// duplicar ese formateo en el controlador de admin. Solo aplica a
// publicaciones activas (una eliminada no tiene botón "Ver detalle").
async function abrirModalPublicacion(id) {
  const respuesta = await fetch(`${API_BASE}/publicaciones/${id}`, { headers: cabecerasAutenticadas(false) });
  if (!respuesta.ok) return;
  const { publicacion: p } = await respuesta.json();
  const c = p.caracteristicas || {};

  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">${p.titulo}</p>
    <p class="adm-modal-sub">${p.direccion || ""} · ${p.zona}, ${p.ciudad}</p>
    <img class="adm-modal-img" src="${(p.imagenes && p.imagenes[0]) || ""}" alt="${p.titulo}"/>
    <dl class="adm-modal-grid">
      <dt>Precio</dt><dd>${formatCOP(p.precio)}</dd>
      <dt>Tipo de espacio</dt><dd>${p.tipo_espacio || "—"}</dd>
      <dt>Propietario</dt><dd>${p.propietario?.nombre || "—"}</dd>
      <dt>Correo</dt><dd>${p.propietario?.correo || "—"}</dd>
      <dt>Rating</dt><dd>★ ${p.rating ?? "—"} (${(p.resenas || []).length} reseñas)</dd>
      <dt>Disponible desde</dt><dd>${p.fecha_disponible || "—"}</dd>
      <dt>Amueblado</dt><dd>${c.amueblado ? "Sí" : "No"}</dd>
      <dt>Baño privado</dt><dd>${c.bano_privado ? "Sí" : "No"}</dd>
      <dt>Ambiente</dt><dd>${c.ambiente_hogar || "—"}</dd>
      <dt>Nº habitantes</dt><dd>${c.numero_habitantes ?? "—"}</dd>
    </dl>
    <p style="font-size:13.5px;color:#555;line-height:1.5;margin:0 0 18px;">${p.descripcion || ""}</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCerrarDetallePub">Cerrar</button>
      <button type="button" class="adm-row-btn is-danger" id="admEliminarDesdeModal">Eliminar publicación</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCerrarDetallePub").addEventListener("click", cerrarModal);
  document.getElementById("admEliminarDesdeModal").addEventListener("click", () => {
    cerrarModal();
    confirmarEliminarPublicacion(p.id, p.titulo);
  });
}

function confirmarEliminarPublicacion(id, titulo) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">¿Eliminar esta publicación?</p>
    <p class="adm-modal-sub">"${titulo}" dejará de verse en el sitio. El registro no se borra de la base de datos: puedes restaurarla después desde el filtro "Eliminadas" si fue un error.</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCancelarEliminarPub">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="admConfirmarEliminarPub">Sí, eliminar</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCancelarEliminarPub").addEventListener("click", cerrarModal);
  document.getElementById("admConfirmarEliminarPub").addEventListener("click", async () => {
    const respuesta = await fetch(`${API_BASE}/admin/publicaciones/${id}`, { method: "DELETE", headers: cabecerasAutenticadas(false) });
    if (manejarNoAutorizado(respuesta)) return;
    cerrarModal();
    mostrarToast("Publicación eliminada.");
    renderPublicaciones();
    renderResumen();
    renderReportes();
  });
}

function inicializarPublicaciones() {
  document.getElementById("admPubsSearch").addEventListener("input", debounce((e) => {
    busquedaPubActual = e.target.value.trim();
    renderPublicaciones();
  }, 300));

  document.querySelectorAll("#admPubsFiltros .prf-subtab").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll("#admPubsFiltros .prf-subtab").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroPubActual = btn.dataset.filtro;
      renderPublicaciones();
    });
  });

  renderPublicaciones();
}

// ============================================================
// ROOMIES
// ------------------------------------------------------------
// Mismo patrón que Publicaciones (HU-12): activos/eliminados/todos +
// restaurar, reutilizando las mismas clases .adm-pub-* del CSS (son
// genéricas: título, subtítulo, precio, dueño, acciones) para no
// duplicar estilos.
// ============================================================
let busquedaRoomieActual = "";
let filtroRoomieActual = "activos"; // 'activos' | 'eliminados' | 'todos' — ver Admin\PersonaRoomieController::index()

async function renderRoomies() {
  const grid = document.getElementById("admRoomiesGrid");
  const empty = document.getElementById("admRoomiesEmpty");

  const params = new URLSearchParams({ estado: filtroRoomieActual });
  if (busquedaRoomieActual) params.set("q", busquedaRoomieActual);

  const respuesta = await fetch(`${API_BASE}/admin/roomies?${params}`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const lista = datos.personas || [];

  if (!lista.length) {
    grid.innerHTML = "";
    empty.hidden = false;
    empty.textContent = filtroRoomieActual === "eliminados"
      ? "No hay perfiles de roomie eliminados."
      : "No se encontraron perfiles de roomie.";
    return;
  }
  empty.hidden = true;

  grid.innerHTML = lista.map((p) => `
    <div class="adm-pub-card">
      <img class="adm-pub-img" src="${p.img || ""}" alt="${p.nombre}"/>
      <div class="adm-pub-body">
        <div class="adm-pub-title">
          ${p.nombre}
          ${p.eliminado ? `<span class="prf-badge is-bloqueado" style="margin-left:6px;">Eliminado</span>` : ""}
        </div>
        <div class="adm-pub-sub">${p.ocupacion} · ${p.zona}, ${p.ciudad} · ★ ${p.rating ?? "—"}</div>
        <div class="adm-pub-price">${formatCOP(p.presupuesto)}</div>
        <div class="adm-pub-owner">Edad: ${p.edad ?? "—"}</div>
        <div class="adm-pub-actions">
          ${p.eliminado
            ? `<button type="button" class="adm-row-btn is-primary" data-restaurar-roomie="${p.id}">Restaurar</button>`
            : `<button type="button" class="adm-row-btn" data-ver-roomie="${p.id}">Ver perfil</button>
               <button type="button" class="adm-row-btn is-danger" data-eliminar-roomie="${p.id}" data-nombre="${p.nombre}">Eliminar</button>`}
        </div>
      </div>
    </div>`).join("");

  grid.querySelectorAll("[data-ver-roomie]").forEach((btn) => {
    btn.addEventListener("click", () => abrirModalRoomie(Number(btn.dataset.verRoomie)));
  });
  grid.querySelectorAll("[data-eliminar-roomie]").forEach((btn) => {
    btn.addEventListener("click", () => confirmarEliminarRoomie(Number(btn.dataset.eliminarRoomie), btn.dataset.nombre));
  });
  grid.querySelectorAll("[data-restaurar-roomie]").forEach((btn) => {
    btn.addEventListener("click", () => restaurarRoomie(Number(btn.dataset.restaurarRoomie)));
  });
}

async function restaurarRoomie(id) {
  const respuesta = await fetch(`${API_BASE}/admin/roomies/${id}/restaurar`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  if (!respuesta.ok) {
    const datos = await respuesta.json().catch(() => ({}));
    mostrarToast(datos.mensaje || "No se pudo restaurar el perfil.");
    return;
  }
  mostrarToast("Perfil de roomie restaurado.");
  renderRoomies();
  renderResumen();
}

// Reutiliza el endpoint público GET /api/roomies/{id} (mismo criterio
// que abrirModalPublicacion con /api/publicaciones/{id}): trae
// características, propietario y reseñas ya formateadas, sin duplicar
// ese formateo en el controlador de admin. Antes esto era un enlace
// <a href="/roomie/{id}" target="_blank"> — pero una pestaña nueva
// hereda el mismo sessionStorage, así que admin-guard.js la detectaba
// como "admin fuera de /admin" y la rebotaba de vuelta al panel. Un
// modal evita el problema de raíz: nunca se navega a otra ruta.
const ETIQUETAS_ESTADO_BUSQUEDA = {
  buscando: "Buscando roomie",
  ya_encontro: "Ya encontró roomie",
  pausado: "Búsqueda pausada",
};

async function abrirModalRoomie(id) {
  const respuesta = await fetch(`${API_BASE}/roomies/${id}`, { headers: cabecerasAutenticadas(false) });
  if (!respuesta.ok) return;
  const { persona: p } = await respuesta.json();
  const c = p.caracteristicas || {};

  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">${p.nombre}</p>
    <p class="adm-modal-sub">${p.ocupacion} · ${p.zona}, ${p.ciudad}</p>
    <img class="adm-modal-img" src="${p.img || ""}" alt="${p.nombre}"/>
    <dl class="adm-modal-grid">
      <dt>Edad</dt><dd>${p.edad ?? "—"}</dd>
      <dt>Género</dt><dd>${p.genero || "—"}</dd>
      <dt>Presupuesto</dt><dd>${formatCOP(p.presupuesto)}</dd>
      <dt>Estado de búsqueda</dt><dd>${ETIQUETAS_ESTADO_BUSQUEDA[p.estado_busqueda] || p.estado_busqueda || "—"}</dd>
      <dt>Rating</dt><dd>★ ${p.rating ?? "—"} (${(p.resenas || []).length} reseñas)</dd>
      <dt>Ambiente preferido</dt><dd>${c.ambiente_preferido || "—"}</dd>
      <dt>Horario</dt><dd>${c.horario || "—"}</dd>
      <dt>Busca mudarse</dt><dd>${c.fecha_mudanza || "—"}</dd>
      <dt>Fumador</dt><dd>${c.fumador ? "Sí" : "No"}</dd>
      <dt>Tiene mascota</dt><dd>${c.tiene_mascota ? "Sí" : "No"}</dd>
    </dl>
    <p style="font-size:13.5px;color:#555;line-height:1.5;margin:0 0 18px;">${p.descripcion || ""}</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCerrarDetalleRoomie">Cerrar</button>
      <button type="button" class="adm-row-btn is-danger" id="admEliminarRoomieDesdeModal">Eliminar perfil</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCerrarDetalleRoomie").addEventListener("click", cerrarModal);
  document.getElementById("admEliminarRoomieDesdeModal").addEventListener("click", () => {
    cerrarModal();
    confirmarEliminarRoomie(p.id, p.nombre);
  });
}

function confirmarEliminarRoomie(id, nombre) {
  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">¿Eliminar el perfil de ${nombre}?</p>
    <p class="adm-modal-sub">Dejará de verse en la búsqueda de roomies. El registro no se borra de la base de datos: puedes restaurarlo después desde el filtro "Eliminados" si fue un error.</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCancelarEliminarRoomie">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="admConfirmarEliminarRoomie">Sí, eliminar</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCancelarEliminarRoomie").addEventListener("click", cerrarModal);
  document.getElementById("admConfirmarEliminarRoomie").addEventListener("click", async () => {
    const respuesta = await fetch(`${API_BASE}/admin/roomies/${id}`, { method: "DELETE", headers: cabecerasAutenticadas(false) });
    if (manejarNoAutorizado(respuesta)) return;
    cerrarModal();
    mostrarToast("Perfil de roomie eliminado.");
    renderRoomies();
    renderResumen();
    renderReportes();
  });
}

function inicializarRoomies() {
  document.getElementById("admRoomiesSearch").addEventListener("input", debounce((e) => {
    busquedaRoomieActual = e.target.value.trim();
    renderRoomies();
  }, 300));

  document.querySelectorAll("#admRoomiesFiltros .prf-subtab").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll("#admRoomiesFiltros .prf-subtab").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroRoomieActual = btn.dataset.filtro;
      renderRoomies();
    });
  });

  renderRoomies();
}

// ============================================================
// REPORTES (HU-13)
// ============================================================
let filtroReporteActual = "pendiente";

function actualizarBadgeReportes(pendientes) {
  const badge = document.getElementById("admReportesBadge");
  badge.hidden = pendientes === 0;
  badge.textContent = pendientes;
}

async function renderReportes() {
  const cont = document.getElementById("admReportesList");
  const empty = document.getElementById("admReportesEmpty");

  const respuesta = await fetch(`${API_BASE}/admin/reportes?estado=${filtroReporteActual}`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const reportes = datos.reportes || [];
  actualizarBadgeReportes(datos.pendientes || 0);

  if (!reportes.length) {
    cont.innerHTML = "";
    empty.hidden = false;
    return;
  }
  empty.hidden = true;

  const ETIQUETAS_ESTADO = { pendiente: "Pendiente", resuelto: "Resuelto", descartado: "Descartado" };
  const ETIQUETAS_TIPO = { publicacion: "Publicación", persona: "Roomie", usuario: "Usuario" };

  cont.innerHTML = reportes.map((r) => `
    <div class="adm-reporte-card">
      <div class="adm-reporte-icon">
        <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01"/><path d="M10.3 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L14.7 3.86a2 2 0 0 0-3.4 0z"/></svg>
      </div>
      <div class="adm-reporte-body">
        <div class="adm-reporte-top">
          <span class="adm-reporte-motivo">${r.motivo}</span>
          <span class="adm-reporte-tipo">${ETIQUETAS_TIPO[r.tipo] || r.tipo}</span>
          <span class="prf-badge is-${r.estado}">${ETIQUETAS_ESTADO[r.estado]}</span>
        </div>
        <p class="adm-reporte-desc">${r.descripcion || ""}</p>
        <div class="adm-reporte-meta">${ETIQUETAS_TIPO[r.tipo] || r.tipo}: ${r.objetivo_titulo || "—"} · Reportado por ${r.reportante} · ${formatFecha(r.fecha)}</div>
      </div>
      <div class="adm-reporte-side">
        ${r.estado === "pendiente" ? `
          <div class="adm-reporte-actions">
            <button type="button" class="adm-row-btn is-danger" data-resolver="${r.id}">Tomar acción</button>
            <button type="button" class="adm-row-btn" data-descartar="${r.id}">Descartar</button>
          </div>` : `<span class="adm-reporte-none">Sin acciones pendientes</span>`}
      </div>
    </div>`).join("");

  cont.querySelectorAll("[data-resolver]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const r = reportes.find((x) => x.id === Number(btn.dataset.resolver));
      if (r) abrirModalResolverReporte(r);
    });
  });
  cont.querySelectorAll("[data-descartar]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const respuesta = await fetch(`${API_BASE}/admin/reportes/${btn.dataset.descartar}/descartar`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
      if (manejarNoAutorizado(respuesta)) return;
      mostrarToast("Reporte descartado.");
      renderReportes();
      renderResumen();
    });
  });
}

function abrirModalResolverReporte(r) {
  const ACCIONES = {
    publicacion: "Eliminar la publicación reportada",
    usuario: "Bloquear al usuario reportado",
    persona: "Eliminar el perfil reportado",
  };
  const accionLabel = ACCIONES[r.tipo] || "Marcar como resuelto";

  abrirModal(`
    <button class="adm-modal-close" type="button" id="admModalCloseBtn">&times;</button>
    <p class="adm-modal-title">Resolver reporte</p>
    <p class="adm-modal-sub">${r.motivo} — ${r.objetivo_titulo || "—"}</p>
    <p style="font-size:13.5px;color:#555;line-height:1.5;margin:0 0 18px;">${r.descripcion || ""}</p>
    <div class="adm-modal-buttons">
      <button type="button" class="adm-row-btn" id="admCancelarResolver">Cancelar</button>
      <button type="button" class="adm-row-btn is-danger" id="admAplicarAccion">${accionLabel}</button>
    </div>
  `);
  document.getElementById("admModalCloseBtn").addEventListener("click", cerrarModal);
  document.getElementById("admCancelarResolver").addEventListener("click", cerrarModal);
  document.getElementById("admAplicarAccion").addEventListener("click", async () => {
    const respuesta = await fetch(`${API_BASE}/admin/reportes/${r.id}/resolver`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
    if (manejarNoAutorizado(respuesta)) return;
    cerrarModal();
    mostrarToast("Reporte resuelto.");
    renderReportes();
    renderPublicaciones();
    renderRoomies();
    renderUsuarios();
    renderResumen();
  });
}

function inicializarReportes() {
  document.querySelectorAll("#admReportesFiltros .prf-subtab").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll("#admReportesFiltros .prf-subtab").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroReporteActual = btn.dataset.filtro;
      renderReportes();
    });
  });
  renderReportes();
}

// ============================================================
// MENSAJES DE CONTACTO
// ------------------------------------------------------------
// Mismo patrón que la sección de Reportes: subtabs de estado +
// tarjetas reutilizando las clases .adm-reporte-* ya existentes
// (mismo layout: ícono, título, etiqueta, cuerpo, meta, acción).
// ============================================================
let filtroMensajeActual = "pendiente";

function actualizarBadgeMensajes(pendientes) {
  const badge = document.getElementById("admMensajesBadge");
  badge.hidden = pendientes === 0;
  badge.textContent = pendientes;
}

async function renderMensajes() {
  const cont = document.getElementById("admMensajesList");
  const empty = document.getElementById("admMensajesEmpty");

  const respuesta = await fetch(`${API_BASE}/admin/mensajes-contacto?estado=${filtroMensajeActual}`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const mensajes = datos.mensajes || [];
  actualizarBadgeMensajes(datos.pendientes || 0);

  if (!mensajes.length) {
    cont.innerHTML = "";
    empty.hidden = false;
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
          <span class="adm-reporte-motivo">${m.nombre}</span>
          <span class="adm-reporte-tipo">${m.asunto_label}</span>
          <span class="prf-badge ${m.atendido ? "is-resuelto" : "is-pendiente"}">${m.atendido ? "Atendido" : "Pendiente"}</span>
        </div>
        <p class="adm-reporte-desc">${m.mensaje}</p>
        <div class="adm-reporte-meta">${m.correo} · ${formatFecha(m.fecha)}</div>
      </div>
      <div class="adm-reporte-side">
        ${!m.atendido ? `
          <div class="adm-reporte-actions">
            <button type="button" class="adm-row-btn is-primary" data-atender="${m.id}">Marcar atendido</button>
          </div>` : `<span class="adm-reporte-none">Sin acciones pendientes</span>`}
      </div>
    </div>`).join("");

  cont.querySelectorAll("[data-atender]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      const respuesta = await fetch(`${API_BASE}/admin/mensajes-contacto/${btn.dataset.atender}/atender`, { method: "PATCH", headers: cabecerasAutenticadas(false) });
      if (manejarNoAutorizado(respuesta)) return;
      mostrarToast("Mensaje marcado como atendido.");
      renderMensajes();
    });
  });
}

function inicializarMensajes() {
  document.querySelectorAll("#admMensajesFiltros .prf-subtab").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll("#admMensajesFiltros .prf-subtab").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      filtroMensajeActual = btn.dataset.filtro;
      renderMensajes();
    });
  });
  renderMensajes();
}

// ============================================================
// GRÁFICA: REPORTES POR DÍA (últimos 14 días)
// ------------------------------------------------------------
// El backend (GET /api/admin/reportes/grafica) ya entrega el conteo
// agrupado por día y tipo; aquí solo se dibuja el SVG, igual que
// antes, sin depender de ninguna librería externa.
// ============================================================
async function renderGraficaReportes() {
  const cont = document.getElementById("admGraficaReportes");

  const respuesta = await fetch(`${API_BASE}/admin/reportes/grafica`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const { dias } = await respuesta.json();

  const claves = Object.keys(dias);
  const totalReportes = claves.reduce((acc, k) => acc + dias[k].publicacion + dias[k].otros, 0);

  if (!totalReportes) {
    cont.innerHTML = `<p class="adm-chart-empty">Aún no hay reportes registrados en este rango de fechas.</p>`;
    return;
  }

  const maxValor = Math.max(1, ...claves.map((k) => Math.max(dias[k].publicacion, dias[k].otros)));

  const anchoGrupo = 34;
  const espacio = 12;
  const altoChart = 170;
  const altoBarras = altoChart - 30;
  const anchoChart = claves.length * (anchoGrupo + espacio) + espacio;

  const barras = claves.map((key, i) => {
    const c = dias[key];
    const x = espacio + i * (anchoGrupo + espacio);
    const barW = (anchoGrupo - 4) / 2;

    const hPub = Math.round((c.publicacion / maxValor) * altoBarras);
    const hOtros = Math.round((c.otros / maxValor) * altoBarras);
    const yPub = altoChart - hPub - 20;
    const yOtros = altoChart - hOtros - 20;

    const label = new Date(key + "T00:00:00").toLocaleDateString("es-CO", { day: "2-digit", month: "short" }).replace(".", "");

    return `
      <g>
        ${c.publicacion ? `<rect x="${x}" y="${yPub}" width="${barW}" height="${Math.max(hPub, 2)}" rx="3" fill="#1a9ecf"><title>${c.publicacion} publicación(es) reportada(s) el ${label}</title></rect>` : ""}
        ${c.otros ? `<rect x="${x + barW + 4}" y="${yOtros}" width="${barW}" height="${Math.max(hOtros, 2)}" rx="3" fill="#e08a1e"><title>${c.otros} usuario(s)/roomie(s) reportado(s) el ${label}</title></rect>` : ""}
        <text x="${x + anchoGrupo / 2}" y="${altoChart - 4}" font-size="9" fill="#999" text-anchor="middle">${label}</text>
      </g>`;
  }).join("");

  cont.innerHTML = `
    <div class="adm-chart-legend">
      <span><i style="background:#1a9ecf"></i> Publicaciones</span>
      <span><i style="background:#e08a1e"></i> Usuarios y roomies</span>
    </div>
    <svg viewBox="0 0 ${anchoChart} ${altoChart}" class="adm-chart-svg" preserveAspectRatio="xMinYMin meet">
      ${barras}
    </svg>`;
}

// ============================================================
// RESUMEN
// ------------------------------------------------------------
// 'buscadores'/'oferentes' ya no existen: el backend ahora entrega
// 'clientes' y 'administradores' (ver AdminController::resumen()).
// ============================================================
async function renderResumen() {
  const respuesta = await fetch(`${API_BASE}/admin/resumen`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const s = await respuesta.json();

  const stats = [
    { label: "Usuarios registrados", value: s.usuarios_registrados },
    { label: "Clientes", value: s.clientes },
    { label: "Administradores", value: s.administradores },
    { label: "Usuarios bloqueados", value: s.usuarios_bloqueados, alert: s.usuarios_bloqueados > 0 },
    { label: "Publicaciones activas", value: s.publicaciones_activas },
    { label: "Roomies publicados", value: s.roomies_publicados },
    { label: "Reportes pendientes", value: s.reportes_pendientes, alert: s.reportes_pendientes > 0 },
    { label: "Mensajes de contacto pendientes", value: s.mensajes_contacto_pendientes, alert: s.mensajes_contacto_pendientes > 0 },
  ];

  document.getElementById("admStatsGrid").innerHTML = stats.map((s) => `
    <div class="adm-stat-card ${s.alert ? "is-alert" : ""}">
      <div class="adm-stat-value">${s.value}</div>
      <div class="adm-stat-label">${s.label}</div>
    </div>`).join("");

  renderGraficaReportes();
}

// ============================================================
// INICIALIZACIÓN DEL PANEL
// ============================================================
renderResumen();
inicializarUsuarios();
inicializarPublicaciones();
inicializarRoomies();
inicializarReportes();
inicializarMensajes();
activarPanelPorHash();
