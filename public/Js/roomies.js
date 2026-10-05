// ============================================================
// ROOMMATCH – lógica de la página ROOMIES (resultados públicos)
// Página pública: NO requiere sesión.
//
// CAMBIO PRINCIPAL: ya no depende de `personas` (roomies-data.js).
// El filtrado, orden y paginación ahora los hace el servidor
// (GET /api/roomies con query params), igual que en habitaciones.js.
// ============================================================

const RESULTADOS_POR_PAGINA = 6;

let ordenActual = "recientes";
let paginaActual = 1;
let debounceTimer = null;

function formatCOP(n) {
  return "$" + Number(n).toLocaleString("es-CO") + " COP";
}

function leerFiltros() {
  const zona = document.getElementById("filtroZonaRoomie").value.trim();
  const ocupacionesSeleccionadas = Array.from(document.querySelectorAll(".filtroOcupacion:checked")).map((c) => c.value);
  const generosSeleccionados = Array.from(document.querySelectorAll(".filtroGenero:checked")).map((c) => c.value);
  const presupuestoMin = document.getElementById("presupuestoMin").value;
  const presupuestoMax = document.getElementById("presupuestoMax").value;
  const calificacionMin = document.querySelector('input[name="calificacionRoomie"]:checked').value;
  const booleanosSeleccionados = Array.from(document.querySelectorAll(".filter-group input[type=checkbox]:checked"))
    .map((c) => c.dataset.filtro);
  const ambiente = document.getElementById("filtroAmbienteRoomie").value;
  const horario = document.getElementById("filtroHorario").value;
  const tiempoBusqueda = document.getElementById("filtroTiempoBusqueda").value;
  const mudanza = document.getElementById("filtroMudanza").value;

  return {
    zona, ocupacionesSeleccionadas, generosSeleccionados, presupuestoMin, presupuestoMax,
    calificacionMin, booleanosSeleccionados, ambiente, horario, tiempoBusqueda, mudanza,
  };
}

function construirQueryParams() {
  const f = leerFiltros();
  const params = new URLSearchParams();

  if (f.zona) params.set("zona", f.zona);
  f.ocupacionesSeleccionadas.forEach((o) => params.append("ocupacion[]", o));
  f.generosSeleccionados.forEach((g) => params.append("genero[]", g));
  if (f.presupuestoMin) params.set("presupuesto_min", f.presupuestoMin);
  if (f.presupuestoMax) params.set("presupuesto_max", f.presupuestoMax);
  if (f.calificacionMin && f.calificacionMin !== "0") params.set("calificacion_min", f.calificacionMin);
  f.booleanosSeleccionados.forEach((b) => params.append("booleanos[]", b));
  if (f.ambiente) params.set("ambiente", f.ambiente);
  if (f.horario) params.set("horario", f.horario);
  if (f.tiempoBusqueda) params.set("tiempo_busqueda", f.tiempoBusqueda);
  if (f.mudanza) params.set("mudanza", f.mudanza);

  params.set("orden", ordenActual);
  params.set("page", paginaActual);

  return params;
}

// ---------- ETIQUETAS VISIBLES DE CADA TARJETA ----------
// Ya vienen calculadas desde el backend (item.tags), no se recalculan aquí.

async function cargarResultados() {
  const lista = document.getElementById("roomiesList");
  const contador = document.getElementById("roomiesResultsCount");

  try {
    const params = construirQueryParams();
    const resp = await fetch(`${API_BASE}/roomies?${params.toString()}`);
    if (!resp.ok) throw new Error("Error al consultar roomies");

    const { data, meta } = await resp.json();

    contador.textContent = `${meta.total} resultado${meta.total === 1 ? "" : "s"} en Bogotá`;

    if (!data.length) {
      lista.innerHTML = `
        <div class="roomies-empty">
          No encontramos personas que coincidan con estos filtros.
          <br/><button id="limpiarDesdeVacioBtn">Limpiar filtros</button>
        </div>`;
      document.getElementById("limpiarDesdeVacioBtn").addEventListener("click", limpiarFiltros);
      document.getElementById("roomiesPagination").innerHTML = "";
      return;
    }

    lista.innerHTML = data.map((item) => `
      <div class="roomie-card">
        <div class="roomie-card-avatar-wrap">
          <img src="${item.img}" alt="${item.nombre}"/>
          <span class="roomie-card-badge">${item.rating > 0 ? item.rating : "Nuevo"}</span>
        </div>
        <div class="roomie-card-body">
          <div class="roomie-card-name">${item.nombre} <span>· ${item.edad} años</span></div>
          <div class="roomie-card-sub">${item.ocupacion} · Busca en ${item.zona}, ${item.ciudad}</div>
          <div class="roomie-card-tags">
            ${item.tags.map((t) => `<span class="roomie-card-tag">${t}</span>`).join("")}
          </div>
          <div class="roomie-card-footer">
            <div class="roomie-card-price">${formatCOP(item.presupuesto)}<span>presupuesto/mes</span></div>
            <a class="roomie-card-btn" href="/roomie/${item.id}">Ver perfil</a>
          </div>
        </div>
      </div>`).join("");

    renderPaginacion(meta.last_page);
  } catch (err) {
    console.error(err);
    lista.innerHTML = `<div class="roomies-empty">Ocurrió un error al cargar los perfiles. Intenta de nuevo.</div>`;
  }
}

function renderPaginacion(totalPaginas) {
  const wrap = document.getElementById("roomiesPagination");
  wrap.innerHTML = "";
  for (let i = 1; i <= totalPaginas; i++) {
    const btn = document.createElement("button");
    btn.className = "roomies-page-btn" + (i === paginaActual ? " active" : "");
    btn.textContent = i;
    btn.addEventListener("click", () => {
      paginaActual = i;
      cargarResultados();
      document.querySelector(".roomies-results").scrollIntoView({ behavior: "smooth", block: "start" });
    });
    wrap.appendChild(btn);
  }
}

function limpiarFiltros() {
  document.getElementById("filtroZonaRoomie").value = "";
  document.querySelectorAll(".filtroOcupacion").forEach((c) => (c.checked = false));
  document.querySelectorAll(".filtroGenero").forEach((c) => (c.checked = false));
  document.getElementById("presupuestoMin").value = "";
  document.getElementById("presupuestoMax").value = "";
  document.querySelector('input[name="calificacionRoomie"][value="0"]').checked = true;
  document.querySelectorAll(".filter-group input[type=checkbox]").forEach((c) => (c.checked = false));
  document.getElementById("filtroAmbienteRoomie").value = "";
  document.getElementById("filtroHorario").value = "";
  document.getElementById("filtroTiempoBusqueda").value = "";
  document.getElementById("filtroMudanza").value = "";
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
document.getElementById("filtroZonaRoomie").addEventListener("input", refrescarConDebounce);
document.getElementById("presupuestoMin").addEventListener("input", refrescarConDebounce);
document.getElementById("presupuestoMax").addEventListener("input", refrescarConDebounce);
document.querySelectorAll(".filtroOcupacion").forEach((c) => c.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.querySelectorAll(".filtroGenero").forEach((c) => c.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.querySelectorAll('input[name="calificacionRoomie"]').forEach((r) => r.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.querySelectorAll(".filter-group input[type=checkbox]").forEach((c) => c.addEventListener("change", () => { paginaActual = 1; cargarResultados(); }));
document.getElementById("filtroAmbienteRoomie").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("filtroHorario").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("filtroTiempoBusqueda").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("filtroMudanza").addEventListener("change", () => { paginaActual = 1; cargarResultados(); });
document.getElementById("roomiesSort").addEventListener("change", (e) => { ordenActual = e.target.value; paginaActual = 1; cargarResultados(); });
document.getElementById("limpiarFiltrosRoomieBtn").addEventListener("click", limpiarFiltros);

// ---------- INICIALIZACIÓN ----------
const parametrosURL = new URLSearchParams(window.location.search);
const zonaDesdeURL = parametrosURL.get("zona");
if (zonaDesdeURL) {
  document.getElementById("filtroZonaRoomie").value = zonaDesdeURL;
}

cargarResultados();
