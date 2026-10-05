// ============================================================
// ROOMMATCH – lógica de la página CREAR PUBLICACIÓN
// ------------------------------------------------------------
// Misma UX que la versión original (wizard de 5 pasos), pero:
//   - Sesión: token + usuario de localStorage (igual que dashboard.js)
//   - Modo edición (?id=): precarga vía GET /api/mis-publicaciones/{id}
//   - Publicar/Guardar: POST o PUT contra /api/mis-publicaciones
//   - Ya NO depende de data.js (guardarPublicacionExtra, etc.)
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

// ---------- LOCALIDADES DE BOGOTÁ ----------
const LOCALIDADES_BOGOTA = [
  "Antonio Nariño",
  "Barrios Unidos",
  "Bosa",
  "Chapinero",
  "Ciudad Bolívar",
  "Engativá",
  "Fontibón",
  "Kennedy",
  "La Candelaria",
  "Los Mártires",
  "Puente Aranda",
  "Rafael Uribe Uribe",
  "San Cristóbal",
  "Santa Fe",
  "Suba",
  "Sumapaz",
  "Teusaquillo",
  "Tunjuelito",
  "Usaquén",
  "Usme",
];

// ---------- PROTECCIÓN DE SESIÓN ----------
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

// ---------- MODO EDICIÓN (?id=<id>) ----------
const parametrosUrl = new URLSearchParams(window.location.search);
const idPublicacionEditar = parametrosUrl.has("id") ? Number(parametrosUrl.get("id")) : null;
const modoEdicion = idPublicacionEditar !== null;
const tipoDesdeUrl = parametrosUrl.get("tipo"); // "espacio" (por ahora es el único caso)

// ============================================================
// ESTADO DEL WIZARD
// ============================================================
const MAX_FOTOS = 8;
const FOTO_MAX_LADO = 900;
const FOTO_CALIDAD = 0.82;
const FOTO_MAX_PESO_ORIGINAL = 8 * 1024 * 1024;

const PASOS = ["basico", "caracteristicas", "reglas", "fotos", "resumen"];
const PASO_LABELS = { basico: "Básico", caracteristicas: "Detalles", reglas: "Reglas", fotos: "Fotos", resumen: modoEdicion ? "Guardar" : "Publicar" };

let pasoActual = (modoEdicion || tipoDesdeUrl === "espacio") ? 1 : 0;let estado = estadoVacio();

function estadoVacio() {
  return {
    tipo_espacio: "", titulo: "", zona: "", ciudad: "Bogotá", direccion: "",
    precio: "", fecha_disponible: "", genero: "", descripcion: "",
    numero_habitantes: "", telefono_contacto: usuarioActual.telefono || "",
    ambiente_hogar: "", horario_entrada: "", horario_silencio: "",
    detalles_incluidos: "", distancia_transporte: "", tamano_habitacion: "", tipo_cama: "",
    amueblado: false, bano_privado: false, cocina_compartida: false, lavadora: false,
    secadora: false, parqueadero: false, balcon: false, terraza: false,
    incluye_agua: false, incluye_luz: false, incluye_internet: false, incluye_gas: false,
    habitacion_compartida: false,
    ascensor: false, gimnasio: false, zona_comun: false,
    porteria: false, camaras_seguridad: false,
    espacio_trabajo: false,
    cerca_transporte_publico: false, cerca_supermercado: false, cerca_universidad: false,
    permite_mascotas: false, permite_visitas: false, permite_fumar: false,
    permite_fiestas: false, permite_parejas: false,
    fumadores_en_casa: false, mascotas_en_casa: false,
    imagenes: [],
  };
}

function llenarEstadoDesdePublicacion(pub) {
  const c = pub.caracteristicas || {};
  estado = {
    ...estado,
    tipo_espacio: pub.tipo_espacio || "",
    titulo: pub.titulo || "",
    zona: pub.zona || "",
    ciudad: pub.ciudad || "Bogotá",
    direccion: pub.direccion || "",
    precio: pub.precio ?? "",
    fecha_disponible: pub.fecha_disponible || "",
    genero: pub.genero_preferido || "",
    descripcion: pub.descripcion || "",
    numero_habitantes: c.numero_habitantes ?? "",
    ambiente_hogar: c.ambiente_hogar || "",
    horario_entrada: c.horario_entrada || "",
    horario_silencio: c.horario_silencio || "",
    detalles_incluidos: c.detalles_incluidos || "",
    distancia_transporte: c.distancia_transporte || "",
    tamano_habitacion: c.tamano_habitacion || "",
    tipo_cama: c.tipo_cama || "",
    amueblado: !!c.amueblado, bano_privado: !!c.bano_privado, cocina_compartida: !!c.cocina_compartida, lavadora: !!c.lavadora,
    secadora: !!c.secadora, parqueadero: !!c.parqueadero, balcon: !!c.balcon, terraza: !!c.terraza,
    incluye_agua: !!c.incluye_agua, incluye_luz: !!c.incluye_luz, incluye_internet: !!c.incluye_internet, incluye_gas: !!c.incluye_gas,
    habitacion_compartida: !!c.habitacion_compartida,
    ascensor: !!c.ascensor, gimnasio: !!c.gimnasio, zona_comun: !!c.zona_comun,
    porteria: !!c.porteria, camaras_seguridad: !!c.camaras_seguridad,
    espacio_trabajo: !!c.espacio_trabajo,
    cerca_transporte_publico: !!c.cerca_transporte_publico, cerca_supermercado: !!c.cerca_supermercado, cerca_universidad: !!c.cerca_universidad,
    permite_mascotas: !!c.permite_mascotas, permite_visitas: !!c.permite_visitas, permite_fumar: !!c.permite_fumar,
    permite_fiestas: !!c.permite_fiestas, permite_parejas: !!c.permite_parejas,
    fumadores_en_casa: !!c.fumadores_en_casa, mascotas_en_casa: !!c.mascotas_en_casa,
    imagenes: Array.isArray(pub.imagenes) ? [...pub.imagenes] : [],
  };
}

const cpPage = document.getElementById("cpPage");

// ============================================================
// HELPERS DE CAMPOS
// ============================================================
function campoTexto({ key, label, placeholder = "", tipo = "text", hint = "" }) {
  return `
    <div class="cp-field" data-field="${key}">
      <label for="cp_${key}">${label}</label>
      <input id="cp_${key}" type="${tipo}" data-bind="${key}" placeholder="${placeholder}" value="${(estado[key] ?? "").toString().replace(/"/g, "&quot;")}"/>
      ${hint ? `<p class="cp-field-hint">${hint}</p>` : ""}
      <p class="cp-field-error" data-error></p>
    </div>`;
}
function campoTextarea({ key, label, placeholder = "" }) {
  return `
    <div class="cp-field" data-field="${key}">
      <label for="cp_${key}">${label}</label>
      <textarea id="cp_${key}" data-bind="${key}" placeholder="${placeholder}">${estado[key] ?? ""}</textarea>
      <p class="cp-field-error" data-error></p>
    </div>`;
}
// Dropdown personalizado (reemplaza al <select> nativo en todo el wizard
// para poder darle una apariencia propia; los navegadores no permiten
// estilizar el panel desplegable de un <select> nativo).
function campoSelect({ key, label, opciones, placeholder = "Selecciona una opción" }) {
  const valorActual = estado[key];
  const opcionSeleccionada = opciones.find(([v]) => v === valorActual);
  const textoActual = opcionSeleccionada ? opcionSeleccionada[1] : placeholder;

  const itemPlaceholder = `
    <li class="cp-select-option cp-select-option--placeholder${!valorActual ? " is-selected" : ""}" role="option" tabindex="-1" data-value="" data-placeholder>
      ${placeholder}
    </li>`;

  const itemsOpciones = opciones.map(([v, txt]) => `
    <li class="cp-select-option${valorActual === v ? " is-selected" : ""}" role="option" tabindex="-1" data-value="${v.toString().replace(/"/g, "&quot;")}">
      <span>${txt}</span>
      <svg class="cp-select-check" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
    </li>`).join("");

  return `
    <div class="cp-field" data-field="${key}">
      <label id="cp_${key}_label">${label}</label>
      <div class="cp-select" data-select="${key}">
        <button type="button" class="cp-select-trigger" id="cp_${key}" data-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-labelledby="cp_${key}_label">
          <span class="cp-select-value${opcionSeleccionada ? "" : " is-placeholder"}" data-select-value>${textoActual}</span>
          <svg class="cp-select-chevron" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
        </button>
        <ul class="cp-select-menu" role="listbox" data-select-menu hidden>
          ${itemPlaceholder}
          ${itemsOpciones}
        </ul>
      </div>
      <p class="cp-field-error" data-error></p>
    </div>`;
}
function toggleRow(key, label) {
  return `
    <div class="cp-toggle-row">
      <span class="cp-toggle-label">${label}</span>
      <label class="cp-switch">
        <input type="checkbox" data-bind-toggle="${key}" ${estado[key] ? "checked" : ""}/>
        <span class="cp-switch-track"></span>
      </label>
    </div>`;
}

function enlazarCampos(cont) {
  cont.querySelectorAll("[data-bind]").forEach((el) => {
    el.addEventListener("input", () => { estado[el.dataset.bind] = el.value; limpiarError(el); });
    el.addEventListener("change", () => { estado[el.dataset.bind] = el.value; limpiarError(el); });
  });
  cont.querySelectorAll("[data-bind-toggle]").forEach((el) => {
    el.addEventListener("change", () => { estado[el.dataset.bindToggle] = el.checked; });
  });
  cont.querySelectorAll("[data-select]").forEach((wrapper) => enlazarSelectPersonalizado(wrapper));
}
function limpiarError(el) {
  const row = el.closest(".cp-field");
  if (!row) return;
  row.classList.remove("has-error");
  const err = row.querySelector("[data-error]");
  if (err) { err.textContent = ""; err.classList.remove("is-visible"); }
}
function limpiarErrorPorKey(key) {
  const row = cpPage.querySelector(`.cp-field[data-field="${key}"]`);
  if (!row) return;
  row.classList.remove("has-error");
  const err = row.querySelector("[data-error]");
  if (err) { err.textContent = ""; err.classList.remove("is-visible"); }
}

// ---------- DROPDOWN PERSONALIZADO (reemplaza al <select> nativo) ----------
function enlazarSelectPersonalizado(wrapper) {
  const key = wrapper.dataset.select;
  const trigger = wrapper.querySelector("[data-select-trigger]");
  const menu = wrapper.querySelector("[data-select-menu]");
  const valueEl = wrapper.querySelector("[data-select-value]");
  const opciones = Array.from(menu.querySelectorAll(".cp-select-option"));

  function cerrar() {
    menu.hidden = true;
    trigger.setAttribute("aria-expanded", "false");
    wrapper.classList.remove("is-open");
    document.removeEventListener("click", manejarClickFuera);
  }
  function manejarClickFuera(e) {
    if (!wrapper.contains(e.target)) cerrar();
  }
  function abrir() {
    document.querySelectorAll(".cp-select.is-open").forEach((otro) => {
      if (otro === wrapper) return;
      otro.querySelector("[data-select-menu]").hidden = true;
      otro.classList.remove("is-open");
    });
    menu.hidden = false;
    trigger.setAttribute("aria-expanded", "true");
    wrapper.classList.add("is-open");
    document.addEventListener("click", manejarClickFuera);
  }

  trigger.addEventListener("click", (e) => {
    e.stopPropagation();
    if (menu.hidden) abrir(); else cerrar();
  });

  trigger.addEventListener("keydown", (e) => {
    if (e.key === "Enter" || e.key === " " || e.key === "ArrowDown") {
      e.preventDefault();
      abrir();
      (menu.querySelector(".cp-select-option.is-selected") || opciones[0])?.focus();
    } else if (e.key === "Escape") {
      cerrar();
    }
  });

  opciones.forEach((opt, i) => {
    opt.addEventListener("click", () => {
      const val = opt.dataset.value;
      const esPlaceholder = opt.dataset.placeholder !== undefined;
      const etiqueta = esPlaceholder ? opt.textContent.trim() : opt.querySelector("span").textContent;

      estado[key] = val;
      valueEl.textContent = etiqueta;
      valueEl.classList.toggle("is-placeholder", esPlaceholder);
      opciones.forEach((o) => o.classList.remove("is-selected"));
      opt.classList.add("is-selected");

      cerrar();
      limpiarErrorPorKey(key);
      trigger.focus();
    });

    opt.addEventListener("keydown", (e) => {
      if (e.key === "ArrowDown") { e.preventDefault(); (opciones[i + 1] || opciones[0]).focus(); }
      else if (e.key === "ArrowUp") { e.preventDefault(); (opciones[i - 1] || opciones[opciones.length - 1]).focus(); }
      else if (e.key === "Enter" || e.key === " ") { e.preventDefault(); opt.click(); }
      else if (e.key === "Escape") { cerrar(); trigger.focus(); }
    });
  });
}
function marcarError(key, mensaje) {
  const row = cpPage.querySelector(`.cp-field[data-field="${key}"]`);
  if (!row) return;
  row.classList.add("has-error");
  const err = row.querySelector("[data-error]");
  err.textContent = mensaje;
  err.classList.add("is-visible");
}

// ============================================================
// PASO 0 – SELECTOR: espacio vs roomie
// ============================================================
function renderPaso0() {
  cpPage.innerHTML = `
    <h1 class="cp-intro-title">¿Qué quieres crear?</h1>
    <p class="cp-intro-subtitle">Elige el tipo de publicación que quieres hacer en Roommatch</p>
    <div class="cp-choice-grid">
      <button type="button" class="cp-choice-card" id="choiceEspacio">
        <span class="cp-choice-icon">
          <svg viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/></svg>
        </span>
        <p class="cp-choice-title">Publicar un espacio</p>
        <p class="cp-choice-desc">Ofrece una habitación, apartamento, casa o estudio para que otros usuarios lo encuentren.</p>
      </button>
      <button type="button" class="cp-choice-card" id="choiceRoomie">
        <span class="cp-choice-icon">
          <svg viewBox="0 0 24 24"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>
        </span>
        <p class="cp-choice-title">Publicarme como roomie</p>
        <p class="cp-choice-desc">Crea tu perfil para que otros oferentes o buscadores te encuentren como compañero/a.</p>
      </button>
    </div>`;

  document.getElementById("choiceEspacio").addEventListener("click", () => {
    pasoActual = 1;
    renderPasoActual();
  });
  document.getElementById("choiceRoomie").addEventListener("click", () => {
    window.location.href = "/crear-perfil-roomie";
  });
}

// ============================================================
// PROGRESO
// ============================================================
function renderProgreso() {
  const idx = pasoActual - 1;
  const pct = (idx / (PASOS.length - 1)) * 100;
  return `
    <div class="cp-progress-bar"><div class="cp-progress-bar-fill" style="width:${pct}%"></div></div>
    <div class="cp-progress">
      ${PASOS.map((p, i) => `
        <div class="cp-progress-step ${i === idx ? "is-active" : ""} ${i < idx ? "is-done" : ""}">
          <span class="cp-progress-dot">${i < idx ? "✓" : i + 1}</span>
          <span class="cp-progress-label">${PASO_LABELS[p]}</span>
        </div>`).join("")}
    </div>`;
}

// ============================================================
// PASO 1 – BÁSICO
// ============================================================
function renderBasico() {
  cpPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">${modoEdicion ? "Edita la información básica" : "Información básica"}</h1>
    <p class="cp-step-subtitle">Cuéntanos qué vas a publicar y dónde está</p>

    ${campoSelect({ key: "tipo_espacio", label: "Tipo de espacio", opciones: [["Habitación", "Habitación"], ["Apartamento", "Apartamento"], ["Casa", "Casa"], ["Estudio", "Estudio"]] })}
    ${campoTexto({ key: "titulo", label: "Título de la publicación", placeholder: "Ej. Habitación amoblada en Chapinero" })}

    <div class="cp-field-row">
      ${campoSelect({ key: "zona", label: "Zona o localidad", opciones: LOCALIDADES_BOGOTA.map((loc) => [loc, loc]), placeholder: "Selecciona una localidad" })}
      ${campoTexto({ key: "ciudad", label: "Ciudad", placeholder: "Ej. Bogotá" })}
    </div>
    ${campoTexto({ key: "direccion", label: "Dirección", placeholder: "Ej. Calle 60 #7-15" })}

    <div class="cp-field-row">
      ${campoTexto({ key: "precio", label: "Precio mensual (COP)", tipo: "number" })}
      ${campoTexto({ key: "fecha_disponible", label: "Disponible desde", tipo: "date" })}
    </div>

    <div class="cp-field-row">
      ${campoSelect({ key: "genero", label: "Género preferido", opciones: [["masculino", "Masculino"], ["femenino", "Femenino"], ["otro", "Cualquiera"]], placeholder: "Sin preferencia" })}
      ${campoTexto({ key: "numero_habitantes", label: "N.º de habitantes actuales", tipo: "number" })}
    </div>

    ${campoTexto({ key: "telefono_contacto", label: "Teléfono de contacto (WhatsApp)", tipo: "tel", placeholder: "Ej. 573001112233", hint: "Con indicativo de país, solo números. Es el número que verán los interesados al contactarte." })}

    ${campoTextarea({ key: "descripcion", label: "Descripción", placeholder: "Cuenta cómo es el espacio, el ambiente y a quién le puede interesar" })}

    <div class="cp-nav">
      <a class="cp-btn cp-btn-ghost" href="${modoEdicion ? "/mis-publicaciones" : "/dashboard"}">Cancelar</a>
      <button type="button" class="cp-btn cp-btn-primary" id="cpNext">Siguiente</button>
    </div>`;

  enlazarCampos(cpPage);
  document.getElementById("cpNext").addEventListener("click", () => {
    if (!validarBasico()) return;
    pasoActual = 2;
    renderPasoActual();
  });
}

function validarBasico() {
  let ok = true;
  if (!estado.tipo_espacio) { marcarError("tipo_espacio", "Selecciona el tipo de espacio."); ok = false; }
  if (!estado.titulo || estado.titulo.trim().length < 5) { marcarError("titulo", "Escribe un título de al menos 5 caracteres."); ok = false; }
  if (!estado.zona || !LOCALIDADES_BOGOTA.includes(estado.zona)) { marcarError("zona", "Selecciona una localidad de la lista."); ok = false; }
  if (!estado.direccion || estado.direccion.trim().length < 3) { marcarError("direccion", "Ingresa una dirección válida."); ok = false; }
  if (!estado.precio || Number(estado.precio) <= 0) { marcarError("precio", "Ingresa un precio válido."); ok = false; }
  if (!estado.fecha_disponible) { marcarError("fecha_disponible", "Selecciona una fecha de disponibilidad."); ok = false; }
  const telefonoLimpio = limpiarTelefono(estado.telefono_contacto);
  if (telefonoLimpio.length < 10) { marcarError("telefono_contacto", "Ingresa un número de WhatsApp válido, con indicativo de país (ej. 573001112233)."); ok = false; }
  if (!estado.descripcion || estado.descripcion.trim().length < 15) { marcarError("descripcion", "Describe el espacio con al menos 15 caracteres."); ok = false; }
  return ok;
}

function limpiarTelefono(str) {
  return (str || "").replace(/\D/g, "");
}

// ============================================================
// PASO 2 – CARACTERÍSTICAS
// ============================================================
function renderCaracteristicas() {
  cpPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Características del espacio</h1>
    <p class="cp-step-subtitle">Marca todo lo que aplique; esto ayuda a que aparezcas en más búsquedas</p>

    <div class="cp-group">
      <p class="cp-group-title">Espacio</p>
      <div class="cp-toggle-grid">
        ${toggleRow("amueblado", "Amueblado")}
        ${toggleRow("bano_privado", "Baño privado")}
        ${toggleRow("cocina_compartida", "Cocina compartida")}
        ${toggleRow("lavadora", "Lavadora")}
        ${toggleRow("secadora", "Secadora")}
        ${toggleRow("parqueadero", "Parqueadero")}
        ${toggleRow("balcon", "Balcón")}
        ${toggleRow("terraza", "Terraza")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Servicios incluidos en el precio</p>
      <div class="cp-toggle-grid">
        ${toggleRow("incluye_agua", "Agua")}
        ${toggleRow("incluye_luz", "Luz")}
        ${toggleRow("incluye_internet", "Internet")}
        ${toggleRow("incluye_gas", "Gas")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Habitación</p>
      <div class="cp-field-row">
        ${campoSelect({ key: "tamano_habitacion", label: "Tamaño", opciones: [["pequena", "Pequeña"], ["mediana", "Mediana"], ["grande", "Grande"]] })}
        ${campoSelect({ key: "tipo_cama", label: "Tipo de cama", opciones: [["sencilla", "Sencilla"], ["semidoble", "Semidoble"], ["doble", "Doble"], ["queen", "Queen"], ["king", "King"]] })}
      </div>
      <div class="cp-toggle-grid">${toggleRow("habitacion_compartida", "Habitación compartida")}</div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Edificio y seguridad</p>
      <div class="cp-toggle-grid">
        ${toggleRow("ascensor", "Ascensor")}
        ${toggleRow("gimnasio", "Gimnasio")}
        ${toggleRow("zona_comun", "Zona común")}
        ${toggleRow("porteria", "Portería")}
        ${toggleRow("camaras_seguridad", "Cámaras de seguridad")}
        ${toggleRow("espacio_trabajo", "Espacio de trabajo/estudio")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Cercanías</p>
      <div class="cp-toggle-grid">
        ${toggleRow("cerca_transporte_publico", "Cerca a transporte público")}
        ${toggleRow("cerca_supermercado", "Cerca a supermercado")}
        ${toggleRow("cerca_universidad", "Cerca a universidad")}
      </div>
      ${campoTexto({ key: "distancia_transporte", label: "Distancia al transporte (opcional)", placeholder: "Ej. 250 metros" })}
    </div>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cpBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cpNext">Siguiente</button>
    </div>`;

  enlazarCampos(cpPage);
  document.getElementById("cpBack").addEventListener("click", () => { pasoActual = 1; renderPasoActual(); });
  document.getElementById("cpNext").addEventListener("click", () => { pasoActual = 3; renderPasoActual(); });
}

// ============================================================
// PASO 3 – REGLAS Y CONVIVENCIA
// ============================================================
function renderReglas() {
  cpPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Reglas y convivencia</h1>
    <p class="cp-step-subtitle">Define el ambiente y las reglas del hogar</p>

    <div class="cp-group">
      <p class="cp-group-title">Ambiente del hogar</p>
      ${campoSelect({ key: "ambiente_hogar", label: "¿Qué ambiente predomina?", opciones: [["estudiantes", "Estudiantes"], ["profesionales", "Profesionales"], ["otro", "Otro / mixto"]] })}
      <div class="cp-field-row">
        ${campoTexto({ key: "horario_entrada", label: "Horario de entrada", placeholder: "Ej. Hasta las 11:00pm" })}
        ${campoTexto({ key: "horario_silencio", label: "Horario de silencio", placeholder: "Ej. 10:00pm - 7:00am" })}
      </div>
      <div class="cp-toggle-grid">
        ${toggleRow("fumadores_en_casa", "Ya hay fumadores en casa")}
        ${toggleRow("mascotas_en_casa", "Ya hay mascotas en casa")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Reglas del hogar</p>
      <div class="cp-toggle-grid">
        ${toggleRow("permite_mascotas", "Permite mascotas")}
        ${toggleRow("permite_visitas", "Permite visitas")}
        ${toggleRow("permite_fumar", "Permite fumar")}
        ${toggleRow("permite_fiestas", "Permite fiestas/reuniones")}
        ${toggleRow("permite_parejas", "Permite parejas")}
      </div>
    </div>

    ${campoTexto({ key: "detalles_incluidos", label: "Detalles adicionales sobre lo incluido (opcional)", placeholder: "Ej. Todos los servicios básicos están incluidos" })}

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cpBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cpNext">Siguiente</button>
    </div>`;

  enlazarCampos(cpPage);
  document.getElementById("cpBack").addEventListener("click", () => { pasoActual = 2; renderPasoActual(); });
  document.getElementById("cpNext").addEventListener("click", () => { pasoActual = 4; renderPasoActual(); });
}

// ============================================================
// PASO 4 – FOTOS
// ============================================================
function comprimirImagen(archivo) {
  return new Promise((resolve, reject) => {
    const lector = new FileReader();
    lector.onerror = () => reject(new Error("No se pudo leer el archivo."));
    lector.onload = () => {
      const img = new Image();
      img.onerror = () => reject(new Error("El archivo no es una imagen válida."));
      img.onload = () => {
        const escala = Math.min(1, FOTO_MAX_LADO / Math.max(img.width, img.height));
        const ancho = Math.round(img.width * escala);
        const alto = Math.round(img.height * escala);
        const canvas = document.createElement("canvas");
        canvas.width = ancho;
        canvas.height = alto;
        canvas.getContext("2d").drawImage(img, 0, 0, ancho, alto);
        resolve(canvas.toDataURL("image/jpeg", FOTO_CALIDAD));
      };
      img.src = lector.result;
    };
    lector.readAsDataURL(archivo);
  });
}

function renderFotos() {
  cpPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Fotos del espacio</h1>
    <p class="cp-step-subtitle">Sube hasta ${MAX_FOTOS} fotos. La primera será la portada.</p>

    <div class="cp-photos-grid" id="cpPhotosGrid"></div>
    <input type="file" id="cpFotoInput" accept="image/png, image/jpeg, image/webp" multiple hidden/>
    <p class="cp-field-error" id="cpFotoError"></p>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cpBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cpNext">Siguiente</button>
    </div>`;

  pintarGridFotos();

  document.getElementById("cpBack").addEventListener("click", () => { pasoActual = 3; renderPasoActual(); });
  document.getElementById("cpNext").addEventListener("click", () => {
    if (!estado.imagenes.length) {
      document.getElementById("cpFotoError").textContent = "Sube al menos una foto para publicar.";
      document.getElementById("cpFotoError").classList.add("is-visible");
      return;
    }
    pasoActual = 5;
    renderPasoActual();
  });
}

function pintarGridFotos() {
  const grid = document.getElementById("cpPhotosGrid");
  const slots = estado.imagenes.map((src, i) => `
    <div class="cp-photo-slot">
      <img src="${src}" alt="Foto ${i + 1}"/>
      ${i === 0 ? '<span class="cp-photo-cover-tag">Portada</span>' : ""}
      <button type="button" class="cp-photo-remove" data-remove-foto="${i}" title="Quitar foto">✕</button>
    </div>`).join("");

  const addBtn = estado.imagenes.length < MAX_FOTOS ? `
    <button type="button" class="cp-photo-add" id="cpAddFoto">
      <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
      Agregar foto
    </button>` : "";

  grid.innerHTML = slots + addBtn;

  const addBtnEl = document.getElementById("cpAddFoto");
  if (addBtnEl) addBtnEl.addEventListener("click", () => document.getElementById("cpFotoInput").click());

  grid.querySelectorAll("[data-remove-foto]").forEach((btn) => {
    btn.addEventListener("click", () => {
      estado.imagenes.splice(Number(btn.dataset.removeFoto), 1);
      pintarGridFotos();
    });
  });

  document.getElementById("cpFotoInput").addEventListener("change", async (e) => {
    const archivos = Array.from(e.target.files || []);
    e.target.value = "";
    const errorEl = document.getElementById("cpFotoError");
    errorEl.textContent = "";
    errorEl.classList.remove("is-visible");

    for (const archivo of archivos) {
      if (estado.imagenes.length >= MAX_FOTOS) break;
      if (!archivo.type.startsWith("image/")) {
        errorEl.textContent = "Solo se aceptan archivos de imagen (JPG, PNG o WEBP).";
        errorEl.classList.add("is-visible");
        continue;
      }
      if (archivo.size > FOTO_MAX_PESO_ORIGINAL) {
        errorEl.textContent = "Alguna imagen pesa demasiado. Usa fotos de menos de 8MB.";
        errorEl.classList.add("is-visible");
        continue;
      }
      try {
        const dataUrl = await comprimirImagen(archivo);
        estado.imagenes.push(dataUrl);
      } catch (err) {
        errorEl.textContent = "No se pudo procesar alguna imagen. Intenta con otra.";
        errorEl.classList.add("is-visible");
      }
    }
    pintarGridFotos();
  }, { once: false });
}

// ============================================================
// PASO 5 – RESUMEN Y PUBLICAR
// ============================================================
const ETIQUETAS_RESUMEN = {
  amueblado: "Amueblado", bano_privado: "Baño privado", cocina_compartida: "Cocina compartida",
  lavadora: "Lavadora", secadora: "Secadora", parqueadero: "Parqueadero", balcon: "Balcón", terraza: "Terraza",
  incluye_agua: "Agua incluida", incluye_luz: "Luz incluida", incluye_internet: "Internet incluido", incluye_gas: "Gas incluido",
  habitacion_compartida: "Habitación compartida", ascensor: "Ascensor", gimnasio: "Gimnasio", zona_comun: "Zona común",
  porteria: "Portería", camaras_seguridad: "Cámaras de seguridad", espacio_trabajo: "Espacio de trabajo",
  cerca_transporte_publico: "Cerca a transporte", cerca_supermercado: "Cerca a supermercado", cerca_universidad: "Cerca a universidad",
  permite_mascotas: "Permite mascotas", permite_visitas: "Permite visitas", permite_fumar: "Permite fumar",
  permite_fiestas: "Permite fiestas", permite_parejas: "Permite parejas",
};

function formatCOP(n) {
  return "$" + Math.round(Number(n) || 0).toLocaleString("es-CO") + " COP";
}

function renderResumen() {
  const etiquetasActivas = Object.keys(ETIQUETAS_RESUMEN).filter((k) => estado[k]);

  cpPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Revisa tu publicación</h1>
    <p class="cp-step-subtitle">${modoEdicion ? "Revisa los cambios antes de guardarlos" : "Así se verá antes de publicarla"}</p>

    <div class="cp-summary-card">
      <img class="cp-summary-img" src="${estado.imagenes[0] || ""}" alt="${estado.titulo}"/>
      <div class="cp-summary-body">
        <p class="cp-summary-title">${estado.titulo}</p>
        <p class="cp-summary-sub">${estado.tipo_espacio} · ${estado.zona}, ${estado.ciudad}</p>
        <p class="cp-summary-price">${formatCOP(estado.precio)} / mes</p>
        <ul class="cp-summary-list">
          ${etiquetasActivas.map((k) => `<li>${ETIQUETAS_RESUMEN[k]}</li>`).join("") || "<li>Sin características marcadas</li>"}
        </ul>
      </div>
    </div>

    <p class="cp-field-error is-visible" id="cpErrorGeneral" style="text-align:center;"></p>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cpBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cpPublicar">${modoEdicion ? "Guardar cambios" : "Publicar"}</button>
    </div>`;

  document.getElementById("cpBack").addEventListener("click", () => { pasoActual = 4; renderPasoActual(); });
  document.getElementById("cpPublicar").addEventListener("click", publicar);
}

async function publicar() {
  const btn = document.getElementById("cpPublicar");
  const errorGeneral = document.getElementById("cpErrorGeneral");
  btn.disabled = true;
  btn.textContent = modoEdicion ? "Guardando..." : "Publicando...";
  errorGeneral.textContent = "";

  const payload = {
    tipo_espacio: estado.tipo_espacio,
    titulo: estado.titulo.trim(),
    zona: estado.zona.trim(),
    ciudad: estado.ciudad.trim() || "Bogotá",
    direccion: estado.direccion.trim(),
    precio: Number(estado.precio),
    fecha_disponible: estado.fecha_disponible,
    genero: estado.genero || null,
    numero_habitantes: estado.numero_habitantes ? Number(estado.numero_habitantes) : null,
    telefono_contacto: limpiarTelefono(estado.telefono_contacto),
    descripcion: estado.descripcion.trim(),
    imagenes: estado.imagenes,
    caracteristicas: {
      amueblado: estado.amueblado, bano_privado: estado.bano_privado, cocina_compartida: estado.cocina_compartida,
      lavadora: estado.lavadora, secadora: estado.secadora, parqueadero: estado.parqueadero,
      balcon: estado.balcon, terraza: estado.terraza,
      incluye_agua: estado.incluye_agua, incluye_luz: estado.incluye_luz,
      incluye_internet: estado.incluye_internet, incluye_gas: estado.incluye_gas,
      ambiente_hogar: estado.ambiente_hogar || null,
      fumadores_en_casa: estado.fumadores_en_casa, mascotas_en_casa: estado.mascotas_en_casa,
      tamano_habitacion: estado.tamano_habitacion || null, tipo_cama: estado.tipo_cama || null,
      habitacion_compartida: estado.habitacion_compartida,
      permite_mascotas: estado.permite_mascotas, permite_visitas: estado.permite_visitas,
      permite_fumar: estado.permite_fumar, permite_fiestas: estado.permite_fiestas, permite_parejas: estado.permite_parejas,
      cerca_transporte_publico: estado.cerca_transporte_publico,
      porteria: estado.porteria, camaras_seguridad: estado.camaras_seguridad,
      espacio_trabajo: estado.espacio_trabajo,
      ascensor: estado.ascensor, gimnasio: estado.gimnasio, zona_comun: estado.zona_comun,
      cerca_supermercado: estado.cerca_supermercado, cerca_universidad: estado.cerca_universidad,
      detalles_incluidos: estado.detalles_incluidos.trim() || null,
      numero_habitantes: estado.numero_habitantes ? Number(estado.numero_habitantes) : null,
      horario_silencio: estado.horario_silencio.trim() || null,
      horario_entrada: estado.horario_entrada.trim() || null,
      distancia_transporte: estado.distancia_transporte.trim() || null,
    },
  };

  try {
    const url = modoEdicion ? `${API_BASE}/mis-publicaciones/${idPublicacionEditar}` : `${API_BASE}/mis-publicaciones`;
    const respuesta = await fetch(url, {
      method: modoEdicion ? "PUT" : "POST",
      headers: cabecerasAutenticadas(),
      body: JSON.stringify(payload),
    });

    if (manejarNoAutorizado(respuesta)) return;

    const datos = await respuesta.json();

    if (!respuesta.ok) {
      // Perfil sin tipo_usuario/telefono: el backend no deja crear
      // nada nuevo hasta que se complete (ver perfil.completo).
      if (respuesta.status === 409) {
        errorGeneral.textContent = datos.mensaje || "Completa tu perfil antes de continuar.";
        setTimeout(() => { window.location.href = "/onboarding"; }, 1500);
        return;
      }

      btn.disabled = false;
      btn.textContent = modoEdicion ? "Guardar cambios" : "Publicar";
      errorGeneral.textContent = datos.mensaje || "No se pudo guardar la publicación. Revisa los datos e intenta de nuevo.";
      return;
    }

    renderExito();
  } catch (err) {
    btn.disabled = false;
    btn.textContent = modoEdicion ? "Guardar cambios" : "Publicar";
    errorGeneral.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
  }
}

function renderExito() {
  cpPage.innerHTML = `
    <div class="cp-success">
      <div class="cp-success-icon">
        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <h1>${modoEdicion ? "¡Publicación actualizada!" : "¡Publicación creada!"}</h1>
      <p>Tu publicación ${modoEdicion ? "ya refleja los cambios" : "ya está visible"} para otros usuarios.</p>
      <div class="cp-nav" style="justify-content:center; border-top:none; padding-top:0; gap:12px;">
        <a class="cp-btn cp-btn-primary" href="/mis-publicaciones">Ir a mis publicaciones</a>
      </div>
    </div>`;
}

// ============================================================
// ENRUTADOR DE PASOS
// ============================================================
function renderPasoActual() {
  window.scrollTo({ top: 0, behavior: "smooth" });
  if (pasoActual === 0) return renderPaso0();
  const nombrePaso = PASOS[pasoActual - 1];
  if (nombrePaso === "basico") return renderBasico();
  if (nombrePaso === "caracteristicas") return renderCaracteristicas();
  if (nombrePaso === "reglas") return renderReglas();
  if (nombrePaso === "fotos") return renderFotos();
  if (nombrePaso === "resumen") return renderResumen();
}

// ============================================================
// INICIALIZACIÓN
// ============================================================
(async function inicializar() {
  if (modoEdicion) {
    try {
      const respuesta = await fetch(`${API_BASE}/mis-publicaciones/${idPublicacionEditar}`, {
        headers: cabecerasAutenticadas(false),
      });
      if (manejarNoAutorizado(respuesta)) return;
      if (!respuesta.ok) {
        window.location.href = "/mis-publicaciones";
        return;
      }
      const datos = await respuesta.json();
      llenarEstadoDesdePublicacion(datos.publicacion);
    } catch (err) {
      window.location.href = "/mis-publicaciones";
      return;
    }
  }
  renderPasoActual();
})();
