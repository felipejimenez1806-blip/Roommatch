// ============================================================
// ROOMMATCH – lógica de la página DETALLE DE PERFIL DE ROOMIE
// Página pública: se puede ver el perfil completo sin haber
// iniciado sesión. Solo las acciones que requieren un usuario
// real (guardar en favoritos, agendar cita) piden login si no
// hay sesión activa. Mismo patrón que publicacion.js.
//
// CAMBIO PRINCIPAL: ya no depende de `personas` (roomies-data.js).
// Consume GET /api/roomies/{id}, y el chequeo de "es mi propio
// perfil" ahora es por id (item.propietario.id) en vez de por email.
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

function obtenerToken() {
  return sessionStorage.getItem(STORAGE_TOKEN_KEY);
}

function obtenerUsuarioGuardado() {
  return JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");
}

function authHeaders(extra = {}) {
  const token = obtenerToken();
  return {
    Accept: "application/json",
    ...(token ? { Authorization: `Bearer ${token}` } : {}),
    ...extra,
  };
}

// ---------- HELPERS ----------
function formatCOP(n) {
  return "$" + Number(n).toLocaleString("es-CO") + " COP";
}

function formatFechaLarga(fechaISO) {
  if (!fechaISO) return "No especificado";
  return new Date(fechaISO + "T00:00:00").toLocaleDateString("es-CO", { day: "2-digit", month: "long", year: "numeric" });
}

function palabraCalificacion(rating) {
  if (rating >= 9) return "Excelente";
  if (rating >= 8) return "Muy bueno";
  if (rating >= 7) return "Bueno";
  return "Regular";
}

const ETIQUETAS_ENUM = {
  estudiantes: "Estudiantes", profesionales: "Profesionales", otro: "Otro",
  diurno: "Diurno", nocturno: "Nocturno", mixto: "Mixto",
  corto: "Corto plazo", largo: "Largo plazo",
  inmediata: "Inmediata", "1mes": "En un mes", flexible: "Flexible",
};

// ---------- GRUPOS DE CARACTERÍSTICAS (misma organización que en el sidebar de roomies.html) ----------
const GRUPO_ESTILO_VIDA = [
  ["fumador", "Fumador/a"], ["tiene_mascota", "Tiene mascota propia"], ["ordenado", "Ordenado/a"],
  ["sociable", "Sociable"], ["madrugador", "Madrugador/a"], ["trasnochador", "Trasnochador/a"],
  ["fiestero", "Le gustan las reuniones/fiestas"],
];
const GRUPO_CONVIVENCIA = [
  ["acepta_mascotas", "Acepta mascotas en casa"], ["acepta_fumadores", "Acepta convivir con fumadores"],
  ["acepta_visitas", "Acepta visitas frecuentes"], ["acepta_parejas", "Acepta convivir con parejas"],
];
const GRUPO_PREFERENCIAS_HOGAR = [
  ["quiere_amueblado", "Busca lugar amueblado"], ["quiere_bano_privado", "Busca baño privado"],
  ["quiere_parqueadero", "Busca parqueadero"], ["cerca_universidad", "Cerca a universidad"],
  ["cerca_transporte_publico", "Cerca a transporte público"],
];
const GRUPO_OTROS = [
  ["tiene_vehiculo", "Tiene vehículo propio"], ["comparte_gastos", "Dispuesto/a a compartir gastos comunes"],
  ["referencias_verificadas", "Referencias verificadas"],
];

function renderListaFeatures(c, campos) {
  return `<div class="rp-feature-list">
    ${campos.map(([campo, etiqueta]) => `
      <span class="rp-feature-item${c[campo] ? "" : " is-no"}">
        <span class="rp-feature-check">${c[campo] ? "✓" : "✕"}</span> ${etiqueta}
      </span>`).join("")}
  </div>`;
}

// ---------- FAVORITOS (solo si hay sesión) ----------
async function toggleFavorito(item, btn) {
  const token = obtenerToken();
  if (!token) {
    window.location.href = "/login";
    return;
  }

  btn.disabled = true;
  try {
    const resp = await fetch(`${API_BASE}/roomies/${item.id}/favorito`, {
      method: "POST",
      headers: authHeaders(),
    });
    if (!resp.ok) throw new Error("Error al actualizar favorito");

    const { favorito } = await resp.json();
    btn.classList.toggle("is-fav-active", favorito);
    btn.textContent = favorito ? "♥ Guardado en favoritos" : "♡ Guardar en favoritos";
  } catch (err) {
    console.error(err);
    alert("No pudimos actualizar tus favoritos. Intenta de nuevo.");
  } finally {
    btn.disabled = false;
  }
}

// ---------- AGENDAR CITA (requiere sesión) ----------
// En vez de un mensaje directo, se propone una cita en persona
// para conocerse y visitar juntos el sitio que compartirían.
function agendarCita(item) {
  if (!obtenerToken()) {
    window.location.href = "/login";
    return;
  }
  // TODO: el módulo de "Cita con roomie" todavía no está migrado a
  // Laravel (no existe la ruta /cita/{id} ni su vista). Ajustar esta
  // URL cuando ese módulo se construya.
  window.location.href = `/cita/${item.id}`;
}

// ---------- PESTAÑAS ----------
function activarTabs() {
  document.querySelectorAll(".rp-tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".rp-tab-btn").forEach((b) => b.classList.remove("active"));
      document.querySelectorAll(".rp-tab-panel").forEach((p) => p.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById(btn.dataset.tab).classList.add("active");
    });
  });
}

// ---------- CONTENIDO DE CADA PESTAÑA ----------
function tabSobreMi(item) {
  const c = item.caracteristicas || {};
  const iconos = [
    ["sociable", "🙂", "Sociable"],
    ["ordenado", "🧹", "Ordenado/a"],
    ["trabaja_desde_casa", "💻", "Trabaja/estudia desde casa"],
    ["tiene_vehiculo", "🚗", "Tiene vehículo propio"],
    ["referencias_verificadas", "✅", "Referencias verificadas"],
    ["comparte_gastos", "🤝", "Comparte gastos comunes"],
  ].filter(([campo]) => c[campo]);
  if (!c.fumador) iconos.unshift(["no_fumador", "🚭", "No fumador/a"]);

  return `
    <p class="rp-description">${item.descripcion || ""}</p>
    <p class="rp-overview-title">Resumen del perfil</p>
    <div class="rp-overview-grid">
      ${iconos.map(([, icono, texto]) => `
        <div class="rp-overview-item"><span class="rp-overview-icon">${icono}</span> ${texto}</div>
      `).join("")}
    </div>
    <div class="rp-info-row"><span>Zona donde busca</span><span>${item.zona}, ${item.ciudad}</span></div>
    <div class="rp-info-row"><span>Ocupación</span><span>${item.ocupacion}</span></div>
    <div class="rp-info-row"><span>Edad</span><span>${item.edad} años</span></div>
    <div class="rp-info-row"><span>Género</span><span>${item.genero}</span></div>
    <div class="rp-info-row"><span>Presupuesto mensual</span><span>${formatCOP(item.presupuesto)}</span></div>
    <div class="rp-info-row"><span>Tiempo de búsqueda</span><span>${ETIQUETAS_ENUM[c.tiempo_busqueda] || "No especificado"}</span></div>
    <div class="rp-info-row"><span>Fecha de mudanza</span><span>${ETIQUETAS_ENUM[c.fecha_mudanza] || "No especificado"}</span></div>`;
}

function tabEstiloVida(item) {
  const c = item.caracteristicas || {};
  return `
    <div class="rp-feature-group">
      <p class="rp-feature-group-title">Estilo de vida</p>
      ${renderListaFeatures(c, GRUPO_ESTILO_VIDA)}
    </div>
    <div class="rp-info-row"><span>Horario habitual</span><span>${ETIQUETAS_ENUM[c.horario] || "No especificado"}</span></div>
    <div class="rp-info-row"><span>Viaja frecuentemente</span><span>${c.viaja_frecuentemente ? "Sí" : "No"}</span></div>`;
}

function tabConvivencia(item) {
  const c = item.caracteristicas || {};
  return `
    <div class="rp-feature-group">
      <p class="rp-feature-group-title">Convivencia</p>
      ${renderListaFeatures(c, GRUPO_CONVIVENCIA)}
    </div>
    <div class="rp-info-row"><span>Ambiente que busca</span><span>${ETIQUETAS_ENUM[c.ambiente_preferido] || "No especificado"}</span></div>
    <div class="rp-feature-group" style="margin-top:18px;">
      <p class="rp-feature-group-title">Preferencias del hogar</p>
      ${renderListaFeatures(c, GRUPO_PREFERENCIAS_HOGAR)}
    </div>
    <div class="rp-feature-group" style="margin-top:18px;">
      <p class="rp-feature-group-title">Otros</p>
      ${renderListaFeatures(c, GRUPO_OTROS)}
    </div>`;
}

// Las reseñas guardan puntuacion en escala 1-10, pero visualmente se
// siguen mostrando como 5 estrellas (mismo criterio estético que el
// widget de calificar): se redondea al nivel de estrella más cercano
// y el valor exacto se muestra aparte como texto "x/10".
function renderEstrellasResena(puntuacion10) {
  const nivel = Math.max(0, Math.min(5, Math.round(puntuacion10 / 2)));
  return "★".repeat(nivel) + "☆".repeat(5 - nivel);
}

function tabResenas(item) {
  const resenas = item.resenas || [];
  if (!resenas.length) {
    return `<p class="rp-no-reviews">Este perfil todavía no tiene reseñas.</p>`;
  }
  const promedio = (resenas.reduce((acc, r) => acc + r.puntuacion, 0) / resenas.length).toFixed(1);
  return `
    <div class="rp-reviews-summary">
      <div class="rp-reviews-score">${promedio}<span>/10</span></div>
      <div class="rp-reviews-count">${resenas.length} reseña${resenas.length === 1 ? "" : "s"} de personas que han compartido vivienda con ${item.nombre.split(" ")[0]}</div>
    </div>
    ${resenas.map((r) => `
      <div class="rp-review-card">
        <div class="rp-review-head">
          <span class="rp-review-author">${r.autor}</span>
          <span class="rp-review-date">${formatFechaLarga(r.fecha)}</span>
        </div>
        <div class="rp-review-stars">${renderEstrellasResena(r.puntuacion)} <span class="rp-review-score">${r.puntuacion}/10</span></div>
        <p class="rp-review-text">${r.comentario}</p>
      </div>`).join("")}`;
}

// ---------- RENDER PRINCIPAL ----------
function renderPerfil(item, favoritoActivo) {
  const contenedor = document.getElementById("rpPage");
  const token = obtenerToken();
  const usuarioActual = obtenerUsuarioGuardado();
  const resenas = item.resenas || [];

  // Un roomie no debe poder agendarse cita consigo mismo.
  const esPropioPerfil = usuarioActual && item.propietario
    && item.propietario.id === usuarioActual.id;

  contenedor.innerHTML = `
    <a class="rp-back" href="/roomies" title="Volver a resultados">&larr;</a>

    <div class="rp-hero">
      <div class="rp-avatar-wrap">
        <img src="${item.img || ""}" alt="${item.nombre}"/>
      </div>
      <div class="rp-hero-info">
        <div class="rp-title">${item.nombre} <span>· ${item.edad} años</span></div>
        <div class="rp-subtitle">${item.ocupacion} · Busca en ${item.zona}, ${item.ciudad}</div>
        ${item.estado_busqueda && item.estado_busqueda !== "buscando"
          ? `<span class="rp-unavailable-badge">${item.estado_busqueda === "ya_encontro" ? "Ya no está buscando — encontró roomie" : "Búsqueda pausada"}</span>`
          : ""}
      </div>
      <div class="rp-rating-chip">
        <div class="rp-rating-text">
          <div class="rp-rating-word">${resenas.length ? palabraCalificacion(item.rating) : "Nuevo"}</div>
          <div class="rp-rating-count">${resenas.length} reseña${resenas.length === 1 ? "" : "s"}</div>
        </div>
        <span class="rp-rating-badge">${resenas.length ? item.rating : "—"}</span>
      </div>
    </div>

    <div class="rp-body">
      <div class="rp-main">
        <div class="rp-tabs">
          <button class="rp-tab-btn active" data-tab="tabSobreMi">Sobre mí</button>
          <button class="rp-tab-btn" data-tab="tabEstiloVida">Estilo de vida</button>
          <button class="rp-tab-btn" data-tab="tabConvivencia">Convivencia y preferencias</button>
          <button class="rp-tab-btn" data-tab="tabResenas">Reseñas${resenas.length ? ` (${resenas.length})` : ""}</button>
        </div>

        <div class="rp-tab-panel active" id="tabSobreMi">${tabSobreMi(item)}</div>
        <div class="rp-tab-panel" id="tabEstiloVida">${tabEstiloVida(item)}</div>
        <div class="rp-tab-panel" id="tabConvivencia">${tabConvivencia(item)}</div>
        <div class="rp-tab-panel" id="tabResenas">${tabResenas(item)}</div>
      </div>

      <div class="rp-sidebar">
        <div class="rp-contact-card">
          <div class="rp-price">${formatCOP(item.presupuesto)} <span>/ mes</span></div>
          <div class="rp-price-note">Presupuesto máximo declarado por ${item.nombre.split(" ")[0]}</div>
          ${esPropioPerfil
            ? `<p class="rp-login-note">Este es tu perfil de roomie — <a href="/mis-publicaciones" style="color:#1a9ecf;font-weight:700;">gestiona las solicitudes aquí →</a></p>`
            : (item.estado_busqueda && item.estado_busqueda !== "buscando"
                ? `<button type="button" class="rp-cta is-disabled" disabled>No disponible</button>`
                : `<button type="button" class="rp-cta" id="agendarCitaBtn">📅 Agendar cita para conocerse</button>`)}
          <button type="button" class="rp-cta-secondary${favoritoActivo ? " is-fav-active" : ""}" id="favoritoBtn">
            ${favoritoActivo ? "♥ Guardado en favoritos" : "♡ Guardar en favoritos"}
          </button>
          ${!token ? `<p class="rp-login-note">Inicia sesión para agendar una cita o guardar en favoritos</p>` : ""}
        </div>
      </div>
    </div>`;

  // agendarCitaBtn solo existe en el DOM si el usuario NO es el dueño
  // (ver arriba), así que el listener se agrega de forma opcional.
  document.getElementById("agendarCitaBtn")?.addEventListener("click", () => agendarCita(item));
  document.getElementById("favoritoBtn").addEventListener("click", (e) => toggleFavorito(item, e.currentTarget));
  activarTabs();
}

// ---------- CARGA INICIAL DESDE LA API ----------
async function cargarPerfil() {
  const contenedor = document.getElementById("rpPage");
  const id = contenedor.dataset.id;

  try {
    const resp = await fetch(`${API_BASE}/roomies/${id}`, { headers: { Accept: "application/json" } });
    if (!resp.ok) throw new Error("Perfil no encontrado");

    const { persona: item } = await resp.json();

    let favoritoActivo = false;
    const token = obtenerToken();
    if (token) {
      try {
        const respFav = await fetch(`${API_BASE}/mis-favoritos`, { headers: authHeaders() });
        if (respFav.ok) {
          const { idsPersonas } = await respFav.json();
          favoritoActivo = (idsPersonas || []).includes(item.id);
        }
      } catch (e) {
        console.error(e);
      }
    }

    renderPerfil(item, favoritoActivo);
  } catch (err) {
    console.error(err);
    contenedor.innerHTML = `
      <div class="rp-not-found">
        <p>No encontramos este perfil. Puede que ya no esté disponible.</p>
        <a href="/roomies">← Volver a la búsqueda</a>
      </div>`;
  }
}

cargarPerfil();
