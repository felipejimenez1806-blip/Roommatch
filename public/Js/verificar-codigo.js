/* =========================================================
   Roommatch - Recuperar contraseña (Paso 2: verificar código)
   ========================================================= */

const API_BASE = "/api";
const SEGUNDOS_COOLDOWN = 60;

const email = sessionStorage.getItem("roommatch_recuperar_email");

if (!email) {
  // Si llegan directo a esta pantalla sin pasar por el paso 1.
  window.location.href = "/recuperar-password";
}

document.getElementById("descripcionCorreo").textContent =
  `Ingresa el código de 6 dígitos que enviamos a ${email}.`;

const form = document.getElementById("codigoForm");
const casillas = Array.from(document.querySelectorAll(".codigo-casilla"));
const codigoGrupo = document.getElementById("codigoGrupo");
const codigoError = document.getElementById("codigoError");
const formMessage = document.getElementById("formMessage");
const submitBtn = document.getElementById("submitBtn");
const reenviarBtn = document.getElementById("reenviarBtn");
const toast = document.getElementById("toast");

function mostrarToast(mensaje, duracion = 3500) {
  toast.textContent = mensaje;
  toast.classList.add("show");
  clearTimeout(mostrarToast._t);
  mostrarToast._t = setTimeout(() => toast.classList.remove("show"), duracion);
}

function limpiarErrorCodigo() {
  codigoGrupo.classList.remove("has-error");
  casillas.forEach((c) => c.classList.remove("has-error"));
  codigoError.textContent = "";
}

function mostrarErrorCodigo(mensaje) {
  casillas.forEach((c) => c.classList.add("has-error"));
  codigoError.textContent = mensaje;
}

function setCargando(cargando) {
  submitBtn.disabled = cargando;
  submitBtn.querySelector(".btn-text").style.visibility = cargando ? "hidden" : "visible";
  submitBtn.querySelector(".spinner").hidden = !cargando;
}

function obtenerCodigo() {
  return casillas.map((c) => c.value).join("");
}

function enfocarCasilla(indice) {
  if (casillas[indice]) casillas[indice].focus();
}

// --- Interacción de las casillas ---
casillas.forEach((casilla, indice) => {
  casilla.addEventListener("input", () => {
    casilla.value = casilla.value.replace(/[^0-9]/g, "").slice(0, 1);
    limpiarErrorCodigo();
    if (casilla.value && indice < casillas.length - 1) {
      enfocarCasilla(indice + 1);
    }
    if (obtenerCodigo().length === casillas.length) {
      form.requestSubmit();
    }
  });

  casilla.addEventListener("keydown", (evento) => {
    if (evento.key === "Backspace" && !casilla.value && indice > 0) {
      enfocarCasilla(indice - 1);
    }
  });

  casilla.addEventListener("paste", (evento) => {
    evento.preventDefault();
    const texto = (evento.clipboardData.getData("text") || "").replace(/[^0-9]/g, "");
    if (!texto) return;
    texto.split("").slice(0, casillas.length).forEach((digito, i) => {
      if (casillas[i]) casillas[i].value = digito;
    });
    limpiarErrorCodigo();
    const siguienteVacia = casillas.findIndex((c) => !c.value);
    enfocarCasilla(siguienteVacia === -1 ? casillas.length - 1 : siguienteVacia);
    if (obtenerCodigo().length === casillas.length) {
      form.requestSubmit();
    }
  });
});

enfocarCasilla(0);

// --- Envío / verificación ---
form.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  formMessage.textContent = "";
  formMessage.className = "form-message";

  const codigo = obtenerCodigo();

  if (codigo.length !== casillas.length) {
    mostrarErrorCodigo("Completa los 6 dígitos.");
    return;
  }
  limpiarErrorCodigo();

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/password/verificar-codigo`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ email, codigo }),
    });

    const datos = await respuesta.json();
    setCargando(false);

    if (!respuesta.ok) {
      mostrarErrorCodigo(datos.mensaje || "Código incorrecto.");
      casillas.forEach((c) => (c.value = ""));
      enfocarCasilla(0);
      return;
    }

    sessionStorage.setItem("roommatch_recuperar_token", datos.token);

    formMessage.classList.add("success");
    formMessage.textContent = "¡Código verificado! Redirigiendo...";
    setTimeout(() => {
      window.location.href = "/nueva-contrasena";
    }, 600);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});

// --- Reenviar código con cooldown ---
function iniciarCooldown() {
  let restante = SEGUNDOS_COOLDOWN;
  reenviarBtn.disabled = true;
  reenviarBtn.textContent = `Reenviar en ${restante}s`;

  const intervalo = setInterval(() => {
    restante -= 1;
    if (restante <= 0) {
      clearInterval(intervalo);
      reenviarBtn.disabled = false;
      reenviarBtn.textContent = "Reenviar código";
    } else {
      reenviarBtn.textContent = `Reenviar en ${restante}s`;
    }
  }, 1000);
}

reenviarBtn.addEventListener("click", async () => {
  reenviarBtn.disabled = true;

  try {
    const respuesta = await fetch(`${API_BASE}/password/enviar-codigo`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ email }),
    });

    const datos = await respuesta.json();

    if (!respuesta.ok) {
      mostrarToast(datos.mensaje || "No se pudo reenviar el código.");
      reenviarBtn.disabled = false;
      return;
    }

    mostrarToast("Código reenviado. Revisa tu correo.");
    casillas.forEach((c) => (c.value = ""));
    enfocarCasilla(0);
    iniciarCooldown();
  } catch (error) {
    mostrarToast("Error de conexión al reenviar.");
    reenviarBtn.disabled = false;
  }
});

iniciarCooldown();
