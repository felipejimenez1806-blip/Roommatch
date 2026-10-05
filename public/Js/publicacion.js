// ============================================================
// ROOMMATCH – lógica de la página DETALLE DE PUBLICACIÓN
// Página pública: se puede ver el detalle completo sin haber
// iniciado sesión. Solo las acciones que requieren un usuario
// real (guardar en favoritos, reservar) piden login si no hay
// sesión activa.
//
// CAMBIO PRINCIPAL: ya no depende de `publicaciones` (data.js).
// Ahora consume la API vía fetch, y la sesión se lee con las
// mismas claves que usa nav.js:
//   - "roommatch_token": token Sanctum
//   - "roommatch_user":  snapshot del usuario logueado
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

// ---------- SESIÓN (opcional en esta página) ----------
function obtenerToken() {
  return sessionStorage.getItem(STORAGE_TOKEN_KEY);
}

function obtenerUsuarioGuardado() {
  return JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");
}

function authHeaders() {
  const token = obtenerToken();
  return token
    ? { Authorization: `Bearer ${token}`, Accept: "application/json" }
    : { Accept: "application/json" };
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
  pequena: "Pequeña", mediana: "Mediana", grande: "Grande",
  sencilla: "Sencilla", semidoble: "Semidoble", doble: "Doble", queen: "Queen", king: "King",
  masculino: "Solo hombres", femenino: "Solo mujeres",
};

// ---------- GRUPOS DE CARACTERÍSTICAS (misma organización que CARACTERISTICAS_PUBLICACION) ----------
const GRUPO_ESPACIO = [
  ["amueblado", "Amueblado"], ["bano_privado", "Baño privado"], ["cocina_compartida", "Cocina compartida"],
  ["lavadora", "Lavadora"], ["secadora", "Secadora"], ["parqueadero", "Parqueadero"],
  ["balcon", "Balcón"], ["terraza", "Terraza"],
];
const GRUPO_SERVICIOS = [
  ["incluye_agua", "Agua"], ["incluye_luz", "Luz"], ["incluye_internet", "Internet"], ["incluye_gas", "Gas"],
];
const GRUPO_EDIFICIO_SEGURIDAD = [
  ["ascensor", "Ascensor"], ["gimnasio", "Gimnasio"], ["zona_comun", "Zona común"],
  ["porteria", "Portería"], ["camaras_seguridad", "Cámaras de seguridad"],
  ["espacio_trabajo", "Espacio de trabajo/estudio"], ["cerca_transporte_publico", "Cerca a transporte público"],
  ["cerca_supermercado", "Supermercado cerca"], ["cerca_universidad", "Universidad cerca"],
];
const GRUPO_REGLAS = [
  ["permite_mascotas", "Permite mascotas"], ["permite_visitas", "Permite visitas"],
  ["permite_fumar", "Permite fumar"], ["permite_fiestas", "Permite fiestas/reuniones"],
  ["permite_parejas", "Permite parejas"],
];

function renderListaFeatures(c, campos) {
  return `<div class="pub-feature-list">
    ${campos.map(([campo, etiqueta]) => `
      <span class="pub-feature-item${c[campo] ? "" : " is-no"}">
        <span class="pub-feature-check">${c[campo] ? "✓" : "✕"}</span> ${etiqueta}
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
    const resp = await fetch(`${API_BASE}/publicaciones/${item.id}/favorito`, {
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

// ---------- RESERVAR (requiere sesión) ----------
function solicitarReserva(item) {
  if (!obtenerToken()) {
    window.location.href = "/login";
    return;
  }
  // TODO: el módulo de Reservas todavía no está migrado a Laravel
  // (no existe la ruta /reserva/{id} en web.php ni la vista). Ajustar
  // esta URL cuando ese módulo se construya.
  window.location.href = `/reserva/${item.id}`;
}

// ---------- GALERÍA: click en miniatura intercambia con la foto principal ----------
function activarGaleria() {
  const principal = document.getElementById("galeriaPrincipal");
  document.querySelectorAll(".pub-gallery-thumb").forEach((thumb) => {
    thumb.addEventListener("click", () => {
      const src = thumb.querySelector("img").src;
      const anterior = principal.src;
      principal.src = src;
      thumb.querySelector("img").src = anterior;
    });
  });
}

// ---------- PESTAÑAS ----------
function activarTabs() {
  document.querySelectorAll(".pub-tab-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      document.querySelectorAll(".pub-tab-btn").forEach((b) => b.classList.remove("active"));
      document.querySelectorAll(".pub-tab-panel").forEach((p) => p.classList.remove("active"));
      btn.classList.add("active");
      document.getElementById(btn.dataset.tab).classList.add("active");
    });
  });
}

// ---------- CONTENIDO DE CADA PESTAÑA ----------
function tabDescripcion(item) {
  const c = item.caracteristicas || {};
  const iconos = [
    ["amueblado", "🛋️", "Amueblado"],
    ["incluye_internet", "📶", "Internet incluido"],
    ["parqueadero", "🅿️", "Parqueadero"],
    ["bano_privado", "🚿", "Baño privado"],
    ["porteria", "💂", "Portería 24h"],
    ["ascensor", "🛗", "Ascensor"],
  ].filter(([campo]) => c[campo]);

  return `
    <p class="pub-description">${item.descripcion}</p>
    <p class="pub-overview-title">Resumen del espacio</p>
    <div class="pub-overview-grid">
      ${iconos.map(([, icono, texto]) => `
        <div class="pub-overview-item"><span class="pub-overview-icon">${icono}</span> ${texto}</div>
      `).join("")}
    </div>
    <div class="pub-info-row"><span>Dirección</span><span>${item.direccion}</span></div>
    <div class="pub-info-row"><span>Disponible desde</span><span>${formatFechaLarga(item.fecha_disponible)}</span></div>
    ${item.genero_preferido ? `<div class="pub-info-row"><span>Preferencia</span><span>${ETIQUETAS_ENUM[item.genero_preferido] || item.genero_preferido}</span></div>` : ""}
    ${c.detalles_incluidos ? `<div class="pub-info-row"><span>Servicios</span><span>${c.detalles_incluidos}</span></div>` : ""}
    <p class="pub-overview-title" style="margin-top:20px;">Ubicación aproximada</p>
    <div class="pub-map-wrap">
      <iframe
        src="https://maps.google.com/maps?q=${encodeURIComponent(`${item.direccion}, ${item.zona}, ${item.ciudad}, Colombia`)}&output=embed"
        loading="lazy"
        referrerpolicy="no-referrer-when-downgrade">
      </iframe>
    </div>
    <p class="pub-map-note">Ubicación referencial según la dirección publicada, no es un pin exacto verificado.</p>`;
}

function tabCaracteristicas(item) {
  const c = item.caracteristicas || {};
  return `
    <div class="pub-feature-group">
      <p class="pub-feature-group-title">Espacio</p>
      ${renderListaFeatures(c, GRUPO_ESPACIO)}
    </div>
    <div class="pub-feature-group">
      <p class="pub-feature-group-title">Servicios incluidos</p>
      ${renderListaFeatures(c, GRUPO_SERVICIOS)}
    </div>
    <div class="pub-feature-group">
      <p class="pub-feature-group-title">Edificio, seguridad y alrededores</p>
      ${renderListaFeatures(c, GRUPO_EDIFICIO_SEGURIDAD)}
    </div>
    <div class="pub-info-row"><span>Tamaño de la habitación</span><span>${ETIQUETAS_ENUM[c.tamano_habitacion] || "No especificado"}</span></div>
    <div class="pub-info-row"><span>Tipo de cama</span><span>${ETIQUETAS_ENUM[c.tipo_cama] || "No especificado"}</span></div>`;
}

function tabConvivencia(item) {
  const c = item.caracteristicas || {};
  return `
    <div class="pub-feature-group">
      <p class="pub-feature-group-title">Reglas del hogar</p>
      ${renderListaFeatures(c, GRUPO_REGLAS)}
    </div>
    <div class="pub-info-row"><span>Ambiente del hogar</span><span>${ETIQUETAS_ENUM[c.ambiente_hogar] || "No especificado"}</span></div>
    <div class="pub-info-row"><span>Personas viviendo actualmente</span><span>${c.numero_habitantes ?? "No especificado"}</span></div>
    <div class="pub-info-row"><span>¿Hay fumadores en casa?</span><span>${c.fumadores_en_casa ? "Sí" : "No"}</span></div>
    <div class="pub-info-row"><span>¿Hay mascotas en casa?</span><span>${c.mascotas_en_casa ? "Sí" : "No"}</span></div>
    ${c.horario_silencio ? `<div class="pub-info-row"><span>Horario de silencio</span><span>${c.horario_silencio}</span></div>` : ""}
    <div class="pub-info-row"><span>Horario de entrada</span><span>${c.horario_entrada || "Sin restricción"}</span></div>
    ${c.distancia_transporte ? `<div class="pub-info-row"><span>Distancia al transporte</span><span>${c.distancia_transporte}</span></div>` : ""}`;
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
    return `<p class="pub-no-reviews">Esta publicación todavía no tiene reseñas.</p>`;
  }
  const promedio = (resenas.reduce((acc, r) => acc + r.puntuacion, 0) / resenas.length).toFixed(1);
  return `
    <div class="pub-reviews-summary">
      <div class="pub-reviews-score">${promedio}<span>/10</span></div>
      <div class="pub-reviews-count">${resenas.length} reseña${resenas.length === 1 ? "" : "s"} de buscadores que se alojaron aquí</div>
    </div>
    ${resenas.map((r) => `
      <div class="pub-review-card">
        <div class="pub-review-head">
          <span class="pub-review-author">${r.autor}</span>
          <span class="pub-review-date">${formatFechaLarga(r.fecha)}</span>
        </div>
        <div class="pub-review-stars">${renderEstrellasResena(r.puntuacion)} <span class="pub-review-score">${r.puntuacion}/10</span></div>
        <p class="pub-review-text">${r.comentario}</p>
      </div>`).join("")}`;
}

// ---------- RENDER PRINCIPAL ----------
function renderPublicacion(item, favoritoActivo) {
  const contenedor = document.getElementById("pubPage");
  const token = obtenerToken();
  const usuarioActual = obtenerUsuarioGuardado();
  const resenas = item.resenas || [];

  const esPropiaPublicacion = usuarioActual && item.propietario
    && item.propietario.id === usuarioActual.id;

  const imagenes = (item.imagenes && item.imagenes.length)
    ? item.imagenes
    : (item.img ? [item.img] : []);

  contenedor.innerHTML = `
    <a class="pub-back" href="/habitaciones" title="Volver a resultados">&larr;</a>

    <div class="pub-top">
      <div class="pub-gallery">
        <div class="pub-gallery-main">
          <img id="galeriaPrincipal" src="${imagenes[0] || ""}" alt="${item.titulo}"/>
        </div>
        <div class="pub-gallery-thumbs">
          ${imagenes.slice(1, 5).map((url) => `
            <div class="pub-gallery-thumb"><img src="${url}" alt="${item.titulo}"/></div>
          `).join("")}
        </div>
      </div>

      <div class="pub-header">
        <div>
          <div class="pub-title">${item.titulo}</div>
          <div class="pub-subtitle">${item.direccion} · ${item.zona}, ${item.ciudad}</div>
          ${item.estado_inmueble && item.estado_inmueble !== "disponible"
            ? `<span class="pub-unavailable-badge">${item.estado_inmueble === "reservado" ? "Ya no disponible — reservado" : "No disponible actualmente"}</span>`
            : ""}
        </div>
        <div class="pub-rating-chip">
          <div class="pub-rating-text">
            <div class="pub-rating-word">${palabraCalificacion(item.rating)}</div>
            <div class="pub-rating-count">${resenas.length} reseña${resenas.length === 1 ? "" : "s"}</div>
          </div>
          <span class="pub-rating-badge">${item.rating}</span>
        </div>
      </div>
    </div>

    <div class="pub-body">
      <div class="pub-main">
        <div class="pub-tabs">
          <button class="pub-tab-btn active" data-tab="tabDescripcion">Descripción</button>
          <button class="pub-tab-btn" data-tab="tabCaracteristicas">Características</button>
          <button class="pub-tab-btn" data-tab="tabConvivencia">Convivencia y reglas</button>
          <button class="pub-tab-btn" data-tab="tabResenas">Reseñas${resenas.length ? ` (${resenas.length})` : ""}</button>
        </div>

        <div class="pub-tab-panel active" id="tabDescripcion">${tabDescripcion(item)}</div>
        <div class="pub-tab-panel" id="tabCaracteristicas">${tabCaracteristicas(item)}</div>
        <div class="pub-tab-panel" id="tabConvivencia">${tabConvivencia(item)}</div>
        <div class="pub-tab-panel" id="tabResenas">${tabResenas(item)}</div>
      </div>

      <div class="pub-sidebar">
        <div class="pub-price-card">
          <div class="pub-price">${formatCOP(item.precio)} <span>/ mes</span></div>
          <div class="pub-price-note">${item.caracteristicas?.detalles_incluidos ? "Servicios incluidos" : "Servicios según se indique en la descripción"}</div>
          ${esPropiaPublicacion
            ? `<p class="pub-login-note">Esta es tu publicación — <a href="/mis-publicaciones" style="color:#1a9ecf;font-weight:700;">gestiona las solicitudes aquí →</a></p>`
            : (item.estado_inmueble && item.estado_inmueble !== "disponible"
                ? `<button type="button" class="pub-cta is-disabled" disabled>No disponible</button>`
                : `<button type="button" class="pub-cta" id="reservarBtn">Solicitar reserva</button>`)}
          <button type="button" class="pub-cta-secondary${favoritoActivo ? " is-fav-active" : ""}" id="favoritoBtn">
            ${favoritoActivo ? "♥ Guardado en favoritos" : "♡ Guardar en favoritos"}
          </button>
          ${!token ? `<p class="pub-login-note">Inicia sesión para reservar o guardar en favoritos</p>` : ""}
        </div>
      </div>
    </div>`;

  // reservarBtn solo existe en el DOM si el usuario NO es el dueño (ver
  // arriba), así que el listener se agrega de forma opcional.
  document.getElementById("reservarBtn")?.addEventListener("click", () => solicitarReserva(item));
  document.getElementById("favoritoBtn").addEventListener("click", (e) => toggleFavorito(item, e.currentTarget));
  activarGaleria();
  activarTabs();
}

// ---------- CARGA INICIAL DESDE LA API ----------
async function cargarPublicacion() {
  const contenedor = document.getElementById("pubPage");
  const id = contenedor.dataset.id;

  try {
    const resp = await fetch(`${API_BASE}/publicaciones/${id}`, { headers: { Accept: "application/json" } });
    if (!resp.ok) throw new Error("Publicación no encontrada");

    const { publicacion: item } = await resp.json();

    // Si hay sesión, se consulta si esta publicación ya está en
    // favoritos (mismo endpoint que usa dashboard.js/favoritos).
    let favoritoActivo = false;
    const token = obtenerToken();
    if (token) {
      try {
        const respFav = await fetch(`${API_BASE}/mis-favoritos`, { headers: authHeaders() });
        if (respFav.ok) {
          const { idsPublicaciones } = await respFav.json();
          favoritoActivo = (idsPublicaciones || []).includes(item.id);
        }
      } catch (e) {
        // Si falla la consulta de favoritos, simplemente se muestra
        // el botón como "no guardado"; no bloquea el resto de la página.
        console.error(e);
      }
    }

    renderPublicacion(item, favoritoActivo);
  } catch (err) {
    console.error(err);
    contenedor.innerHTML = `
      <div class="pub-not-found">
        <p>No encontramos esta publicación. Puede que ya no esté disponible.</p>
        <a href="/habitaciones">← Volver a la búsqueda</a>
      </div>`;
  }
}

cargarPublicacion();
