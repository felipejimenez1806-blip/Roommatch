/* =========================================================
   Roommatch - Onboarding post-OAuth
   ---------------------------------------------------------
   Wizard de 2 pasos para completar telefono y genero tras un
   login con Google/Facebook (Socialite). tipo_usuario ya NO
   se pide aquí: todo usuario nace 'cliente' desde su creación
   (default en BD), tanto en registro local como social.
   Requiere sesión ya iniciada (token en sessionStorage), ya
   que EnsurePerfilCompleto solo deja pasar aquí a usuarios
   autenticados con el perfil incompleto.
   ========================================================= */

const API_BASE = "/api";
const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";
const TOTAL_PASOS = 2;

let pasoActual = 1;
const datos = { telefono: "", genero: "" };

// --- Referencias DOM ---
const form = document.getElementById("onboardingForm");
const stepper = document.getElementById("stepper");
const paneles = document.querySelectorAll(".step-panel");

const telefonoInput = document.getElementById("telefono");
const telefonoGroup = document.getElementById("telefonoGroup");
const telefonoError = document.getElementById("telefonoError");

const generoField = document.getElementById("generoField");
const generoTrigger = document.getElementById("generoTrigger");
const generoMenu = generoField.querySelector("[data-select-menu]");
const generoValueEl = generoField.querySelector("[data-select-value]");
const generoOptions = Array.from(generoMenu.querySelectorAll(".onb-select-option"));
const generoError = document.getElementById("generoError");

const summaryTelefono = document.getElementById("summaryTelefono");

const formMessage = document.getElementById("formMessage");
const submitBtn = document.getElementById("submitBtn");
const toast = document.getElementById("toast");

// --- Utilidades ---
function esTelefonoValido(valor) {
  const limpio = valor.replace(/[\s-]/g, "");
  const patron = /^(\+57)?[0-9]{7,10}$/;
  return patron.test(limpio);
}

function mostrarToast(mensaje, duracion = 3200) {
  toast.textContent = mensaje;
  toast.classList.add("show");
  clearTimeout(mostrarToast._t);
  mostrarToast._t = setTimeout(() => toast.classList.remove("show"), duracion);
}

function mostrarError(grupo, elementoError, mensaje) {
  if (grupo) grupo.classList.add("has-error");
  elementoError.textContent = mensaje;
}

function limpiarError(grupo, elementoError) {
  if (grupo) grupo.classList.remove("has-error");
  elementoError.textContent = "";
}

function obtenerToken() {
  return sessionStorage.getItem(STORAGE_TOKEN_KEY);
}

// Si no hay sesión iniciada, esta pantalla no tiene sentido: se manda
// al login en vez de dejar que el submit falle con un 401 confuso.
if (!obtenerToken()) {
  window.location.href = "/login";
}

// --- Navegación entre pasos ---
function irAPaso(nuevoPaso) {
  pasoActual = nuevoPaso;

  paneles.forEach((panel) => {
    panel.classList.toggle("is-active", Number(panel.dataset.panel) === nuevoPaso);
  });

  stepper.querySelectorAll("li[data-step]").forEach((li) => {
    const numero = Number(li.dataset.step);
    li.classList.toggle("is-active", numero === nuevoPaso);
    li.classList.toggle("is-done", numero < nuevoPaso);
  });

  if (nuevoPaso === TOTAL_PASOS) {
    actualizarResumen();
  }
}

function actualizarResumen() {
  summaryTelefono.textContent = datos.telefono || "—";
}

document.querySelectorAll(".summary-edit").forEach((boton) => {
  boton.addEventListener("click", () => irAPaso(Number(boton.dataset.goto)));
});

// --- Paso 1: teléfono ---
telefonoInput.addEventListener("input", () => limpiarError(telefonoGroup, telefonoError));

document.getElementById("next1").addEventListener("click", () => {
  const telefono = telefonoInput.value.trim();
  if (!telefono) {
    mostrarError(telefonoGroup, telefonoError, "Ingresa tu número de teléfono.");
    return;
  }
  if (!esTelefonoValido(telefono)) {
    mostrarError(telefonoGroup, telefonoError, "Ingresa un teléfono válido (10 dígitos).");
    return;
  }
  limpiarError(telefonoGroup, telefonoError);
  datos.telefono = telefono;
  irAPaso(2);
});

// --- Paso 2: género ---
function cerrarGeneroMenu() {
  generoMenu.hidden = true;
  generoTrigger.setAttribute("aria-expanded", "false");
  generoTrigger.classList.remove("is-open");
  document.removeEventListener("click", cerrarGeneroMenuFuera);
}

function cerrarGeneroMenuFuera(e) {
  if (!generoField.contains(e.target)) cerrarGeneroMenu();
}

function abrirGeneroMenu() {
  generoMenu.hidden = false;
  generoTrigger.setAttribute("aria-expanded", "true");
  generoTrigger.classList.add("is-open");
  document.addEventListener("click", cerrarGeneroMenuFuera);
}

generoTrigger.addEventListener("click", (e) => {
  e.stopPropagation();
  if (generoMenu.hidden) abrirGeneroMenu(); else cerrarGeneroMenu();
});

generoOptions.forEach((opt) => {
  opt.addEventListener("click", () => {
    const valor = opt.dataset.value;
    datos.genero = valor || null;
    generoValueEl.textContent = opt.textContent;
    generoValueEl.classList.toggle("is-placeholder", !valor);
    generoOptions.forEach((o) => o.classList.remove("is-selected"));
    opt.classList.add("is-selected");
    cerrarGeneroMenu();
    limpiarError(null, generoError);
  });
});

document.getElementById("back2").addEventListener("click", () => irAPaso(1));

function setCargando(cargando) {
  submitBtn.disabled = cargando;
  submitBtn.querySelector(".btn-text").style.visibility = cargando ? "hidden" : "visible";
  submitBtn.querySelector(".spinner").hidden = !cargando;
}

// --- Envío final ---
form.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  formMessage.textContent = "";
  formMessage.className = "form-message";

  // Género es opcional: datos.genero ya quedó actualizado por el
  // dropdown (o sigue en null si no se tocó).
  limpiarError(null, generoError);

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/onboarding`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
        Authorization: `Bearer ${obtenerToken()}`,
      },
      body: JSON.stringify({
        telefono: datos.telefono,
        genero: datos.genero,
      }),
    });

    const respuestaDatos = await respuesta.json();
    setCargando(false);

    if (!respuesta.ok) {
      if (respuesta.status === 401) {
        sessionStorage.removeItem(STORAGE_TOKEN_KEY);
        sessionStorage.removeItem(STORAGE_USER_KEY);
        mostrarToast(respuestaDatos.mensaje || "Tu sesión expiró.");
        setTimeout(() => { window.location.href = "/login"; }, 1200);
        return;
      }

      if (respuesta.status === 422 && respuestaDatos.errors) {
        if (respuestaDatos.errors.telefono) {
          irAPaso(1);
          mostrarError(telefonoGroup, telefonoError, respuestaDatos.errors.telefono[0]);
        } else if (respuestaDatos.errors.genero) {
          mostrarError(null, generoError, respuestaDatos.errors.genero[0]);
        }
      }
      formMessage.classList.add("error");
      formMessage.textContent = respuestaDatos.mensaje || "No se pudo completar tu perfil.";
      mostrarToast(respuestaDatos.mensaje || "No se pudo completar tu perfil.");
      return;
    }

    if (respuestaDatos.usuario) {
      sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(respuestaDatos.usuario));
    }

    formMessage.classList.add("success");
    formMessage.textContent = "¡Perfil completado! Redirigiendo...";
    mostrarToast("Perfil completado correctamente");

    setTimeout(() => {
      window.location.href = "/dashboard";
    }, 1200);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});
