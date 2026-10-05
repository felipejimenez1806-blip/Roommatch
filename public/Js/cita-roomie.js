// ============================================================
// ROOMMATCH – lógica de la página AGENDAR CITA CON ROOMIE
// Página privada: requiere sesión activa. Si no hay sesión,
// se redirige a /login (mismo patrón que reserva.js).
//
// El objetivo de esta cita NO es alquilar nada todavía: es
// coordinar un encuentro en persona con el/la roomie para
// conocerse y visitar juntos el sitio que compartirían.
//
// CAMBIO PRINCIPAL: ya no depende de `personas` (roomies-data.js).
// Reutiliza GET /api/roomies/{id} (el mismo endpoint del perfil) y
// envía la solicitud a POST /api/roomies/{id}/citas.
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

function palabraCalificacion(rating) {
  if (rating >= 9) return "Excelente";
  if (rating >= 8) return "Muy bueno";
  if (rating >= 7) return "Bueno";
  return "Regular";
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

const ETIQUETAS_TIPO_ENCUENTRO = {
  sitio: "En el sitio que compartirían",
  publico: "En un lugar público cercano",
  otro: "Otro punto de encuentro",
};

// Bloques de 30 minutos agrupados por franja del día, para el panel
// de chips (mismo mecanismo que en reserva.js).
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

// ---------- CARGA INICIAL DESDE LA API ----------
async function cargarCita() {
  const contenedor = document.getElementById("crPage");
  const id = contenedor.dataset.id;

  try {
    const resp = await fetch(`${API_BASE}/roomies/${id}`, { headers: { Accept: "application/json" } });
    if (!resp.ok) throw new Error("Perfil no encontrado");

    const { persona: item } = await resp.json();

    // Un roomie no puede agendarse cita consigo mismo.
    const esPropioPerfil = usuarioActual && item.propietario
      && item.propietario.id === usuarioActual.id;

    if (esPropioPerfil) {
      contenedor.innerHTML = `
        <div class="cr-not-found">
          <p>Este es tu propio perfil de roomie, así que no puedes agendarte una cita contigo mismo/a.</p>
          <a href="/mis-publicaciones">← Ir a Mis publicaciones</a>
        </div>`;
      return;
    }

    renderCita(item);
  } catch (err) {
    console.error(err);
    contenedor.innerHTML = `
      <div class="cr-not-found">
        <p>No encontramos este perfil. Puede que ya no esté disponible.</p>
        <a href="/roomies">← Volver a la búsqueda</a>
      </div>`;
  }
}

// ---------- RENDER PRINCIPAL ----------
function renderCita(item) {
  const contenedor = document.getElementById("crPage");
  const primerNombre = item.nombre.split(" ")[0];

  contenedor.innerHTML = `
    <a class="cr-back" href="/roomie/${item.id}" title="Volver al perfil">&larr;</a>

    <div class="cr-heading">
      <div class="cr-title">Agendar cita</div>
      <div class="cr-subtitle">Con ${item.nombre} · ${item.zona}, ${item.ciudad}</div>
    </div>

    <div class="cr-body">
      <div class="cr-main">

        <!-- PASO 1 -->
        <div class="cr-step">
          <div class="cr-step-title"><span class="cr-step-number">1</span> Detalles del encuentro</div>

          <div class="cr-safety-note">
            <span class="cr-safety-icon">🛡️</span>
            <span>Recomendamos que el primer encuentro sea en un lugar público y de fácil acceso para ambos, antes de visitar juntos el sitio que planean compartir.</span>
          </div>

          <div class="cr-field-row">
            <div class="cr-field">
              <label for="fechaCita">Fecha propuesta</label>
              <input type="date" id="fechaCita" min="${hoyISO()}"/>
              <div class="cr-field-error" id="err-fechaCita">Selecciona una fecha para la cita.</div>
            </div>
            <div class="cr-field">
              <label for="horaCitaTrigger">Hora propuesta</label>
              <div class="cr-hora-picker" id="horaCitaPicker">
                <button type="button" class="cr-hora-trigger" id="horaCitaTrigger">
                  <span id="horaCitaTriggerLabel">Selecciona una hora</span>
                  <svg class="cr-hora-chevron" viewBox="0 0 24 24"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <input type="hidden" id="horaCita" value=""/>
                <div class="cr-hora-panel" id="horaCitaPanel" hidden>
                  ${generarGruposHorario().map((grupo) => `
                    <div class="cr-hora-group">
                      <p class="cr-hora-group-title">${grupo.titulo}</p>
                      <div class="cr-hora-grid">
                        ${grupo.bloques.map((h) => `<button type="button" class="cr-hora-chip" data-hora="${h}">${formatHora12(h)}</button>`).join("")}
                      </div>
                    </div>`).join("")}
                </div>
              </div>
              <div class="cr-field-error" id="err-horaCita">Selecciona una hora.</div>
            </div>
          </div>

          <div class="cr-field">
            <label for="tipoEncuentro">¿Dónde se encontrarán?</label>
            <select id="tipoEncuentro">
              <option value="">Selecciona una opción</option>
              <option value="sitio">En el sitio que compartirían</option>
              <option value="publico">En un lugar público cercano</option>
              <option value="otro">Otro punto de encuentro</option>
            </select>
            <div class="cr-field-error" id="err-tipoEncuentro">Selecciona dónde se encontrarán.</div>
          </div>

          <div class="cr-field">
            <label for="lugarEncuentro">Dirección o punto de referencia</label>
            <input type="text" id="lugarEncuentro" placeholder="Ej. Portal 80, entrada principal"/>
            <div class="cr-field-error" id="err-lugarEncuentro">Indica un lugar o punto de referencia.</div>
          </div>

          <div class="cr-field">
            <label for="mensaje">Mensaje para ${primerNombre} (opcional)</label>
            <textarea id="mensaje" placeholder="Cuéntale brevemente por qué te gustaría conocerlo/a y visitar el sitio juntos..."></textarea>
          </div>
        </div>

        <!-- PASO 2 -->
        <div class="cr-step">
          <div class="cr-step-title"><span class="cr-step-number">2</span> Tus datos de contacto</div>
          <div class="cr-field">
            <label for="nombre">Nombre completo</label>
            <input type="text" id="nombre" placeholder="Ej. María López" value="${usuarioActual?.nombre || ""}"/>
            <div class="cr-field-error" id="err-nombre">Ingresa tu nombre completo.</div>
          </div>
          <div class="cr-field-row">
            <div class="cr-field">
              <label for="correo">Correo electrónico</label>
              <input type="email" id="correo" placeholder="correo@ejemplo.com" value="${usuarioActual?.correo || usuarioActual?.email || ""}"/>
              <div class="cr-field-error" id="err-correo">Ingresa un correo válido.</div>
            </div>
            <div class="cr-field">
              <label for="telefono">Número de teléfono</label>
              <input type="tel" id="telefono" placeholder="+57 300 123 4567"/>
              <div class="cr-field-error" id="err-telefono">Ingresa un número de teléfono válido.</div>
            </div>
          </div>
        </div>

      </div>

      <div class="cr-sidebar">
        <div class="cr-summary-card">
          <div class="cr-summary-img-wrap">
            <img class="cr-summary-img" src="${item.img || ""}" alt="${item.nombre}"/>
          </div>
          <div class="cr-summary-body">
            <div class="cr-summary-title">${item.nombre}</div>
            <div class="cr-summary-rating">${palabraCalificacion(item.rating)} · ${item.rating}/10</div>
            <div class="cr-summary-location">Busca en ${item.zona}, ${item.ciudad}</div>

            <div class="cr-summary-row"><span>Presupuesto</span><span>${formatCOP(item.presupuesto)}</span></div>

            <div class="cr-summary-row is-empty" id="resumenFecha">
              <span>Fecha</span><span>Sin definir</span>
            </div>
            <div class="cr-summary-row is-empty" id="resumenHora">
              <span>Hora</span><span>Sin definir</span>
            </div>
            <div class="cr-summary-row is-empty" id="resumenLugar">
              <span>Encuentro</span><span>Sin definir</span>
            </div>

            <button type="button" class="cr-cta" id="enviarCitaBtn">Enviar solicitud de cita</button>
            <div class="cr-form-error" id="formError">Revisa los campos marcados antes de continuar.</div>
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
function activarSelectorHora() {
  const picker = document.getElementById("horaCitaPicker");
  const trigger = document.getElementById("horaCitaTrigger");
  const triggerLabel = document.getElementById("horaCitaTriggerLabel");
  const panel = document.getElementById("horaCitaPanel");
  const hiddenInput = document.getElementById("horaCita");

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

  panel.querySelectorAll(".cr-hora-chip").forEach((chip) => {
    chip.addEventListener("click", () => {
      if (chip.disabled) return;
      hiddenInput.value = chip.dataset.hora;
      triggerLabel.textContent = chip.textContent;
      trigger.classList.add("has-value");
      trigger.classList.remove("cr-invalid");
      panel.querySelectorAll(".cr-hora-chip.is-selected").forEach((c) => c.classList.remove("is-selected"));
      chip.classList.add("is-selected");
      cerrarPanel();
      hiddenInput.dispatchEvent(new Event("change"));
    });
  });
}

// ---------- DISPONIBILIDAD DE HORARIO (según la fecha elegida) ----------
// Mismo mecanismo que activarDisponibilidadHorario() en reserva.js:
// al elegir fecha, se deshabilitan los chips que ya tiene ocupados
// este roomie (con una cita pendiente o aceptada de OTRA persona).
function activarDisponibilidadHorario(item) {
  const fechaInput = document.getElementById("fechaCita");
  const hiddenInput = document.getElementById("horaCita");
  const trigger = document.getElementById("horaCitaTrigger");
  const triggerLabel = document.getElementById("horaCitaTriggerLabel");
  const chips = () => document.querySelectorAll("#horaCitaPanel .cr-hora-chip");

  async function actualizar() {
    const fecha = fechaInput.value;

    chips().forEach((chip) => {
      chip.disabled = false;
      chip.classList.remove("is-unavailable");
    });
    if (!fecha) return;

    try {
      const resp = await fetch(`${API_BASE}/roomies/${item.id}/horarios-ocupados?fecha=${fecha}`, {
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
  const fechaInput = document.getElementById("fechaCita");
  const horaInput = document.getElementById("horaCita");
  const tipoSelect = document.getElementById("tipoEncuentro");

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

  tipoSelect.addEventListener("change", () => {
    const el = document.querySelector("#resumenLugar span:last-child");
    const contenedor = document.getElementById("resumenLugar");
    if (tipoSelect.value) {
      el.textContent = ETIQUETAS_TIPO_ENCUENTRO[tipoSelect.value];
      contenedor.classList.remove("is-empty");
    } else {
      el.textContent = "Sin definir";
      contenedor.classList.add("is-empty");
    }
  });
}

// ---------- VALIDACIÓN Y ENVÍO ----------
function activarEnvio(item) {
  const btn = document.getElementById("enviarCitaBtn");

  btn.addEventListener("click", async () => {
    const campos = {
      fecha_cita: document.getElementById("fechaCita").value.trim(),
      hora_cita: document.getElementById("horaCita").value.trim(),
      tipo_encuentro: document.getElementById("tipoEncuentro").value.trim(),
      lugar_encuentro: document.getElementById("lugarEncuentro").value.trim(),
      mensaje: document.getElementById("mensaje").value.trim(),
      contacto_nombre: document.getElementById("nombre").value.trim(),
      contacto_correo: document.getElementById("correo").value.trim(),
      contacto_telefono: document.getElementById("telefono").value.trim(),
    };

    // ---- Validación en cliente (misma UX que el original) ----
    let valido = true;
    const marcarError = (idInput, condicionInvalida) => {
      const input = document.getElementById(idInput);
      const error = document.getElementById(`err-${idInput}`);
      // #horaCita es un input oculto (el valor real lo guarda el panel
      // de chips); el que se ve y se marca en rojo es su botón disparador.
      const elementoVisual = input.type === "hidden" ? document.getElementById(`${idInput}Trigger`) : input;
      if (condicionInvalida) {
        elementoVisual.classList.add("cr-invalid");
        error.classList.add("is-visible");
        valido = false;
      } else {
        elementoVisual.classList.remove("cr-invalid");
        error.classList.remove("is-visible");
      }
    };

    marcarError("fechaCita", !campos.fecha_cita);
    marcarError("horaCita", !campos.hora_cita);
    marcarError("tipoEncuentro", !campos.tipo_encuentro);
    marcarError("lugarEncuentro", campos.lugar_encuentro.length < 3);
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

    // ---- Envío a la API (la validación real y autoritativa vive en CitaRequest) ----
    btn.disabled = true;
    btn.textContent = "Enviando...";

    try {
      const resp = await fetch(`${API_BASE}/roomies/${item.id}/citas`, {
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
        formError.textContent = "No puedes agendar una cita contigo mismo/a.";
        formError.classList.add("is-visible");
        return;
      }
      if (resp.status === 409) {
        // Perfil sin tipo_usuario/telefono: el backend no deja agendar
        // nada nuevo hasta que se complete (ver perfil.completo).
        const datos = await resp.json().catch(() => ({}));
        formError.textContent = datos.mensaje || "Completa tu perfil antes de continuar.";
        formError.classList.add("is-visible");
        setTimeout(() => { window.location.href = "/onboarding"; }, 1500);
        return;
      }
      if (!resp.ok) throw new Error("Error al enviar la solicitud");

      const { cita } = await resp.json();
      window.location.href = `/confirmacion-cita?citaId=${cita.id}`;
    } catch (err) {
      console.error(err);
      formError.textContent = "Ocurrió un error al enviar tu solicitud. Intenta de nuevo.";
      formError.classList.add("is-visible");
    } finally {
      btn.disabled = false;
      btn.textContent = "Enviar solicitud de cita";
    }
  });
}

cargarCita();
