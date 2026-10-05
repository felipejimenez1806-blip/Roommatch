// ============================================================
// ROOMMATCH – lógica de la página SOLICITAR RESERVA
// Página privada: requiere sesión activa. Si no hay sesión,
// se redirige a /login (igual que el resto de acciones que
// requieren usuario real en publicacion.js).
//
// CAMBIO PRINCIPAL: ya no depende de `publicaciones` (data.js).
// Reutiliza GET /api/publicaciones/{id} (el mismo endpoint del
// detalle) para traer los datos de la publicación, y envía la
// solicitud a POST /api/publicaciones/{id}/reservas.
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

function manejarNoAutorizado(respuesta) {
  if (respuesta.status === 401) {
    sessionStorage.removeItem(STORAGE_TOKEN_KEY);
    sessionStorage.removeItem(STORAGE_USER_KEY);
    window.location.href = "/login";
    return true;
  }
  return false;
}

// Página privada: sin token no hay nada que hacer aquí.
if (!obtenerToken()) {
  window.location.href = "/login";
}

const usuarioActual = obtenerUsuarioGuardado();

// ---------- HELPERS ----------
function formatCOP(n) {
  return "$" + Math.round(n).toLocaleString("es-CO") + " COP";
}

function estrellas(rating) {
  const llenas = Math.round(rating / 2); // rating es sobre 10, estrellas sobre 5
  return "★".repeat(llenas) + "☆".repeat(5 - llenas);
}

function hoyISO() {
  const d = new Date();
  return d.toISOString().split("T")[0];
}

function formatFechaCorta(fechaISO) {
  if (!fechaISO) return null;
  return new Date(fechaISO + "T00:00:00").toLocaleDateString("es-CO", {
    day: "2-digit", month: "short", year: "numeric",
  });
}

function formatHora12(horaISO) {
  if (!horaISO) return null;
  const [h, m] = horaISO.split(":").map(Number);
  const periodo = h >= 12 ? "p. m." : "a. m.";
  const h12 = h % 12 === 0 ? 12 : h % 12;
  return `${h12}:${String(m).padStart(2, "0")} ${periodo}`;
}

const ETIQUETAS_DURACION = {
  "1-3": "1 a 3 meses",
  "3-6": "3 a 6 meses",
  "6-12": "6 a 12 meses",
  "12+": "Más de 12 meses",
};

// Bloques de 30 minutos agrupados por franja del día, para el panel
// de chips (reemplaza al <select> nativo de 48 renglones, que se veía
// como una lista interminable sin ningún estilo propio).
const GRUPOS_HORARIO = [
  { titulo: "Madrugada", inicio: 0, fin: 5 },
  { titulo: "Mañana", inicio: 6, fin: 11 },
  { titulo: "Tarde", inicio: 12, fin: 17 },
  { titulo: "Noche", inicio: 18, fin: 23 },
];

function generarGruposHorario() {
  return GRUPOS_HORARIO.map((grupo) => ({
    titulo: grupo.titulo,
    bloques: Array.from({ length: (grupo.fin - grupo.inicio + 1) * 2 }, (_, i) => {
      const totalMin = grupo.inicio * 60 + i * 30;
      const h = Math.floor(totalMin / 60);
      const m = totalMin % 60;
      return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}`;
    }),
  }));
}

// Mismo subconjunto de iconos usado en la pestaña "Descripción" de publicacion.js,
// para mantener coherencia visual entre ambas páginas.
const ICONOS_RESUMEN = [
  ["amueblado", "🛋️", "Amueblado"],
  ["incluye_internet", "📶", "Internet incluido"],
  ["parqueadero", "🅿️", "Parqueadero"],
  ["bano_privado", "🚿", "Baño privado"],
  ["porteria", "💂", "Portería 24h"],
  ["ascensor", "🛗", "Ascensor"],
];

// ---------- REGLAS DE LA CASA (dinámicas, según la publicación) ----------
function renderReglas(c) {
  const reglas = [
    ["permite_mascotas", "🐾", "Permite mascotas", "No se permiten mascotas"],
    ["permite_fumar", "🚬", "Permite fumar", "No se permite fumar"],
    ["permite_fiestas", "🎉", "Permite fiestas/reuniones", "No se permiten fiestas ni reuniones"],
    ["permite_visitas", "🙋", "Permite visitas", "No se permiten visitas"],
    ["permite_parejas", "💑", "Permite parejas", "No se permiten parejas"],
  ];

  return `
    <div class="res-rules-grid">
      ${reglas.map(([campo, icono, siTexto, noTexto]) => `
        <div class="res-rule-item${c[campo] ? "" : " is-restricted"}">
          <span class="res-rule-icon">${icono}</span> ${c[campo] ? siTexto : noTexto}
        </div>`).join("")}
    </div>
    <div class="res-schedule-row">
      <div class="res-schedule-item"><span>Horario de entrada</span>${c.horario_entrada || "Sin restricción"}</div>
      ${c.horario_silencio ? `<div class="res-schedule-item"><span>Horario de silencio</span>${c.horario_silencio}</div>` : ""}
    </div>`;
}

// ---------- CARGA INICIAL DESDE LA API ----------
async function cargarReserva() {
  const contenedor = document.getElementById("resPage");
  const id = contenedor.dataset.id;

  try {
    const resp = await fetch(`${API_BASE}/publicaciones/${id}`, { headers: { Accept: "application/json" } });
    if (!resp.ok) throw new Error("Publicación no encontrada");

    const { publicacion: item } = await resp.json();

    // Un oferente no puede reservar su propia publicación.
    const esPropiaPublicacion = usuarioActual && item.propietario
      && item.propietario.id === usuarioActual.id;

    if (esPropiaPublicacion) {
      contenedor.innerHTML = `
        <div class="res-not-found">
          <p>Esta es tu propia publicación, así que no puedes solicitar una reserva sobre ella.</p>
          <a href="/mis-publicaciones">← Ir a Mis publicaciones</a>
        </div>`;
      return;
    }

    renderReserva(item);
  } catch (err) {
    console.error(err);
    contenedor.innerHTML = `
      <div class="res-not-found">
        <p>No encontramos esta publicación. Puede que ya no esté disponible.</p>
        <a href="/habitaciones">← Volver a la búsqueda</a>
      </div>`;
  }
}

// ---------- RENDER PRINCIPAL ----------
function renderReserva(item) {
  const contenedor = document.getElementById("resPage");
  const c = item.caracteristicas || {};
  const iconosDisponibles = ICONOS_RESUMEN.filter(([campo]) => c[campo]);
  const deposito = item.precio;
  const imagenPrincipal = (item.imagenes && item.imagenes[0]) || item.img || "";

  contenedor.innerHTML = `
    <a class="res-back" href="/publicacion/${item.id}" title="Volver a la publicación">&larr;</a>

    <div class="res-heading">
      <div class="res-title">Solicitar reserva</div>
      <div class="res-subtitle">${item.titulo} · ${item.zona}, ${item.ciudad}</div>
    </div>

    <div class="res-body">
      <div class="res-main">

        <!-- PASO 1 -->
        <div class="res-step">
          <div class="res-step-title"><span class="res-step-number">1</span> Detalles de tu solicitud</div>

          ${iconosDisponibles.length ? `
            <div class="res-amenities-title">Este espacio incluye</div>
            <div class="res-amenities-grid">
              ${iconosDisponibles.map(([, icono, texto]) => `
                <div class="res-amenity-item"><span class="res-amenity-icon">${icono}</span> ${texto}</div>
              `).join("")}
            </div>
            <hr class="res-divider"/>
          ` : ""}

          <div class="res-field-row">
            <div class="res-field">
              <label for="fechaVisita">Fecha de visita al inmueble</label>
              <input type="date" id="fechaVisita" min="${hoyISO()}"/>
              <div class="res-field-error" id="err-fechaVisita">Selecciona una fecha para la visita.</div>
            </div>
            <div class="res-field">
              <label for="horaVisitaTrigger">Hora de visita</label>
              <div class="res-hora-picker" id="horaVisitaPicker">
                <button type="button" class="res-hora-trigger" id="horaVisitaTrigger">
                  <span id="horaVisitaTriggerLabel">Selecciona una hora</span>
                  <svg class="res-hora-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <input type="hidden" id="horaVisita" value=""/>
                <div class="res-hora-panel" id="horaVisitaPanel" hidden>
                  ${generarGruposHorario().map((grupo) => `
                    <div class="res-hora-group">
                      <p class="res-hora-group-title">${grupo.titulo}</p>
                      <div class="res-hora-grid">
                        ${grupo.bloques.map((h) => `<button type="button" class="res-hora-chip" data-hora="${h}">${formatHora12(h)}</button>`).join("")}
                      </div>
                    </div>`).join("")}
                </div>
              </div>
              <div class="res-field-error" id="err-horaVisita">Selecciona una hora para la visita.</div>
            </div>
          </div>

          <div class="res-field">
            <label for="duracion">Duración estimada</label>
            <select id="duracion">
              <option value="">Selecciona una opción</option>
              <option value="1-3">1 a 3 meses</option>
              <option value="3-6">3 a 6 meses</option>
              <option value="6-12">6 a 12 meses</option>
              <option value="12+">Más de 12 meses</option>
            </select>
            <div class="res-field-error" id="err-duracion">Selecciona una duración estimada.</div>
          </div>
        </div>

        <!-- PASO 2 -->
        <div class="res-step">
          <div class="res-step-title"><span class="res-step-number">2</span> Tus datos</div>
          <div class="res-field">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" placeholder="Ej. María López" value="${usuarioActual?.nombre || ""}"/>
            <div class="res-field-error" id="err-nombre">Ingresa tu nombre completo.</div>
          </div>
          <div class="res-field-row">
            <div class="res-field">
              <label for="correo">Correo electrónico</label>
              <input type="email" id="correo" placeholder="correo@ejemplo.com" value="${usuarioActual?.correo || usuarioActual?.email || ""}"/>
              <div class="res-field-error" id="err-correo">Ingresa un correo válido.</div>
            </div>
            <div class="res-field">
              <label for="telefono">Número de teléfono</label>
              <input type="tel" id="telefono" placeholder="+57 300 123 4567"/>
              <div class="res-field-error" id="err-telefono">Ingresa un número de teléfono válido.</div>
            </div>
          </div>
        </div>

        <!-- REGLAS DE LA CASA (sin numerar) -->
        <div class="res-step">
          <div class="res-rules-title">Reglas de la casa</div>
          ${renderReglas(c)}
        </div>

      </div>

      <div class="res-sidebar">
        <div class="res-summary-card">
          <img class="res-summary-img" src="${imagenPrincipal}" alt="${item.titulo}"/>
          <div class="res-summary-body">
            <div class="res-summary-title">${item.titulo}</div>
            <div class="res-summary-rating">${estrellas(item.rating)}</div>
            <div class="res-summary-location">${item.direccion}, ${item.zona}, ${item.ciudad}</div>

            <div class="res-summary-row is-empty" id="resumenFecha">
              <span>Fecha de visita</span><span>Sin definir</span>
            </div>
            <div class="res-summary-row is-empty" id="resumenHora">
              <span>Hora de visita</span><span>Sin definir</span>
            </div>
            <div class="res-summary-row is-empty" id="resumenDuracion">
              <span>Duración</span><span>Sin definir</span>
            </div>

            <div class="res-price-breakdown">
              <div class="res-price-row"><span>Precio mensual</span><span>${formatCOP(item.precio)}</span></div>
              <div class="res-price-row"><span>Depósito de garantía</span><span>${formatCOP(deposito)}</span></div>
              <div class="res-price-total"><span>Costo total estimado</span><span>${formatCOP(item.precio + deposito)}</span></div>
            </div>

            <button type="button" class="res-cta" id="enviarSolicitudBtn">Enviar solicitud de reserva</button>
            <div class="res-form-error" id="formError">Revisa los campos marcados antes de continuar.</div>
          </div>
        </div>
      </div>
    </div>`;

  activarActualizacionResumen();
  activarSelectorHora();
  activarDisponibilidadHorario(item);
  activarEnvio(item);
}

// ---------- SELECTOR DE HORA (panel de chips) ----------
// Abre/cierra el panel, y al elegir un chip guarda el valor en el
// input oculto #horaVisita y dispara "change" sobre él — así el resto
// del código (resumen en vivo, disponibilidad, envío) sigue leyendo
// #horaVisita exactamente igual que cuando era un <select>.
function activarSelectorHora() {
  const picker = document.getElementById("horaVisitaPicker");
  const trigger = document.getElementById("horaVisitaTrigger");
  const triggerLabel = document.getElementById("horaVisitaTriggerLabel");
  const panel = document.getElementById("horaVisitaPanel");
  const hiddenInput = document.getElementById("horaVisita");

  function cerrarPanel() {
    panel.hidden = true;
    trigger.classList.remove("is-open");
  }

  trigger.addEventListener("click", (e) => {
    e.stopPropagation();
    const abrir = panel.hidden;
    cerrarPanel();
    if (abrir) {
      panel.hidden = false;
      trigger.classList.add("is-open");
    }
  });

  document.addEventListener("click", (e) => {
    if (!picker.contains(e.target)) cerrarPanel();
  });

  panel.querySelectorAll(".res-hora-chip").forEach((chip) => {
    chip.addEventListener("click", () => {
      if (chip.disabled) return;
      hiddenInput.value = chip.dataset.hora;
      triggerLabel.textContent = chip.textContent;
      trigger.classList.add("has-value");
      trigger.classList.remove("res-invalid");
      panel.querySelectorAll(".res-hora-chip.is-selected").forEach((c) => c.classList.remove("is-selected"));
      chip.classList.add("is-selected");
      cerrarPanel();
      hiddenInput.dispatchEvent(new Event("change"));
    });
  });
}

// ---------- DISPONIBILIDAD DE HORARIO (según la fecha elegida) ----------
// Se consulta cada vez que cambia la fecha: las horas ya tomadas (por
// una reserva pendiente o aceptada de OTRO usuario, para ESTA misma
// publicación) quedan deshabilitadas entre los chips, sin necesidad de
// que el usuario intente enviarlas para enterarse.
function activarDisponibilidadHorario(item) {
  const fechaInput = document.getElementById("fechaVisita");
  const hiddenInput = document.getElementById("horaVisita");
  const trigger = document.getElementById("horaVisitaTrigger");
  const triggerLabel = document.getElementById("horaVisitaTriggerLabel");
  const chips = () => document.querySelectorAll("#horaVisitaPanel .res-hora-chip");

  async function actualizar() {
    const fecha = fechaInput.value;

    // Sin fecha aún no hay nada que consultar: todos los chips quedan
    // habilitados hasta que el usuario elija un día.
    chips().forEach((chip) => {
      chip.disabled = false;
      chip.classList.remove("is-unavailable");
    });
    if (!fecha) return;

    try {
      const resp = await fetch(`${API_BASE}/publicaciones/${item.id}/horarios-ocupados?fecha=${fecha}`, {
        headers: { Accept: "application/json" },
      });
      if (!resp.ok) return;

      const { horarios_ocupados: horariosOcupados } = await resp.json();
      const ocupados = new Set((horariosOcupados || []).map((h) => h.slice(0, 5)));

      chips().forEach((chip) => {
        if (!ocupados.has(chip.dataset.hora)) return;
        chip.disabled = true;
        chip.classList.add("is-unavailable");
      });

      // Si la hora que ya tenía elegida se ocupó mientras llenaba el
      // resto del formulario, se limpia para que no la mande sin darse
      // cuenta (el backend la habría rechazado de todas formas).
      if (ocupados.has(hiddenInput.value)) {
        hiddenInput.value = "";
        triggerLabel.textContent = "Selecciona una hora";
        trigger.classList.remove("has-value");
        chips().forEach((c) => c.classList.remove("is-selected"));
        hiddenInput.dispatchEvent(new Event("change"));
      }
    } catch (err) {
      console.error(err);
    }
  }

  fechaInput.addEventListener("change", actualizar);
}

// ---------- ACTUALIZACIÓN EN VIVO DEL RESUMEN ----------
function activarActualizacionResumen() {
  const fechaInput = document.getElementById("fechaVisita");
  const horaInput = document.getElementById("horaVisita");
  const duracionSelect = document.getElementById("duracion");

  fechaInput.addEventListener("change", () => {
    const el = document.querySelector("#resumenFecha span:last-child");
    const contenedor = document.getElementById("resumenFecha");
    if (fechaInput.value) {
      el.textContent = formatFechaCorta(fechaInput.value);
      contenedor.classList.remove("is-empty");
    } else {
      el.textContent = "Sin definir";
      contenedor.classList.add("is-empty");
    }
  });

  horaInput.addEventListener("change", () => {
    const el = document.querySelector("#resumenHora span:last-child");
    const contenedor = document.getElementById("resumenHora");
    if (horaInput.value) {
      el.textContent = formatHora12(horaInput.value);
      contenedor.classList.remove("is-empty");
    } else {
      el.textContent = "Sin definir";
      contenedor.classList.add("is-empty");
    }
  });

  duracionSelect.addEventListener("change", () => {
    const el = document.querySelector("#resumenDuracion span:last-child");
    const contenedor = document.getElementById("resumenDuracion");
    if (duracionSelect.value) {
      el.textContent = ETIQUETAS_DURACION[duracionSelect.value];
      contenedor.classList.remove("is-empty");
    } else {
      el.textContent = "Sin definir";
      contenedor.classList.add("is-empty");
    }
  });
}

// ---------- VALIDACIÓN Y ENVÍO ----------
function activarEnvio(item) {
  const btn = document.getElementById("enviarSolicitudBtn");

  btn.addEventListener("click", async () => {
    const campos = {
      fecha_visita: document.getElementById("fechaVisita").value.trim(),
      hora_visita: document.getElementById("horaVisita").value.trim(),
      duracion_estimada: document.getElementById("duracion").value.trim(),
      contacto_nombre: document.getElementById("nombre").value.trim(),
      contacto_correo: document.getElementById("correo").value.trim(),
      contacto_telefono: document.getElementById("telefono").value.trim(),
    };

    // ---- Validación en cliente (misma UX que el original) ----
    let valido = true;
    const marcarError = (idInput, condicionInvalida) => {
      const input = document.getElementById(idInput);
      const error = document.getElementById(`err-${idInput}`);
      // #horaVisita es un input oculto (el valor real lo guarda el
      // panel de chips); el que se ve y se marca en rojo es su botón
      // disparador, no el input en sí.
      const elementoVisual = input.type === "hidden" ? document.getElementById(`${idInput}Trigger`) : input;
      if (condicionInvalida) {
        elementoVisual.classList.add("res-invalid");
        error.classList.add("is-visible");
        valido = false;
      } else {
        elementoVisual.classList.remove("res-invalid");
        error.classList.remove("is-visible");
      }
    };

    marcarError("fechaVisita", !campos.fecha_visita);
    marcarError("horaVisita", !campos.hora_visita);
    marcarError("duracion", !campos.duracion_estimada);
    marcarError("nombre", campos.contacto_nombre.length < 3);
    marcarError("correo", !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(campos.contacto_correo));
    marcarError("telefono", campos.contacto_telefono.replace(/\D/g, "").length < 7);

    const formError = document.getElementById("formError");
    if (!valido) {
      formError.textContent = "Revisa los campos marcados antes de continuar.";
      formError.classList.add("is-visible");
      return;
    }
    formError.classList.remove("is-visible");

    // ---- Envío a la API (la validación real y autoritativa vive en ReservaRequest) ----
    btn.disabled = true;
    btn.textContent = "Enviando...";

    try {
      const resp = await fetch(`${API_BASE}/publicaciones/${item.id}/reservas`, {
        method: "POST",
        headers: authHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify(campos),
      });

      if (manejarNoAutorizado(resp)) return;

      if (resp.status === 422) {
        const { errors } = await resp.json();
        formError.textContent = Object.values(errors).flat().join(" ");
        formError.classList.add("is-visible");
        return;
      }
      if (resp.status === 403) {
        formError.textContent = "No puedes reservar tu propia publicación.";
        formError.classList.add("is-visible");
        return;
      }
      if (resp.status === 409) {
        // Perfil sin tipo_usuario/telefono: el backend no deja reservar
        // nada nuevo hasta que se complete (ver perfil.completo).
        const datos = await resp.json().catch(() => ({}));
        formError.textContent = datos.mensaje || "Completa tu perfil antes de continuar.";
        formError.classList.add("is-visible");
        setTimeout(() => { window.location.href = "/onboarding"; }, 1500);
        return;
      }
      if (!resp.ok) throw new Error("Error al enviar la solicitud");

      const { reserva } = await resp.json();
      window.location.href = `/confirmacion?reservaId=${reserva.id}`;
    } catch (err) {
      console.error(err);
      formError.textContent = "Ocurrió un error al enviar tu solicitud. Intenta de nuevo.";
      formError.classList.add("is-visible");
    } finally {
      btn.disabled = false;
      btn.textContent = "Enviar solicitud de reserva";
    }
  });
}

cargarReserva();
