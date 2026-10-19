// ============================================================
// ROOMMATCH – lógica de la página INDEX (home pública)
// ------------------------------------------------------------
// Igual que dashboard.js, pero sin exigir sesión: cualquiera
// puede ver el catálogo. Si el usuario SÍ tiene sesión activa,
// sus favoritos reales se cargan igual que en el dashboard.
// ============================================================

import { API_BASE, appUrl } from "../core/config.js";
import { haySesion, cabecerasAutenticadas, manejarNoAutorizado } from "../core/auth.js";
import { esc } from "../core/html.js";

let listings = [];
let favorites = new Set();

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

// Los "Lugares populares" ya no filtran esta sección: cada tarjeta es un
// enlace a /habitaciones?localidad=... (ver partials/lugares-populares).
async function cargarListings() {
  return cargarDestacadas();
}

async function cargarFavoritos() {
  if (!haySesion()) return [];
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
  if (!haySesion()) {
    window.location.href = appUrl("/login");
    return;
  }

  const respuesta = await fetch(`${API_BASE}/publicaciones/${item.id}/favorito`, {
    method: "POST",
    headers: cabecerasAutenticadas(),
  });

  if (manejarNoAutorizado(respuesta)) return;

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
        <img src="${esc(item.img)}" alt="${esc(item.tipo_espacio)} ${esc(item.zona)}"/>
        <span class="recom-badge">${esc(item.rating)}</span>
        <button class="recom-fav${esc(favorites.has(item.id) ? " is-fav" : "")}" data-id="${esc(item.id)}">
          ${esc(favorites.has(item.id) ? "♥" : "♡")}
        </button>
      </div>
      <div class="recom-body">
        <div class="recom-type">${esc(item.tipo_espacio)}</div>
        <div class="recom-zone">${esc(item.zona)}</div>
        <div class="recom-price">
          ${esc(formatCOP(item.precio))}
          <button class="arrow-btn" data-id="${esc(item.id)}">&#8594;</button>
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
      window.location.href = appUrl(`/publicacion/${card.dataset.id}`);
    });
  });
}

// ---------- FILTRO DE TEXTO (dentro del dataset ya cargado) ----------
// Este filtro solo aplica la búsqueda de texto sobre lo que esté cargado
// en `listings` (la zona se elige desde los enlaces de Lugares populares).
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

document.getElementById("searchInput").addEventListener("input", applyFilters);
document.getElementById("searchInput").addEventListener("keydown", e => {
  if (e.key === "Enter") applyFilters();
});
document.getElementById("searchBtn").addEventListener("click", applyFilters);

(async function inicializar() {
  const [publicaciones, idsFavoritos] = await Promise.all([
    cargarListings(),
    cargarFavoritos(),
  ]);

  listings = publicaciones;
  favorites = new Set(idsFavoritos);
  renderListings(listings);
})();
