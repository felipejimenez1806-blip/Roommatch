// ============================================================
// ROOMMATCH – lógica de la página INDEX (home pública)
// ------------------------------------------------------------
// Igual que dashboard.js, pero sin exigir sesión: cualquiera
// puede ver el catálogo. Si el usuario SÍ tiene sesión activa,
// sus favoritos reales se cargan igual que en el dashboard.
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
const usuario = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");

function cabecerasAutenticadas() {
  return {
    Authorization: `Bearer ${token}`,
    Accept: "application/json",
    "Content-Type": "application/json",
  };
}

const CANTIDAD_RECOMENDADOS = 5;
let listings = [];
let favorites = new Set();
let activeZoneFilter = null;

function formatCOP(n) {
  return "$" + n.toLocaleString("es-CO") + " COP";
}

function normalize(str) {
  return str.toLowerCase().normalize("NFD").replace(/[\u0300-\u036f]/g, "");
}

// ---------- CARGA DE DATOS ----------
// Vista por defecto (sin zona seleccionada): la mejor publicación de
// CADA zona, sin repetir zona, máximo 5. La resuelve el backend en
// /api/publicaciones/destacadas para no tener que traer TODO el
// catálogo al navegador solo para agrupar en el cliente.
async function cargarDestacadas() {
  const respuesta = await fetch(`${API_BASE}/publicaciones/destacadas`);
  const datos = await respuesta.json();
  return datos.data;
}

// Zona seleccionada (clic en "Lugares populares"): las 5 MEJORES
// publicaciones de esa zona puntual, sin importar si compiten o no
// con el top global. Reusa el endpoint de listado con los filtros
// zona + orden=rating que ya soporta PublicacionController::index.
async function cargarPorZona(zona) {
  const parametros = new URLSearchParams({ zona, orden: "rating" });
  const respuesta = await fetch(`${API_BASE}/publicaciones?${parametros}`);
  const datos = await respuesta.json();
  return datos.data.slice(0, CANTIDAD_RECOMENDADOS);
}

// Trae el conjunto correcto según haya o no una zona activa.
async function cargarListings() {
  return activeZoneFilter ? cargarPorZona(activeZoneFilter) : cargarDestacadas();
}

async function cargarFavoritos() {
  if (!token || !usuario) return [];
  const respuesta = await fetch(`${API_BASE}/mis-favoritos`, {
    headers: cabecerasAutenticadas(),
  });
  if (!respuesta.ok) return [];
  const datos = await respuesta.json();
  return datos.idsPublicaciones || [];
}

// Sin sesión, un click en el corazón manda a login (mismo patrón que
// reservar/agendar cita en las demás páginas del sitio).
async function toggleFavorito(item, btn) {
  if (!token || !usuario) {
    window.location.href = "/login";
    return;
  }

  const respuesta = await fetch(`${API_BASE}/publicaciones/${item.id}/favorito`, {
    method: "POST",
    headers: cabecerasAutenticadas(),
  });

  if (respuesta.status === 401) {
    sessionStorage.removeItem(STORAGE_TOKEN_KEY);
    sessionStorage.removeItem(STORAGE_USER_KEY);
    window.location.href = "/login";
    return;
  }

  const datos = await respuesta.json();
  if (datos.favorito) {
    favorites.add(item.id);
    btn.classList.add("is-fav");
    btn.textContent = "♥";
  } else {
    favorites.delete(item.id);
    btn.classList.remove("is-fav");
    btn.textContent = "♡";
  }
}

function renderListings(items) {
  const grid = document.getElementById("recomGrid");
  const count = document.getElementById("resultsCount");
  grid.innerHTML = "";

  count.textContent = items.length
    ? `${items.length} resultado${items.length === 1 ? "" : "s"}`
    : "";

  if (!items.length) {
    grid.innerHTML = `
      <div class="no-results">
        No encontramos lugares que coincidan con tu búsqueda.
        <br/><button id="clearFiltersBtn">Limpiar filtros</button>
      </div>`;
    document.getElementById("clearFiltersBtn").addEventListener("click", clearAllFilters);
    return;
  }

  items.forEach(item => {
    const card = document.createElement("div");
    card.className = "recom-card";
    card.dataset.id = item.id;
    card.innerHTML = `
      <div class="recom-img-wrap">
        <img src="${item.img}" alt="${item.tipo_espacio} ${item.zona}"/>
        <span class="recom-badge">${item.rating}</span>
        <button class="recom-fav${favorites.has(item.id) ? " is-fav" : ""}" data-id="${item.id}">
          ${favorites.has(item.id) ? "♥" : "♡"}
        </button>
      </div>
      <div class="recom-body">
        <div class="recom-type">${item.tipo_espacio}</div>
        <div class="recom-zone">${item.zona}</div>
        <div class="recom-price">
          ${formatCOP(item.precio)}
          <button class="arrow-btn" data-id="${item.id}">&#8594;</button>
        </div>
      </div>`;
    grid.appendChild(card);
  });

  grid.querySelectorAll(".recom-fav").forEach(btn => {
    btn.addEventListener("click", e => {
      e.stopPropagation();
      const id = Number(btn.dataset.id);
      const item = listings.find(l => l.id === id);
      if (item) toggleFavorito(item, btn);
    });
  });

  grid.querySelectorAll(".recom-card").forEach(card => {
    card.addEventListener("click", () => {
      window.location.href = `/publicacion/${card.dataset.id}`;
    });
  });
}

// ---------- FILTRO DE TEXTO (dentro del dataset ya cargado) ----------
// La zona YA NO se filtra aquí en memoria: cambia qué se carga desde
// el servidor (ver cargarListings/seleccionarZona). Este filtro solo
// aplica la búsqueda de texto sobre lo que esté cargado en `listings`.
function applyFilters() {
  const query = normalize(document.getElementById("searchInput").value.trim());

  const results = !query
    ? listings
    : listings.filter(item =>
        normalize(item.zona).includes(query) ||
        normalize(item.tipo_espacio).includes(query)
      );

  renderListings(results);
}

function clearAllFilters() {
  document.getElementById("searchInput").value = "";
  applyFilters();
}

// ---------- SELECCIÓN DE ZONA (recarga desde el servidor) ----------
async function seleccionarZona(zona) {
  const grid = document.getElementById("recomGrid");
  grid.innerHTML = `<p class="results-count">Cargando...</p>`;

  listings = await cargarListings();
  document.getElementById("searchInput").value = "";
  renderListings(listings);
}

document.getElementById("searchInput").addEventListener("input", applyFilters);
document.getElementById("searchInput").addEventListener("keydown", e => {
  if (e.key === "Enter") applyFilters();
});
document.getElementById("searchBtn").addEventListener("click", applyFilters);

document.querySelectorAll(".lugar-card").forEach(card => {
  card.addEventListener("click", () => {
    const zone = card.dataset.zone;
    const isActive = activeZoneFilter === zone;

    document.querySelectorAll(".lugar-card").forEach(c => c.classList.remove("active-zone"));
    activeZoneFilter = isActive ? null : zone;
    if (activeZoneFilter) card.classList.add("active-zone");

    seleccionarZona(activeZoneFilter);
    document.getElementById("recomSection").scrollIntoView({ behavior: "smooth", block: "start" });
  });
});

(async function inicializar() {
  const [publicaciones, idsFavoritos] = await Promise.all([
    cargarListings(),
    cargarFavoritos(),
  ]);

  listings = publicaciones;
  favorites = new Set(idsFavoritos);
  renderListings(listings);
})();
