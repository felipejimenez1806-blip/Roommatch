const RESULTADOS_POR_PAGINA = 6;

let ordenActual = "recientes";
let paginaActual = 1;
let debounceTimer = null;

function formatCOP(n) {
  return "$" + n.toLocaleString("es-CO") + " COP";
}

function leerFiltros() {
  const zona = document.getElementById("filtroZona").value.trim();
  const tiposSeleccionados = Array.from(document.querySelectorAll(".filtroTipo:checked")).map((c) => c.value);
  const precioMin = document.getElementById("precioMin").value;
  const precioMax = document.getElementById("precioMax").value;
  const calificacionMin = document.querySelector('input[name="calificacion"]:checked').value;
  const booleanosSeleccionados = Array.from(document.querySelectorAll(".filter-group input[type=checkbox]:checked"))
    .map((c) => c.dataset.filtro);
  const ambiente = document.getElementById("filtroAmbiente").value;
  const tamano = document.getElementById("filtroTamano").value;
  const cama = document.getElementById("filtroCama").value;

  return { zona, tiposSeleccionados, precioMin, precioMax, calificacionMin, booleanosSeleccionados, ambiente, tamano, cama };
}

function construirQueryParams() {
  const f = leerFiltros();
  const params = new URLSearchParams();

  if (f.zona) params.set("zona", f.zona);
  f.tiposSeleccionados.forEach((t) => params.append("tipo[]", t));
  if (f.precioMin) params.set("precio_min", f.precioMin);
  if (f.precioMax) params.set("precio_max", f.precioMax);
  if (f.calificacionMin && f.calificacionMin !== "0") params.set("calificacion_min", f.calificacionMin);
  f.booleanosSeleccionados.forEach((b) => params.append("booleanos[]", b));
  if (f.ambiente) params.set("ambiente", f.ambiente);
  if (f.tamano) params.set("tamano", f.tamano);
  if (f.cama) params.set("cama", f.cama);

  params.set("orden", ordenActual);
  params.set("page", paginaActual);

  return params;
}

async function cargarResultados() {
  const lista = document.getElementById("roomsList");
  const contador = document.getElementById("roomsResultsCount");

  try {
    const params = construirQueryParams();
    const resp = await fetch(`${API_BASE}/publicaciones?${params.toString()}`);
    if (!resp.ok) throw new Error("Error al consultar publicaciones");

    const { data, meta } = await resp.json();

    contador.textContent = `${meta.total} resultado${meta.total === 1 ? "" : "s"} en Bogotá`;

    if (!data.length) {
      lista.innerHTML = `
        <div class="rooms-empty">
          No encontramos publicaciones que coincidan con estos filtros.
          <br/><button id="limpiarDesdeVacioBtn">Limpiar filtros</button>
        </div>`;
      document.getElementById("limpiarDesdeVacioBtn").addEventListener("click", limpiarFiltros);
      document.getElementById("roomsPagination").innerHTML = "";
      return;
    }

    lista.innerHTML = data.map((item) => `
      <div class="room-card">
        <div class="room-card-img-wrap">
          <img src="${item.img}" alt="${item.tipo_espacio} en ${item.zona}"/>
          <span class="room-card-badge">${item.rating}</span>
        </div>
        <div class="room-card-body">
          <div class="room-card-type">${item.tipo_espacio}</div>
          <div class="room-card-zone">${item.zona}, ${item.ciudad}</div>
          <div class="room-card-tags">
            ${item.tags.map((t) => `<span class="room-card-tag">${t}</span>`).join("")}
          </div>
          <div class="room-card-footer">
            <div class="room-card-price">${formatCOP(item.precio)}<span>por mes</span></div>
            <a class="room-card-btn" href="/publicacion/${item.id}">Ver publicación</a>
          </div>
        </div>
      </div>`).join("");

    renderPaginacion(meta.last_page);
  } catch (err) {
    console.error(err);
    lista.innerHTML = `<div class="rooms-empty">Ocurrió un error al cargar las publicaciones. Intenta de nuevo.</div>`;
  }
}

function renderPaginacion(totalPaginas) {
  const wrap = document.getElementById("roomsPagination");
  wrap.innerHTML = "";
  for (let i = 1; i <= totalPaginas; i++) {
    const btn = document.createElement("button");
    btn.className = "rooms-page-btn" + (i === paginaActual ? " active" : "");
    btn.textContent = i;
    btn.addEventListener("click", () => {
      paginaActual = i;
      cargarResultados();
      document.querySelector(".rooms-results").scrollIntoView({ behavior: "smooth", block: "start" });
    });
    wrap.appendChild(btn);
  }
}

function limpiarFiltros() {
  document.getElementById("filtroZona").value = "";
  document.querySelectorAll(".filtroTipo").forEach((c) => (c.checked = false));
  document.getElementById("precioMin").value = "";
  document.getElementById("precioMax").value = "";
  document.querySelector('input[name="calificacion"][value="0"]').checked = true;
  document.querySelectorAll(".filter-group input[type=checkbox]").forEach((c) => (c.checked = false));
  document.getElementById("filtroAmbiente").value = "";
  document.getElementById("filtroTamano").value = "";
  document.getElementById("filtroCama").value = "";
  paginaActual = 1;
  cargarResultados();
}

function refrescarConDebounce() {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    paginaActual = 1;
    cargarResultados();
  }, 350);
}

// ---------- EVENTOS ----------
document.getElementById("filtroZona").addEventListener("input", refrescarConDebounce);
document.getElementById("precioMin").addEventListener("input", refrescarConDebounce);
document.getElementById("precioMax").addEventListener("input", refrescarConDebounce);
document.querySelectorAll(".filtroTipo").forEach((c) => c.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.querySelectorAll('input[name="calificacion"]').forEach((r) => r.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.querySelectorAll(".filter-group input[type=checkbox]").forEach((c) => c.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.getElementById("filtroAmbiente").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("filtroTamano").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("filtroCama").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("roomsSort").addEventListener("change", (e) => { ordenActual = e.target.value; paginaActual = 1; cargarResultados(); });
document.getElementById("limpiarFiltrosBtn").addEventListener("click", limpiarFiltros);

// ---------- INICIALIZACIÓN ----------
const parametrosURL = new URLSearchParams(window.location.search);
const zonaDesdeURL = parametrosURL.get("zona");
if (zonaDesdeURL) {
  document.getElementById("filtroZona").value = zonaDesdeURL;
}

cargarResultados();
