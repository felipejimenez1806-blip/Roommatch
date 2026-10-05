// ============================================================
// ROOMMATCH – lógica de la página CREAR PERFIL DE ROOMIE
// ------------------------------------------------------------
// Mismo patrón que crear-publicacion.js:
//   - Sesión: token + usuario de sessionStorage
//   - El perfil de roomie es 1 a 1 con el usuario, así que "modo
//     edición" ya NO depende de un ?id= en la URL: simplemente le
//     preguntamos a GET /api/mi-perfil-roomie si ya existe uno.
//       · Si existe  -> precargamos el wizard en modo edición.
//       · Si no existe -> arrancamos vacío (modo creación).
//   - Publicar/Guardar: POST o PUT contra /api/mi-perfil-roomie
//   - Ya NO depende de roomies-data.js (guardarPersonaExtra, etc.)
//
// CAMBIO: token/usuario ahora se leen y limpian en sessionStorage
// (antes localStorage), para que la sesión se cierre sola al cerrar
// la pestaña/ventana en vez de persistir indefinidamente.
// ============================================================

const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

// ---------- LOCALIDADES DE BOGOTÁ ----------
// Mismo listado que crear-publicacion.js, para que "zona" sea consistente
// entre publicaciones y perfiles de roomie (y se pueda cruzar/filtrar por
// localidad exacta en vez de texto libre).
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

// ============================================================
// ESTADO DEL WIZARD
// ============================================================
const FOTO_MAX_LADO = 700;
const FOTO_CALIDAD = 0.85;
const FOTO_MAX_PESO_ORIGINAL = 8 * 1024 * 1024;

const PASOS = ["basico", "estilo", "convivencia", "foto", "resumen"];
let PASO_LABELS = { basico: "Básico", estilo: "Estilo de vida", convivencia: "Convivencia", foto: "Foto", resumen: "Publicar" };

let pasoActual = 1;
let modoEdicion = false; // se resuelve en inicializar(), según exista ya un perfil propio

function estadoVacio() {
  return {
    edad: "", genero: "", ocupacion: "", zona: "", ciudad: "Bogotá",
    presupuesto: "", telefono_contacto: usuarioActual.telefono || "",
    descripcion: "", tiempo_busqueda: "", fecha_mudanza: "",

    fumador: false, tiene_mascota: false, ordenado: false, sociable: false,
    madrugador: false, trasnochador: false, fiestero: false,
    trabaja_desde_casa: false, viaja_frecuentemente: false,
    horario: "",

    ambiente_preferido: "",
    acepta_mascotas: false, acepta_fumadores: false, acepta_visitas: false, acepta_parejas: false,
    quiere_amueblado: false, quiere_bano_privado: false, quiere_parqueadero: false,
    cerca_universidad: false, cerca_transporte_publico: false,

    tiene_vehiculo: false, comparte_gastos: false, referencias_verificadas: false,

    foto: usuarioActual.fotoPerfil || "",
  };
}

let estado = estadoVacio();

function llenarEstadoDesdePerfil(perfil) {
  const c = perfil.caracteristicas || {};
  estado = {
    ...estado,
    edad: perfil.edad ?? estado.edad,
    genero: perfil.genero || estado.genero,
    ocupacion: perfil.ocupacion || estado.ocupacion,
    zona: perfil.zona || estado.zona,
    ciudad: perfil.ciudad || estado.ciudad,
    presupuesto: perfil.presupuesto ?? estado.presupuesto,
    telefono_contacto: perfil.telefono || estado.telefono_contacto,
    descripcion: perfil.descripcion || estado.descripcion,
    tiempo_busqueda: c.tiempo_busqueda || estado.tiempo_busqueda,
    fecha_mudanza: c.fecha_mudanza || estado.fecha_mudanza,
    fumador: !!c.fumador, tiene_mascota: !!c.tiene_mascota, ordenado: !!c.ordenado, sociable: !!c.sociable,
    madrugador: !!c.madrugador, trasnochador: !!c.trasnochador, fiestero: !!c.fiestero,
    trabaja_desde_casa: !!c.trabaja_desde_casa, viaja_frecuentemente: !!c.viaja_frecuentemente,
    horario: c.horario || "",
    ambiente_preferido: c.ambiente_preferido || "",
    acepta_mascotas: !!c.acepta_mascotas, acepta_fumadores: !!c.acepta_fumadores,
    acepta_visitas: !!c.acepta_visitas, acepta_parejas: !!c.acepta_parejas,
    quiere_amueblado: !!c.quiere_amueblado, quiere_bano_privado: !!c.quiere_bano_privado,
    quiere_parqueadero: !!c.quiere_parqueadero, cerca_universidad: !!c.cerca_universidad,
    cerca_transporte_publico: !!c.cerca_transporte_publico,
    tiene_vehiculo: !!c.tiene_vehiculo, comparte_gastos: !!c.comparte_gastos,
    referencias_verificadas: !!c.referencias_verificadas,
    foto: perfil.img || estado.foto,
  };
}

const cprPage = document.getElementById("cprPage");

// ============================================================
// HELPERS DE CAMPOS (mismo espíritu que crear-publicacion.js)
// ============================================================
function campoTexto({ key, label, placeholder = "", tipo = "text", hint = "" }) {
  return `
    <div class="cp-field" data-field="${key}">
      <label for="cpr_${key}">${label}</label>
      <input id="cpr_${key}" type="${tipo}" data-bind="${key}" placeholder="${placeholder}" value="${(estado[key] ?? "").toString().replace(/"/g, "&quot;")}"/>
      ${hint ? `<p class="cp-field-hint">${hint}</p>` : ""}
      <p class="cp-field-error" data-error></p>
    </div>`;
}
function campoTextarea({ key, label, placeholder = "" }) {
  return `
    <div class="cp-field" data-field="${key}">
      <label for="cpr_${key}">${label}</label>
      <textarea id="cpr_${key}" data-bind="${key}" placeholder="${placeholder}">${estado[key] ?? ""}</textarea>
      <p class="cp-field-error" data-error></p>
    </div>`;
}
// Dropdown personalizado (mismo componente que crear-publicacion.js, para
// que el select se vea y se comporte igual en ambos wizards; el CSS ya
// viene heredado vía @import en crear-perfil-roomie.css).
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
      <label id="cpr_${key}_label">${label}</label>
      <div class="cp-select" data-select="${key}">
        <button type="button" class="cp-select-trigger" id="cpr_${key}" data-select-trigger aria-haspopup="listbox" aria-expanded="false" aria-labelledby="cpr_${key}_label">
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
  const row = cprPage.querySelector(`.cp-field[data-field="${key}"]`);
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
  const row = cprPage.querySelector(`.cp-field[data-field="${key}"]`);
  if (!row) return;
  row.classList.add("has-error");
  const err = row.querySelector("[data-error]");
  err.textContent = mensaje;
  err.classList.add("is-visible");
}
function limpiarTelefono(str) {
  return (str || "").replace(/\D/g, "");
}
function formatCOP(n) {
  return "$" + Math.round(Number(n) || 0).toLocaleString("es-CO") + " COP";
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
  cprPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">${modoEdicion ? "Edita tu información" : "Cuéntanos sobre ti"}</h1>
    <p class="cp-step-subtitle">Esta información aparecerá en tu perfil público de roomie</p>

    <div class="cp-field-row">
      ${campoTexto({ key: "edad", label: "Edad", tipo: "number" })}
      ${campoSelect({ key: "genero", label: "Género", opciones: [["Mujer", "Mujer"], ["Hombre", "Hombre"], ["Otro", "Otro"]] })}
    </div>
    ${campoSelect({ key: "ocupacion", label: "Ocupación", opciones: [["Estudiante", "Estudiante"], ["Profesional", "Profesional"], ["Freelance", "Freelance"], ["Otro", "Otro"]] })}

    <div class="cp-field-row">
      ${campoSelect({ key: "zona", label: "Zona donde buscas", opciones: LOCALIDADES_BOGOTA.map((loc) => [loc, loc]), placeholder: "Selecciona una localidad" })}
      ${campoTexto({ key: "ciudad", label: "Ciudad", placeholder: "Ej. Bogotá" })}
    </div>
    ${campoTexto({ key: "presupuesto", label: "Presupuesto mensual (COP)", tipo: "number" })}

    <div class="cp-field-row">
      ${campoSelect({ key: "tiempo_busqueda", label: "Tiempo de estadía buscado", opciones: [["corto", "Corto plazo"], ["largo", "Largo plazo"]] })}
      ${campoSelect({ key: "fecha_mudanza", label: "Fecha de mudanza", opciones: [["inmediata", "Inmediata"], ["1mes", "En un mes"], ["flexible", "Flexible"]] })}
    </div>

    ${campoTexto({ key: "telefono_contacto", label: "Teléfono de contacto (WhatsApp)", tipo: "tel", placeholder: "Ej. 573001112233", hint: "Con indicativo de país, solo números. Es el número que verán los interesados al contactarte." })}

    ${campoTextarea({ key: "descripcion", label: "Descripción", placeholder: "Cuéntale a otros roomies quién eres, tu rutina y qué buscas en un lugar para vivir" })}

    <div class="cp-nav">
      <a class="cp-btn cp-btn-ghost" href="${modoEdicion ? "/mis-publicaciones" : "/perfil"}">Cancelar</a>
      <button type="button" class="cp-btn cp-btn-primary" id="cprNext">Siguiente</button>
    </div>`;

  enlazarCampos(cprPage);
  document.getElementById("cprNext").addEventListener("click", () => {
    if (!validarBasico()) return;
    pasoActual = 2;
    renderPasoActual();
  });
}

function validarBasico() {
  let ok = true;
  const edad = Number(estado.edad);
  if (!estado.edad || edad < 18 || edad > 99) { marcarError("edad", "Ingresa una edad válida (18-99)."); ok = false; }
  if (!estado.genero) { marcarError("genero", "Selecciona tu género."); ok = false; }
  if (!estado.ocupacion) { marcarError("ocupacion", "Selecciona tu ocupación."); ok = false; }
  if (!estado.zona || !LOCALIDADES_BOGOTA.includes(estado.zona)) { marcarError("zona", "Selecciona una localidad de la lista."); ok = false; }
  if (!estado.presupuesto || Number(estado.presupuesto) <= 0) { marcarError("presupuesto", "Ingresa un presupuesto válido."); ok = false; }
  if (!estado.tiempo_busqueda) { marcarError("tiempo_busqueda", "Selecciona el tiempo de estadía que buscas."); ok = false; }
  if (!estado.fecha_mudanza) { marcarError("fecha_mudanza", "Selecciona tu fecha de mudanza."); ok = false; }
  const telefonoLimpio = limpiarTelefono(estado.telefono_contacto);
  if (telefonoLimpio.length < 10) { marcarError("telefono_contacto", "Ingresa un número de WhatsApp válido, con indicativo de país (ej. 573001112233)."); ok = false; }
  if (!estado.descripcion || estado.descripcion.trim().length < 15) { marcarError("descripcion", "Cuéntanos un poco más de ti (mínimo 15 caracteres)."); ok = false; }
  return ok;
}

// ============================================================
// PASO 2 – ESTILO DE VIDA
// ============================================================
function renderEstilo() {
  cprPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Tu estilo de vida</h1>
    <p class="cp-step-subtitle">Marca todo lo que te describa; esto ayuda a que aparezcas en más búsquedas</p>

    <div class="cp-group">
      <p class="cp-group-title">Hábitos</p>
      <div class="cp-toggle-grid">
        ${toggleRow("fumador", "Fumador/a")}
        ${toggleRow("tiene_mascota", "Tengo mascota propia")}
        ${toggleRow("ordenado", "Ordenado/a")}
        ${toggleRow("sociable", "Sociable")}
        ${toggleRow("madrugador", "Madrugador/a")}
        ${toggleRow("trasnochador", "Trasnochador/a")}
        ${toggleRow("fiestero", "Me gustan las reuniones/fiestas")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Rutina</p>
      ${campoSelect({ key: "horario", label: "Horario habitual", opciones: [["diurno", "Diurno"], ["nocturno", "Nocturno"], ["mixto", "Mixto"]] })}
      <div class="cp-toggle-grid">
        ${toggleRow("trabaja_desde_casa", "Trabajo/estudio desde casa")}
        ${toggleRow("viaja_frecuentemente", "Viajo frecuentemente")}
      </div>
    </div>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cprBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cprNext">Siguiente</button>
    </div>`;

  enlazarCampos(cprPage);
  document.getElementById("cprBack").addEventListener("click", () => { pasoActual = 1; renderPasoActual(); });
  document.getElementById("cprNext").addEventListener("click", () => { pasoActual = 3; renderPasoActual(); });
}

// ============================================================
// PASO 3 – CONVIVENCIA Y PREFERENCIAS
// ============================================================
function renderConvivencia() {
  cprPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Convivencia y preferencias</h1>
    <p class="cp-step-subtitle">Cuéntanos qué esperas del lugar y de tus futuros roomies</p>

    <div class="cp-group">
      <p class="cp-group-title">Ambiente</p>
      ${campoSelect({ key: "ambiente_preferido", label: "Ambiente que buscas", opciones: [["estudiantes", "Estudiantes"], ["profesionales", "Profesionales"], ["otro", "Otro"]] })}
      <div class="cp-toggle-grid">
        ${toggleRow("acepta_mascotas", "Acepto mascotas en casa")}
        ${toggleRow("acepta_fumadores", "Acepto convivir con fumadores")}
        ${toggleRow("acepta_visitas", "Acepto visitas frecuentes")}
        ${toggleRow("acepta_parejas", "Acepto convivir con parejas")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Preferencias del hogar</p>
      <div class="cp-toggle-grid">
        ${toggleRow("quiere_amueblado", "Busco lugar amueblado")}
        ${toggleRow("quiere_bano_privado", "Busco baño privado")}
        ${toggleRow("quiere_parqueadero", "Busco parqueadero")}
        ${toggleRow("cerca_universidad", "Cerca a universidad")}
        ${toggleRow("cerca_transporte_publico", "Cerca a transporte público")}
      </div>
    </div>

    <div class="cp-group">
      <p class="cp-group-title">Otros</p>
      <div class="cp-toggle-grid">
        ${toggleRow("tiene_vehiculo", "Tengo vehículo propio")}
        ${toggleRow("comparte_gastos", "Dispuesto/a a compartir gastos comunes")}
        ${toggleRow("referencias_verificadas", "Puedo dar referencias verificadas")}
      </div>
    </div>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cprBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cprNext">Siguiente</button>
    </div>`;

  enlazarCampos(cprPage);
  document.getElementById("cprBack").addEventListener("click", () => { pasoActual = 2; renderPasoActual(); });
  document.getElementById("cprNext").addEventListener("click", () => { pasoActual = 4; renderPasoActual(); });
}

// ============================================================
// PASO 4 – FOTO DE PORTADA (comprimida a data URL)
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

function renderFoto() {
  cprPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Tu foto de perfil</h1>
    <p class="cp-step-subtitle">Los perfiles con foto generan mucha más confianza entre roomies</p>

    <div class="cpr-photo-wrap">
      <div class="cpr-photo-circle" id="cprPhotoCircle"></div>
      <input type="file" id="cprFotoInput" accept="image/png, image/jpeg, image/webp" hidden/>
      <p class="cpr-photo-hint">Usa una foto reciente donde se vea claramente tu rostro. Formatos JPG, PNG o WEBP, máximo 8MB.</p>
      <p class="cp-field-error" id="cprFotoError"></p>
    </div>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cprBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cprNext">Siguiente</button>
    </div>`;

  pintarFotoCircle();

  document.getElementById("cprBack").addEventListener("click", () => { pasoActual = 3; renderPasoActual(); });
  document.getElementById("cprNext").addEventListener("click", () => {
    if (!estado.foto) {
      document.getElementById("cprFotoError").textContent = "Sube una foto de perfil para continuar.";
      document.getElementById("cprFotoError").classList.add("is-visible");
      return;
    }
    pasoActual = 5;
    renderPasoActual();
  });
}

function pintarFotoCircle() {
  const circle = document.getElementById("cprPhotoCircle");

  if (estado.foto) {
    circle.classList.add("has-photo");
    circle.innerHTML = `
      <img src="${estado.foto}" alt="Foto de perfil"/>
      <button type="button" class="cpr-photo-remove" id="cprQuitarFoto" title="Quitar foto">✕</button>`;
    circle.onclick = null;
    document.getElementById("cprQuitarFoto").addEventListener("click", (e) => {
      e.stopPropagation();
      estado.foto = "";
      pintarFotoCircle();
    });
  } else {
    circle.classList.remove("has-photo");
    circle.innerHTML = `
      <span class="cpr-photo-placeholder">
        <svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
        Agregar foto
      </span>`;
    circle.onclick = () => document.getElementById("cprFotoInput").click();
  }

  document.getElementById("cprFotoInput").addEventListener("change", async (e) => {
    const archivo = e.target.files && e.target.files[0];
    e.target.value = "";
    if (!archivo) return;
    const errorEl = document.getElementById("cprFotoError");
    errorEl.textContent = "";
    errorEl.classList.remove("is-visible");

    if (!archivo.type.startsWith("image/")) {
      errorEl.textContent = "Solo se aceptan archivos de imagen (JPG, PNG o WEBP).";
      errorEl.classList.add("is-visible");
      return;
    }
    if (archivo.size > FOTO_MAX_PESO_ORIGINAL) {
      errorEl.textContent = "La imagen pesa demasiado. Usa una foto de menos de 8MB.";
      errorEl.classList.add("is-visible");
      return;
    }
    try {
      estado.foto = await comprimirImagen(archivo);
      pintarFotoCircle();
    } catch (err) {
      errorEl.textContent = "No se pudo procesar la imagen. Intenta con otra.";
      errorEl.classList.add("is-visible");
    }
  }, { once: true });
}

// ============================================================
// PASO 5 – RESUMEN Y PUBLICAR
// ============================================================
function renderResumen() {
  cprPage.innerHTML = `
    ${renderProgreso()}
    <h1 class="cp-step-title">Revisa tu perfil</h1>
    <p class="cp-step-subtitle">${modoEdicion ? "Revisa los cambios antes de guardarlos" : "Así te verán otros roomies antes de publicarlo"}</p>

    <div class="cpr-summary-card">
      <div class="cpr-summary-avatar-wrap">
        <img src="${estado.foto}" alt="Foto de perfil"/>
        <span class="cpr-summary-badge">${modoEdicion ? "Editado" : "Nuevo"}</span>
      </div>
      <div class="cpr-summary-body">
        <p class="cpr-summary-title">${usuarioActual.nombre || "Tu perfil"} <span style="color:#888;font-weight:600;">· ${estado.edad} años</span></p>
        <p class="cpr-summary-sub">${estado.ocupacion} · Busca en ${estado.zona}, ${estado.ciudad}</p>
        <p class="cpr-summary-price">${formatCOP(estado.presupuesto)} <span style="color:#888;font-weight:600;font-size:12px;">presupuesto/mes</span></p>
      </div>
    </div>

    <p class="cp-field-error is-visible" id="cprErrorGeneral" style="text-align:center;"></p>

    <div class="cp-nav">
      <button type="button" class="cp-btn cp-btn-ghost" id="cprBack">Atrás</button>
      <button type="button" class="cp-btn cp-btn-primary" id="cprPublicar">${modoEdicion ? "Guardar cambios" : "Publicar perfil"}</button>
    </div>`;

  document.getElementById("cprBack").addEventListener("click", () => { pasoActual = 4; renderPasoActual(); });
  document.getElementById("cprPublicar").addEventListener("click", publicar);
}

async function publicar() {
  const btn = document.getElementById("cprPublicar");
  const errorGeneral = document.getElementById("cprErrorGeneral");
  btn.disabled = true;
  btn.textContent = modoEdicion ? "Guardando..." : "Publicando...";
  errorGeneral.textContent = "";

  const payload = {
    edad: Number(estado.edad),
    genero: estado.genero,
    ocupacion: estado.ocupacion,
    zona: estado.zona.trim(),
    ciudad: estado.ciudad.trim() || "Bogotá",
    presupuesto: Number(estado.presupuesto),
    telefono_contacto: limpiarTelefono(estado.telefono_contacto),
    descripcion: estado.descripcion.trim(),
    foto: estado.foto,
    tiempo_busqueda: estado.tiempo_busqueda,
    fecha_mudanza: estado.fecha_mudanza,
    fumador: estado.fumador, tiene_mascota: estado.tiene_mascota, ordenado: estado.ordenado,
    sociable: estado.sociable, madrugador: estado.madrugador, trasnochador: estado.trasnochador, fiestero: estado.fiestero,
    ambiente_preferido: estado.ambiente_preferido || null,
    acepta_mascotas: estado.acepta_mascotas, acepta_fumadores: estado.acepta_fumadores,
    acepta_visitas: estado.acepta_visitas, acepta_parejas: estado.acepta_parejas,
    horario: estado.horario || null,
    trabaja_desde_casa: estado.trabaja_desde_casa, viaja_frecuentemente: estado.viaja_frecuentemente,
    quiere_amueblado: estado.quiere_amueblado, quiere_bano_privado: estado.quiere_bano_privado,
    quiere_parqueadero: estado.quiere_parqueadero, cerca_universidad: estado.cerca_universidad,
    cerca_transporte_publico: estado.cerca_transporte_publico,
    tiene_vehiculo: estado.tiene_vehiculo, comparte_gastos: estado.comparte_gastos,
    referencias_verificadas: estado.referencias_verificadas,
  };

  try {
    const respuesta = await fetch(`${API_BASE}/mi-perfil-roomie`, {
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
      btn.textContent = modoEdicion ? "Guardar cambios" : "Publicar perfil";
      errorGeneral.textContent = datos.mensaje || "No se pudo guardar tu perfil. Revisa los datos e intenta de nuevo.";
      return;
    }

    renderExito();
  } catch (err) {
    btn.disabled = false;
    btn.textContent = modoEdicion ? "Guardar cambios" : "Publicar perfil";
    errorGeneral.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
  }
}

function renderExito() {
  cprPage.innerHTML = `
    <div class="cp-success">
      <div class="cp-success-icon">
        <svg viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>
      </div>
      <h1>${modoEdicion ? "¡Tu perfil fue actualizado!" : "¡Tu perfil de roomie fue publicado!"}</h1>
      <p>${modoEdicion ? "Los cambios ya son visibles" : "Ya apareces"} en Roomies para que te encuentren en ${estado.zona}, ${estado.ciudad}.</p>
      <div class="cp-nav" style="justify-content:center; border-top:none; padding-top:0; gap:12px;">
        <a class="cp-btn cp-btn-primary" href="/perfil">Ir a mi perfil</a>
      </div>
    </div>`;
  // TODO: cuando exista la ruta de detalle público (perfil-roomie), agregar
  // aquí un segundo botón "Ver mi perfil de roomie" -> /roomie/{id}, igual
  // que el botón "Ver publicación" en crear-publicacion.js.
}

// ============================================================
// ENRUTADOR DE PASOS
// ============================================================
function renderPasoActual() {
  window.scrollTo({ top: 0, behavior: "smooth" });
  const nombrePaso = PASOS[pasoActual - 1];
  if (nombrePaso === "basico") return renderBasico();
  if (nombrePaso === "estilo") return renderEstilo();
  if (nombrePaso === "convivencia") return renderConvivencia();
  if (nombrePaso === "foto") return renderFoto();
  if (nombrePaso === "resumen") return renderResumen();
}

// ============================================================
// INICIALIZACIÓN
// ============================================================
(async function inicializar() {
  try {
    const respuesta = await fetch(`${API_BASE}/mi-perfil-roomie`, {
      headers: cabecerasAutenticadas(false),
    });
    if (manejarNoAutorizado(respuesta)) return;
    if (!respuesta.ok) {
      window.location.href = "/perfil";
      return;
    }
    const datos = await respuesta.json();
    if (datos.perfil) {
      modoEdicion = true;
      PASO_LABELS = { ...PASO_LABELS, resumen: "Guardar" };
      llenarEstadoDesdePerfil(datos.perfil);
    }
  } catch (err) {
    window.location.href = "/perfil";
    return;
  }
  renderPasoActual();
})();
