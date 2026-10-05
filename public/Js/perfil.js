// ============================================================
// ROOMMATCH – lógica de la página PERFIL (ajustes de cuenta)
// ------------------------------------------------------------
// Misma UX que la versión original, pero contra la API real:
//   - Datos personales -> PUT /api/usuario
//   - Foto de perfil -> POST /api/usuario/foto
//   - Convivencia -> PUT /api/usuario/convivencia
//   - Favoritos (habitaciones) -> GET /api/mis-favoritos/habitaciones
//   - Reservas -> GET /api/mis-reservas + calificar/reportar
//   - Mis citas -> GET /api/mis-citas + calificar/reportar
//   - Notificaciones -> GET /api/notificaciones + marcar-leidas
// El navbar (avatar/nombre/badge) ya lo maneja nav.js, compartido en
// todas las páginas — este archivo ya NO lo toca directamente.
// Favoritos->Roomies queda con estado vacío: pendiente de migrar.
//
// CAMBIO: token/usuario ahora se leen y escriben en sessionStorage
// (antes localStorage), para que la sesión se cierre sola al cerrar
// la pestaña/ventana en vez de persistir indefinidamente.
//
// CAMBIO (roles): con el ENUM tipo_usuario reducido a cliente/admin,
// el campo "Tipo de cuenta" se quitó de Datos personales (ya no se
// muestra ni se edita), y el panel "Información convivencial" ya NO
// se ramifica por tipoUsuario buscador/oferente — ahora muestra
// AMBOS bloques (estilo de vida + reglas del hogar) en pestañas,
// disponibles para todo cliente sin importar su rol.
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
let usuarioActual = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");

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
function guardarUsuarioLocal(usuario) {
  usuarioActual = usuario;
  sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(usuario));
}

document.getElementById("logoutBtnSidebar").addEventListener("click", () => {
  window.RoommatchNav.cerrarSesion();
});

// ---------- FOTO DE PERFIL ----------
const FOTO_MAX_LADO = 240;
const FOTO_CALIDAD = 0.85;
const FOTO_MAX_PESO_ORIGINAL = 5 * 1024 * 1024;

function pintarIdentidad() {
  const avatarBig = document.getElementById("prfAvatarBig");
  const quitarBtn = document.getElementById("quitarFotoBtn");
  const inicial = (usuarioActual.nombre || "U").trim().charAt(0).toUpperCase();

  if (usuarioActual.fotoPerfil) {
    avatarBig.innerHTML = `<img src="${usuarioActual.fotoPerfil}" alt="${usuarioActual.nombre}"/>`;
    quitarBtn.hidden = false;
  } else {
    avatarBig.textContent = inicial;
    quitarBtn.hidden = true;
  }
}
pintarIdentidad();

function mostrarErrorFoto(mensaje) {
  const el = document.getElementById("fotoError");
  el.textContent = mensaje;
  el.classList.toggle("is-visible", Boolean(mensaje));
}

function comprimirImagen(archivo) {
  return new Promise((resolve, reject) => {
    const lector = new FileReader();
    lector.onerror = () => reject(new Error("No se pudo leer el archivo."));
    lector.onload = () => {
      const img = new Image();
      img.onerror = () => reject(new Error("El archivo no es una imagen válida."));
      img.onload = () => {
        const lado = Math.min(FOTO_MAX_LADO, Math.max(img.width, img.height));
        const canvas = document.createElement("canvas");
        canvas.width = lado;
        canvas.height = lado;
        const ctx = canvas.getContext("2d");
        const escala = Math.max(lado / img.width, lado / img.height);
        const anchoEscalado = img.width * escala;
        const altoEscalado = img.height * escala;
        const dx = (lado - anchoEscalado) / 2;
        const dy = (lado - altoEscalado) / 2;
        ctx.drawImage(img, dx, dy, anchoEscalado, altoEscalado);
        resolve(canvas.toDataURL("image/jpeg", FOTO_CALIDAD));
      };
      img.src = lector.result;
    };
    lector.readAsDataURL(archivo);
  });
}

document.getElementById("fotoInput").addEventListener("change", async (e) => {
  const archivo = e.target.files[0];
  e.target.value = "";
  if (!archivo) return;

  mostrarErrorFoto("");
  if (!archivo.type.startsWith("image/")) {
    mostrarErrorFoto("Selecciona un archivo de imagen (JPG, PNG o WEBP).");
    return;
  }
  if (archivo.size > FOTO_MAX_PESO_ORIGINAL) {
    mostrarErrorFoto("La imagen pesa demasiado. Usa una de menos de 5MB.");
    return;
  }

  try {
    const dataUrl = await comprimirImagen(archivo);
    const respuesta = await fetch(`${API_BASE}/usuario/foto`, {
      method: "POST",
      headers: cabecerasAutenticadas(),
      body: JSON.stringify({ foto: dataUrl }),
    });
    if (manejarNoAutorizado(respuesta)) return;
    const datos = await respuesta.json();
    if (!respuesta.ok) { mostrarErrorFoto(datos.mensaje || "No se pudo subir la foto."); return; }
    guardarUsuarioLocal(datos.usuario);
    pintarIdentidad();
    window.RoommatchNav.renderNavbar();
  } catch (err) {
    mostrarErrorFoto("No se pudo procesar la imagen. Intenta con otra.");
  }
});

document.getElementById("quitarFotoBtn").addEventListener("click", async () => {
  const respuesta = await fetch(`${API_BASE}/usuario/foto`, {
    method: "POST",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify({ foto: null }),
  });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  guardarUsuarioLocal(datos.usuario);
  pintarIdentidad();
  window.RoommatchNav.renderNavbar();
});

// ============================================================
// NAVEGACIÓN ENTRE PANELES (sidebar)
// ============================================================
document.querySelectorAll(".prf-nav-item[data-panel]").forEach((btn) => {
  btn.addEventListener("click", () => {
    document.querySelectorAll(".prf-nav-item[data-panel]").forEach((b) => b.classList.remove("active"));
    document.querySelectorAll(".prf-panel").forEach((p) => p.classList.remove("active"));
    btn.classList.add("active");
    document.getElementById(btn.dataset.panel).classList.add("active");

    if (btn.dataset.panel === "panelNotificaciones") {
      marcarNotificacionesLeidas();
    }
  });
});

function activarPanelPorHash() {
  const panelId = window.location.hash.replace("#", "");
  if (!panelId) return;
  const btn = document.querySelector(`.prf-nav-item[data-panel="${panelId}"]`);
  if (btn) btn.click();
}
window.addEventListener("hashchange", activarPanelPorHash);

// ============================================================
// HELPERS COMPARTIDOS
// ============================================================
function formatCOP(n) {
  return "$" + Math.round(n).toLocaleString("es-CO") + " COP";
}
function formatFechaCorta(fechaISO) {
  if (!fechaISO) return "Sin definir";
  return new Date(fechaISO + "T00:00:00").toLocaleDateString("es-CO", { day: "2-digit", month: "short", year: "numeric" });
}
function formatFechaHora(fechaISO) {
  const d = new Date(fechaISO);
  const fecha = d.toLocaleDateString("es-CO", { day: "2-digit", month: "short", year: "numeric" });
  const hora = d.toLocaleTimeString("es-CO", { hour: "2-digit", minute: "2-digit" });
  return `${fecha} · ${hora}`;
}
function formatHora12(horaISO) {
  if (!horaISO) return null;
  const [h, m] = horaISO.split(":").map(Number);
  const periodo = h >= 12 ? "p. m." : "a. m.";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${String(m).padStart(2, "0")} ${periodo}`;
}
const ETIQUETAS_DURACION = { "1-3": "1 a 3 meses", "3-6": "3 a 6 meses", "6-12": "6 a 12 meses", "12+": "Más de 12 meses" };
// El backend usa 'aceptada' (no 'aprobada') tanto para reservas como para citas.
const ETIQUETAS_BADGE = { pendiente: "Pendiente", aceptada: "Aceptada", rechazada: "Rechazada", cancelada: "Cancelada" };
const ETIQUETAS_TIPO_ENCUENTRO = { sitio: "En el lugar", publico: "Sitio público", otro: "Otro" };

const MOTIVOS_REPORTE_PUBLICACION = [
  "El lugar no correspondía a lo publicado",
  "El oferente no se presentó a la visita",
  "Comportamiento inapropiado durante la visita",
  "Cobro de valores no acordados",
  "Otro",
];
const MOTIVOS_REPORTE_ROOMIE = [
  "No se presentó a la cita",
  "Comportamiento inapropiado durante el encuentro",
  "El perfil no correspondía a la persona real",
  "Mensajes ofensivos o acoso",
  "Otro",
];

// ============================================================
// 1) DATOS PERSONALES
// ============================================================
const CAMPOS_PERSONALES = [
  { key: "nombre", label: "Nombre completo", type: "text", placeholder: "Tu nombre completo" },
  { key: "email", label: "Email", type: "email", placeholder: "correo@ejemplo.com" },
  { key: "telefono", label: "Teléfono", type: "tel", placeholder: "+57 300 123 4567" },
  { key: "direccion", label: "Dirección / Localidad", type: "text", placeholder: "Ej. Kennedy, Bogotá" },
  {
    key: "genero", label: "Género", type: "select", placeholder: "",
    options: [
      { value: "masculino", label: "Masculino" },
      { value: "femenino", label: "Femenino" },
      { value: "otro", label: "Otro" },
      { value: "prefiero_no_decir", label: "Prefiero no decir" },
    ],
  },
];

function etiquetaOpcion(campo, valor) {
  const opcion = campo.options?.find((o) => o.value === valor);
  return opcion ? opcion.label : valor;
}

function renderDatosPersonales() {
  const cont = document.getElementById("datosPersonalesList");
  cont.innerHTML = CAMPOS_PERSONALES.map((campo) => {
    const valor = usuarioActual[campo.key];
    const textoMostrado = valor ? (campo.type === "select" ? etiquetaOpcion(campo, valor) : valor) : "Sin definir";
    return `
      <div class="prf-field-row" data-field="${campo.key}">
        <span class="prf-field-label">${campo.label}</span>
        <span class="prf-field-value${valor ? "" : " is-empty"}" data-display>${textoMostrado}</span>
        <button type="button" class="prf-edit-btn" data-action="edit">Edit</button>
      </div>`;
  }).join("");

  cont.querySelectorAll('.prf-field-row [data-action="edit"]').forEach((btn) => {
    btn.addEventListener("click", () => activarEdicionCampo(btn.closest(".prf-field-row")));
  });
}

function activarEdicionCampo(row) {
  const key = row.dataset.field;
  const campo = CAMPOS_PERSONALES.find((c) => c.key === key);
  const display = row.querySelector("[data-display]");
  const btn = row.querySelector('[data-action="edit"]');
  const valorActual = usuarioActual[key] || "";

  let inputHtml;
  if (campo.type === "select") {
    inputHtml = `<select class="prf-field-select" data-input>
      <option value="">Selecciona una opción</option>
      ${campo.options.map((o) => `<option value="${o.value}"${o.value === valorActual ? " selected" : ""}>${o.label}</option>`).join("")}
    </select>`;
  } else {
    inputHtml = `<input class="prf-field-input" data-input type="${campo.type}" placeholder="${campo.placeholder}" value="${valorActual.toString().replace(/"/g, "&quot;")}"/>`;
  }

  display.outerHTML = `${inputHtml}<div class="prf-field-error" data-error></div>`;
  btn.textContent = "Guardar";
  btn.classList.add("is-save");
  btn.dataset.action = "save";

  row.querySelector("[data-input]").focus();
  btn.replaceWith(btn.cloneNode(true));
  row.querySelector('[data-action="save"]').addEventListener("click", () => guardarCampo(row));
}

async function guardarCampo(row) {
  const key = row.dataset.field;
  const input = row.querySelector("[data-input]");
  const errorEl = row.querySelector("[data-error]");
  const valor = input.value.trim();

  let invalido = false, mensajeError = "";
  if (key === "nombre" && valor.length < 3) { invalido = true; mensajeError = "Ingresa tu nombre completo."; }
  if (key === "email" && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) { invalido = true; mensajeError = "Ingresa un correo válido."; }
  if (key === "telefono" && valor.replace(/\D/g, "").length < 7) { invalido = true; mensajeError = "Ingresa un teléfono válido."; }
  if (key === "direccion" && valor.length < 3) { invalido = true; mensajeError = "Ingresa una dirección válida."; }
  if (key === "genero" && !valor) { invalido = true; mensajeError = "Selecciona una opción."; }

  if (invalido) {
    errorEl.textContent = mensajeError;
    errorEl.classList.add("is-visible");
    return;
  }

  const payload = {
    nombre: key === "nombre" ? valor : usuarioActual.nombre,
    email: key === "email" ? valor : usuarioActual.email,
    telefono: key === "telefono" ? valor : (usuarioActual.telefono || ""),
    direccion: key === "direccion" ? valor : usuarioActual.direccion,
    genero: key === "genero" ? valor : usuarioActual.genero,
  };

  const respuesta = await fetch(`${API_BASE}/usuario`, {
    method: "PUT",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify(payload),
  });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();

  if (!respuesta.ok) {
    errorEl.textContent = datos.mensaje || "No se pudo guardar. Verifica el dato.";
    errorEl.classList.add("is-visible");
    return;
  }

  guardarUsuarioLocal(datos.usuario);
  if (key === "nombre" || key === "email") window.RoommatchNav.renderNavbar();

  renderDatosPersonales();
}

// ============================================================
// 2) INFORMACIÓN CONVIVENCIAL
// ------------------------------------------------------------
// Ya no depende de tipoUsuario (buscador/oferente): todo cliente ve
// ambos bloques —"Mi estilo de vida" y "Reglas de mi hogar"— en
// pestañas, porque cualquier cliente puede tanto buscar como ofrecer
// espacio. Los datos se siguen guardando en el mismo JSON
// preferenciasConvivencia; los dos bloques comparten esa misma bolsa
// de claves, así que llenar uno no borra el otro.
// ============================================================
const ETIQUETAS_ENUM_CONV = {
  estudiantes: "Estudiantes", profesionales: "Profesionales", otro: "Otro",
  diurno: "Diurno", nocturno: "Nocturno", mixto: "Mixto",
  corto: "Corto plazo", largo: "Largo plazo",
  inmediata: "Inmediata", "1mes": "En un mes", flexible: "Flexible",
};

function mostrarNotaGuardado() {
  const nota = document.getElementById("convivenciaSaveNote");
  nota.classList.add("show");
  clearTimeout(mostrarNotaGuardado._t);
  mostrarNotaGuardado._t = setTimeout(() => nota.classList.remove("show"), 1400);
}

async function actualizarPreferencia(campo, valor) {
  usuarioActual.preferenciasConvivencia = usuarioActual.preferenciasConvivencia || {};
  usuarioActual.preferenciasConvivencia[campo] = valor;
  sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(usuarioActual));

  const respuesta = await fetch(`${API_BASE}/usuario/convivencia`, {
    method: "PUT",
    headers: cabecerasAutenticadas(),
    body: JSON.stringify({ [campo]: valor }),
  });
  if (manejarNoAutorizado(respuesta)) return;
  if (respuesta.ok) mostrarNotaGuardado();
}

function toggleRow(campo, label, valorActual) {
  return `
    <div class="prf-toggle-row">
      <span class="prf-toggle-label">${label}</span>
      <label class="prf-switch">
        <input type="checkbox" data-conv-toggle="${campo}" ${valorActual ? "checked" : ""}/>
        <span class="prf-switch-track"></span>
      </label>
    </div>`;
}
function selectField(campo, label, opciones, valorActual) {
  return `
    <div class="prf-conv-field">
      <label>${label}</label>
      <select data-conv-select="${campo}">
        <option value="">Selecciona una opción</option>
        ${Object.entries(opciones).map(([v, txt]) => `<option value="${v}"${v === valorActual ? " selected" : ""}>${txt}</option>`).join("")}
      </select>
    </div>`;
}
function numberField(campo, label, valorActual, placeholder) {
  return `
    <div class="prf-conv-field">
      <label>${label}</label>
      <input type="number" min="0" data-conv-number="${campo}" value="${valorActual ?? ""}" placeholder="${placeholder}"/>
    </div>`;
}
function textField(campo, label, valorActual, placeholder) {
  return `
    <div class="prf-conv-field">
      <label>${label}</label>
      <input type="text" data-conv-text="${campo}" value="${valorActual ?? ""}" placeholder="${placeholder}"/>
    </div>`;
}

function bloqueEstiloVida(p) {
  return `
    <div class="prf-conv-group">
      <p class="prf-conv-group-title">Estilo de vida</p>
      <div class="prf-toggle-grid">
        ${toggleRow("fumador", "Fumador/a", p.fumador)}
        ${toggleRow("tiene_mascota", "Tiene mascota propia", p.tiene_mascota)}
        ${toggleRow("ordenado", "Ordenado/a", p.ordenado)}
        ${toggleRow("sociable", "Sociable", p.sociable)}
        ${toggleRow("madrugador", "Madrugador/a", p.madrugador)}
        ${toggleRow("trasnochador", "Trasnochador/a", p.trasnochador)}
      </div>
    </div>
    <div class="prf-conv-group">
      <p class="prf-conv-group-title">Qué buscas</p>
      <div class="prf-toggle-grid">
        ${toggleRow("quiere_amueblado", "Busca lugar amueblado", p.quiere_amueblado)}
        ${toggleRow("quiere_bano_privado", "Busca baño privado", p.quiere_bano_privado)}
        ${toggleRow("cerca_transporte_publico", "Cerca a transporte público", p.cerca_transporte_publico)}
        ${toggleRow("cerca_universidad", "Cerca a universidad", p.cerca_universidad)}
      </div>
      ${selectField("ambiente_preferido", "Ambiente que prefieres", ETIQUETAS_ENUM_CONV, p.ambiente_preferido)}
      ${selectField("horario", "Tu horario habitual", { diurno: "Diurno", nocturno: "Nocturno", mixto: "Mixto" }, p.horario)}
      ${selectField("tiempo_busqueda", "Tiempo de búsqueda", { corto: "Corto plazo", largo: "Largo plazo" }, p.tiempo_busqueda)}
      ${selectField("fecha_mudanza", "Fecha de mudanza", { inmediata: "Inmediata", "1mes": "En un mes", flexible: "Flexible" }, p.fecha_mudanza)}
      ${numberField("presupuesto_max", "Presupuesto máximo mensual", p.presupuesto_max, "Ej. 500000")}
    </div>`;
}

function bloqueReglasHogar(p) {
  return `
    <div class="prf-conv-group">
      <p class="prf-conv-group-title">Ambiente del hogar</p>
      ${selectField("ambiente_hogar", "Ambiente de la casa", ETIQUETAS_ENUM_CONV, p.ambiente_hogar)}
      ${numberField("numero_habitantes", "Número de habitantes actuales", p.numero_habitantes, "Ej. 2")}
      ${textField("horario_entrada", "Horario de entrada permitido", p.horario_entrada, "Ej. Hasta las 11:00pm")}
      ${textField("horario_silencio", "Horario de silencio", p.horario_silencio, "Ej. 10:00pm - 7:00am")}
    </div>
    <div class="prf-conv-group">
      <p class="prf-conv-group-title">Reglas de convivencia</p>
      <div class="prf-toggle-grid">
        ${toggleRow("permite_mascotas", "Permite mascotas", p.permite_mascotas)}
        ${toggleRow("permite_fumar", "Permite fumar", p.permite_fumar)}
        ${toggleRow("permite_fiestas", "Permite fiestas/reuniones", p.permite_fiestas)}
        ${toggleRow("permite_visitas", "Permite visitas", p.permite_visitas)}
        ${toggleRow("permite_parejas", "Permite parejas", p.permite_parejas)}
      </div>
    </div>`;
}

function adjuntarListenersConvivencia(cont) {
  cont.querySelectorAll("[data-conv-toggle]").forEach((el) => el.addEventListener("change", () => actualizarPreferencia(el.dataset.convToggle, el.checked)));
  cont.querySelectorAll("[data-conv-select]").forEach((el) => el.addEventListener("change", () => actualizarPreferencia(el.dataset.convSelect, el.value || null)));
  cont.querySelectorAll("[data-conv-number]").forEach((el) => el.addEventListener("change", () => actualizarPreferencia(el.dataset.convNumber, el.value ? Number(el.value) : null)));
  cont.querySelectorAll("[data-conv-text]").forEach((el) => el.addEventListener("change", () => actualizarPreferencia(el.dataset.convText, el.value.trim() || null)));
}

function renderConvivencia() {
  const p = usuarioActual.preferenciasConvivencia || {};
  const subtitulo = document.getElementById("convivenciaSubtitle");
  const cont = document.getElementById("convivenciaList");

  subtitulo.textContent = "Cuéntanos tu estilo de vida y, si ofreces un espacio, las reglas de tu hogar";

  cont.innerHTML = `
    <div class="prf-subtabs" id="convSubtabs">
      <button class="prf-subtab active" data-conv-tab="estilo">Mi estilo de vida</button>
      <button class="prf-subtab" data-conv-tab="hogar">Reglas de mi hogar</button>
    </div>
    <div class="prf-conv-panel" id="convPanelEstilo">${bloqueEstiloVida(p)}</div>
    <div class="prf-conv-panel" id="convPanelHogar" hidden>${bloqueReglasHogar(p)}</div>
  `;

  cont.querySelectorAll("[data-conv-tab]").forEach((btn) => {
    btn.addEventListener("click", () => {
      cont.querySelectorAll("[data-conv-tab]").forEach((b) => b.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById("convPanelEstilo").hidden = btn.dataset.convTab !== "estilo";
      document.getElementById("convPanelHogar").hidden = btn.dataset.convTab !== "hogar";
    });
  });

  adjuntarListenersConvivencia(cont);
}

// ============================================================
// 3) FAVORITOS
// ============================================================
async function cargarFavoritosHabitaciones() {
  const respuesta = await fetch(`${API_BASE}/mis-favoritos/habitaciones`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return [];
  const datos = await respuesta.json();
  return datos.favoritos || [];
}

async function quitarFavoritoHabitacion(id) {
  const respuesta = await fetch(`${API_BASE}/publicaciones/${id}/favorito`, { method: "POST", headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  await renderFavoritos();
}

async function cargarFavoritosRoomies() {
  const respuesta = await fetch(`${API_BASE}/mis-favoritos/roomies`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return [];
  const datos = await respuesta.json();
  return datos.favoritos || [];
}

async function quitarFavoritoRoomie(id) {
  const respuesta = await fetch(`${API_BASE}/roomies/${id}/favorito`, { method: "POST", headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  await renderFavoritos();
}

async function renderFavoritos() {
  const gridHab = document.getElementById("favHabitacionesGrid");
  const gridRoom = document.getElementById("favRoomiesGrid");
  const [favoritosHab, favoritosRoom] = await Promise.all([
    cargarFavoritosHabitaciones(),
    cargarFavoritosRoomies(),
  ]);

  gridHab.innerHTML = favoritosHab.length ? favoritosHab.map((f) => `
    <div class="prf-fav-card">
      <div class="prf-fav-img-wrap"><img src="${f.img || ""}" alt="${f.tipo} ${f.zona}"/></div>
      <button type="button" class="prf-fav-remove" data-remove-hab="${f.id}" title="Quitar de favoritos">♥</button>
      <div class="prf-fav-body">
        <div class="prf-fav-title">${f.tipo}</div>
        <div class="prf-fav-sub">${f.zona}</div>
        <div class="prf-fav-price">${formatCOP(f.precio)}</div>
      </div>
    </div>`).join("") : `<div class="prf-empty">Aún no has guardado habitaciones. <a href="/dashboard">Explora habitaciones →</a></div>`;

  gridRoom.innerHTML = favoritosRoom.length ? favoritosRoom.map((f) => `
    <div class="prf-fav-card">
      <div class="prf-fav-img-wrap"><img src="${f.img || ""}" alt="${f.nombre}"/></div>
      <button type="button" class="prf-fav-remove" data-remove-roomie="${f.id}" title="Quitar de favoritos">♥</button>
      <div class="prf-fav-body">
        <div class="prf-fav-title">${f.nombre}</div>
        <div class="prf-fav-sub">${f.zona}${f.zona && f.ciudad ? ", " : ""}${f.ciudad || ""}</div>
        <div class="prf-fav-price">${formatCOP(f.presupuesto)}</div>
      </div>
    </div>`).join("") : `<div class="prf-empty">Aún no te has guardado roomies. <a href="/roomies">Explora roomies →</a></div>`;

  gridHab.querySelectorAll("[data-remove-hab]").forEach((btn) => {
    btn.addEventListener("click", (e) => { e.preventDefault(); quitarFavoritoHabitacion(Number(btn.dataset.removeHab)); });
  });
  gridRoom.querySelectorAll("[data-remove-roomie]").forEach((btn) => {
    btn.addEventListener("click", (e) => { e.preventDefault(); quitarFavoritoRoomie(Number(btn.dataset.removeRoomie)); });
  });
}

document.querySelectorAll(".prf-subtab").forEach((tab) => {
  tab.addEventListener("click", () => {
    document.querySelectorAll(".prf-subtab").forEach((t) => t.classList.remove("active"));
    tab.classList.add("active");
    const esHabitaciones = tab.dataset.fav === "habitaciones";
    document.getElementById("favHabitacionesGrid").hidden = !esHabitaciones;
    document.getElementById("favRoomiesGrid").hidden = esHabitaciones;
  });
});

// ============================================================
// MODALES: calificar / reportar (mismo look que la versión original)
// ------------------------------------------------------------
// Generalizados para servir tanto a Reservas (publicaciones) como a
// Mis citas (roomies): reciben el endpoint destino y un callback de
// éxito, en vez de tener el endpoint hardcodeado dentro del modal.
// Los estilos .rr-* viven únicamente en perfil.css (antes también se
// inyectaban aquí por JS en runtime; se quitó esa duplicación para
// tener una sola fuente de verdad).
// ============================================================

/**
 * @param {{titulo: string, sub: string, endpoint: string, onExito: () => void}} opciones
 */
function abrirModalCalificacion({ titulo, sub, endpoint, onExito }) {
  let puntuacionSeleccionada = 0;

  const overlay = document.createElement("div");
  overlay.className = "rr-overlay";
  overlay.innerHTML = `
    <div class="rr-modal">
      <p class="rr-modal-title">${titulo}</p>
      <p class="rr-modal-sub">${sub}</p>
      <div class="rr-stars" id="rrStars">
        ${[1, 2, 3, 4, 5].map((n) => `
          <span class="rr-star" data-star="${n}">
            <span class="rr-star-bg">★</span>
            <span class="rr-star-fill">★</span>
            <span class="rr-star-zone rr-star-zone-half" data-value="${n * 2 - 1}"></span>
            <span class="rr-star-zone rr-star-zone-full" data-value="${n * 2}"></span>
          </span>`).join("")}
      </div>
      <p class="rr-rating-value" id="rrRatingValue">Selecciona tu calificación (1 a 10)</p>
      <textarea class="rr-textarea" id="rrComentario" placeholder="Cuéntale a otros usuarios cómo fue tu experiencia..."></textarea>
      <p class="rr-error" id="rrError">Selecciona una calificación y escribe un comentario (mínimo 10 caracteres).</p>
      <div class="rr-buttons">
        <button type="button" class="rr-btn rr-btn-ghost" id="rrCancelar">Cancelar</button>
        <button type="button" class="rr-btn rr-btn-primary" id="rrEnviar">Enviar reseña</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = () => overlay.remove();
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
  document.getElementById("rrCancelar").addEventListener("click", cerrar);

  // Widget de calificación 1-10: 5 estrellas, cada una divisible en
  // mitad izquierda (impar) y mitad derecha (par). rr-star-zone-half
  // y rr-star-zone-full son capas invisibles superpuestas que solo
  // capturan el click; el relleno visual lo pinta pintarEstrellas().
  const estrellas = overlay.querySelectorAll(".rr-star");
  const valorTexto = overlay.querySelector("#rrRatingValue");

  function pintarEstrellas(valor10) {
    estrellas.forEach((estrella, idx) => {
      const n = idx + 1;
      const relleno = estrella.querySelector(".rr-star-fill");
      if (valor10 >= n * 2) relleno.style.width = "100%";
      else if (valor10 === n * 2 - 1) relleno.style.width = "50%";
      else relleno.style.width = "0%";
    });
  }

  overlay.querySelectorAll(".rr-star-zone").forEach((zona) => {
    zona.addEventListener("click", () => {
      puntuacionSeleccionada = Number(zona.dataset.value);
      pintarEstrellas(puntuacionSeleccionada);
      valorTexto.textContent = `${puntuacionSeleccionada} / 10`;
    });
  });

  document.getElementById("rrEnviar").addEventListener("click", async () => {
    const comentario = document.getElementById("rrComentario").value.trim();
    const errorEl = document.getElementById("rrError");
    if (!puntuacionSeleccionada || comentario.length < 10) { errorEl.classList.add("is-visible"); return; }
    errorEl.classList.remove("is-visible");

    const respuesta = await fetch(`${API_BASE}${endpoint}`, {
      method: "POST",
      headers: cabecerasAutenticadas(),
      body: JSON.stringify({ puntuacion: puntuacionSeleccionada, comentario }),
    });
    if (manejarNoAutorizado(respuesta)) return;
    if (!respuesta.ok) { errorEl.textContent = "No se pudo enviar la reseña."; errorEl.classList.add("is-visible"); return; }

    cerrar();
    onExito();
  });
}

function abrirModalReporte({ objetivoTitulo, motivos, onEnviar }) {
  const overlay = document.createElement("div");
  overlay.className = "rr-overlay";
  overlay.innerHTML = `
    <div class="rr-modal">
      <p class="rr-modal-title">Reportar</p>
      <p class="rr-modal-sub">${objetivoTitulo}</p>
      <label class="rr-label" for="rrMotivo">Motivo</label>
      <select class="rr-select" id="rrMotivo">${motivos.map((m) => `<option value="${m}">${m}</option>`).join("")}</select>
      <label class="rr-label" style="margin-top:12px;" for="rrDescripcion">Cuéntanos qué pasó</label>
      <textarea class="rr-textarea" id="rrDescripcion" placeholder="Describe la situación con el mayor detalle posible..."></textarea>
      <p class="rr-error" id="rrReporteError">Escribe una descripción de al menos 15 caracteres.</p>
      <div class="rr-buttons">
        <button type="button" class="rr-btn rr-btn-ghost" id="rrReporteCancelar">Cancelar</button>
        <button type="button" class="rr-btn rr-btn-danger" id="rrReporteEnviar">Enviar reporte</button>
      </div>
    </div>`;
  document.body.appendChild(overlay);

  const cerrar = () => overlay.remove();
  overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
  document.getElementById("rrReporteCancelar").addEventListener("click", cerrar);

  document.getElementById("rrReporteEnviar").addEventListener("click", () => {
    const motivo = document.getElementById("rrMotivo").value;
    const descripcion = document.getElementById("rrDescripcion").value.trim();
    const errorEl = document.getElementById("rrReporteError");
    if (descripcion.length < 15) { errorEl.classList.add("is-visible"); return; }
    errorEl.classList.remove("is-visible");
    onEnviar(motivo, descripcion);
    cerrar();
  });
}

// ============================================================
// 4) RESERVAS
// ============================================================
async function renderReservas() {
  const cont = document.getElementById("reservasList");
  const respuesta = await fetch(`${API_BASE}/mis-reservas`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const reservas = datos.reservas || [];

  if (!reservas.length) {
    cont.innerHTML = `<div class="prf-empty">Aún no has solicitado ninguna visita. <a href="/dashboard">Buscar habitaciones →</a></div>`;
    return;
  }

  cont.innerHTML = reservas.map((r) => `
    <div class="prf-item-card">
      <img class="prf-item-img" src="${r.img || ""}" alt="${r.titulo}"/>
      <div class="prf-item-body">
        <div class="prf-item-title">${r.titulo}</div>
        <div class="prf-item-sub">${r.zona} · ${ETIQUETAS_DURACION[r.duracion] || ""}</div>
        <div class="prf-item-meta">Visita: ${formatFechaCorta(r.fecha_visita)}${r.hora_visita ? ` · ${formatHora12(r.hora_visita)}` : ""} · ${formatCOP(r.monto_total)}</div>
        ${r.estado !== "rechazada" && r.huboEdicion ? `<div class="prf-item-warning">⚠️ El oferente editó esta publicación después de tu solicitud.</div>` : ""}
      </div>
      <div class="prf-item-actions">
        <span class="prf-badge is-${r.estado}">${ETIQUETAS_BADGE[r.estado] || r.estado}</span>
        ${r.estado === "aceptada" ? `<a class="prf-item-link" href="/confirmacion?reservaId=${r.id}">Ver detalle</a>` : ""}
        ${r.estado === "aceptada"
          ? (r.calificada ? `<span class="prf-reviewed-note">★ Ya calificaste</span>` : `<button type="button" class="prf-item-link" data-calificar="${r.id}">★ Calificar espacio</button>`)
          : ""}
        ${r.estado === "aceptada"
          ? (r.reportada ? `<span class="prf-reviewed-note is-reportado">🚩 Reportado</span>` : `<button type="button" class="prf-item-link is-reportar" data-reportar="${r.id}">🚩 Reportar</button>`)
          : ""}
      </div>
    </div>`).join("");

  cont.querySelectorAll("[data-calificar]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const r = reservas.find((x) => x.id === Number(btn.dataset.calificar));
      if (!r) return;
      abrirModalCalificacion({
        titulo: "Califica tu experiencia",
        sub: `${r.titulo} · ${r.zona}`,
        endpoint: `/reservas/${r.id}/calificar`,
        onExito: renderReservas,
      });
    });
  });

  cont.querySelectorAll("[data-reportar]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const r = reservas.find((x) => x.id === Number(btn.dataset.reportar));
      if (!r) return;
      abrirModalReporte({
        objetivoTitulo: r.titulo,
        motivos: MOTIVOS_REPORTE_PUBLICACION,
        onEnviar: async (motivo, descripcion) => {
          const resp = await fetch(`${API_BASE}/reservas/${r.id}/reportar`, {
            method: "POST",
            headers: cabecerasAutenticadas(),
            body: JSON.stringify({ motivo, descripcion }),
          });
          if (manejarNoAutorizado(resp)) return;
          renderReservas();
        },
      });
    });
  });
}

// ============================================================
// 5) MIS CITAS
// ============================================================
async function renderCitas() {
  const cont = document.getElementById("citasList");
  const respuesta = await fetch(`${API_BASE}/mis-citas`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const citas = datos.citas || [];

  if (!citas.length) {
    cont.innerHTML = `<div class="prf-empty">Aún no has agendado citas con roomies. <a href="/roomies">Buscar roomies →</a></div>`;
    return;
  }

  cont.innerHTML = citas.map((c) => `
    <div class="prf-item-card">
      <img class="prf-item-img" src="${c.img || ""}" alt="${c.nombre}"/>
      <div class="prf-item-body">
        <div class="prf-item-title">${c.nombre}</div>
        <div class="prf-item-sub">${c.zona} · ${ETIQUETAS_TIPO_ENCUENTRO[c.tipo_encuentro] || ""} · ${c.lugar_encuentro}</div>
        <div class="prf-item-meta">Cita: ${formatFechaCorta(c.fecha_cita)}${c.hora_cita ? ` · ${formatHora12(c.hora_cita)}` : ""}</div>
      </div>
      <div class="prf-item-actions">
        <span class="prf-badge is-${c.estado}">${ETIQUETAS_BADGE[c.estado] || c.estado}</span>
        ${c.estado === "aceptada" ? `<a class="prf-item-link" href="/confirmacion-cita?citaId=${c.id}">Ver detalle</a>` : ""}
        ${c.estado === "aceptada"
          ? (c.calificada ? `<span class="prf-reviewed-note">★ Ya calificaste</span>` : `<button type="button" class="prf-item-link" data-calificar-cita="${c.id}">★ Calificar roomie</button>`)
          : ""}
        ${c.estado === "aceptada"
          ? (c.reportada ? `<span class="prf-reviewed-note is-reportado">🚩 Reportado</span>` : `<button type="button" class="prf-item-link is-reportar" data-reportar-cita="${c.id}">🚩 Reportar</button>`)
          : ""}
      </div>
    </div>`).join("");

  cont.querySelectorAll("[data-calificar-cita]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const c = citas.find((x) => x.id === Number(btn.dataset.calificarCita));
      if (!c) return;
      abrirModalCalificacion({
        titulo: "Califica a tu roomie",
        sub: `${c.nombre} · ${c.zona}`,
        endpoint: `/citas/${c.id}/calificar`,
        onExito: renderCitas,
      });
    });
  });

  cont.querySelectorAll("[data-reportar-cita]").forEach((btn) => {
    btn.addEventListener("click", () => {
      const c = citas.find((x) => x.id === Number(btn.dataset.reportarCita));
      if (!c) return;
      abrirModalReporte({
        objetivoTitulo: c.nombre,
        motivos: MOTIVOS_REPORTE_ROOMIE,
        onEnviar: async (motivo, descripcion) => {
          const resp = await fetch(`${API_BASE}/citas/${c.id}/reportar`, {
            method: "POST",
            headers: cabecerasAutenticadas(),
            body: JSON.stringify({ motivo, descripcion }),
          });
          if (manejarNoAutorizado(resp)) return;
          renderCitas();
        },
      });
    });
  });
}

// ============================================================
// 6) NOTIFICACIONES
// ============================================================
const NOTIF_ICONOS = {
  reporte_resuelto: { icono: "✓", clase: "is-resuelto" },
  reporte_descartado: { icono: "✕", clase: "is-descartado" },
  reporte_nuevo: { icono: "🚩", clase: "is-info" },
  reserva_nueva: { icono: "🏠", clase: "is-info" },
  reserva_aceptada: { icono: "✓", clase: "is-resuelto" },
  reserva_rechazada: { icono: "✕", clase: "is-descartado" },
  cita_nueva: { icono: "🤝", clase: "is-info" },
  cita_aceptada: { icono: "✓", clase: "is-resuelto" },
  cita_rechazada: { icono: "✕", clase: "is-descartado" },
  mensaje_contacto_nuevo: { icono: "✉️", clase: "is-info" },
};

async function renderNotificaciones() {
  const cont = document.getElementById("notificacionesList");
  const respuesta = await fetch(`${API_BASE}/notificaciones`, { headers: cabecerasAutenticadas(false) });
  if (manejarNoAutorizado(respuesta)) return;
  const datos = await respuesta.json();
  const notificaciones = datos.notificaciones || [];

  actualizarBadgeSidebar(notificaciones);

  if (!notificaciones.length) {
    cont.innerHTML = `<div class="prf-empty">Aún no tienes notificaciones. Cuando reportes algo, o recibas una solicitud de reserva o cita, te avisaremos aquí.</div>`;
    return;
  }

  cont.innerHTML = notificaciones.map((n) => {
    const { icono, clase } = NOTIF_ICONOS[n.tipo] || { icono: "•", clase: "is-info" };
    const contenido = `
        <span class="prf-notif-icon ${clase}">${icono}</span>
        <div class="prf-notif-body">
          <p class="prf-notif-mensaje">${n.mensaje}</p>
          <p class="prf-notif-fecha">${formatFechaHora(n.fecha)}</p>
        </div>`;
    return n.link
      ? `<a class="prf-notif-card ${n.leida ? "" : "is-unread"}" href="${n.link}">${contenido}</a>`
      : `<div class="prf-notif-card ${n.leida ? "" : "is-unread"}">${contenido}</div>`;
  }).join("");
}

function actualizarBadgeSidebar(notificaciones) {
  const noLeidas = notificaciones.filter((n) => !n.leida).length;
  const badge = document.getElementById("prfNotifBadge");
  badge.hidden = noLeidas === 0;
  badge.textContent = noLeidas > 9 ? "9+" : noLeidas;
}

async function marcarNotificacionesLeidas() {
  await fetch(`${API_BASE}/notificaciones/marcar-leidas`, { method: "POST", headers: cabecerasAutenticadas(false) });
  await renderNotificaciones();
  window.RoommatchNav.actualizarBadgeNotificaciones();
}

// ---------- INICIALIZACIÓN ----------
renderDatosPersonales();
renderConvivencia();
renderFavoritos();
renderReservas();
renderCitas();
renderNotificaciones();
activarPanelPorHash();
