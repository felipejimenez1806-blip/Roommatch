/* =========================================================
   Roommatch - Recuperar contraseña (Paso 1: pedir correo)
   ========================================================= */

const API_BASE = "/api";

const form = document.getElementById("recuperarForm");
const emailInput = document.getElementById("email");
const emailGroup = document.getElementById("emailGroup");
const emailError = document.getElementById("emailError");
const formMessage = document.getElementById("formMessage");
const submitBtn = document.getElementById("submitBtn");
const toast = document.getElementById("toast");

function esCorreoValido(valor) {
  const patron = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return patron.test(valor.trim());
}

function mostrarError(grupo, elementoError, mensaje) {
  grupo.classList.add("has-error");
  elementoError.textContent = mensaje;
}

function limpiarError(grupo, elementoError) {
  grupo.classList.remove("has-error");
  elementoError.textContent = "";
}

function mostrarToast(mensaje, duracion = 3500) {
  toast.textContent = mensaje;
  toast.classList.add("show");
  clearTimeout(mostrarToast._t);
  mostrarToast._t = setTimeout(() => toast.classList.remove("show"), duracion);
}

function setCargando(cargando) {
  submitBtn.disabled = cargando;
  submitBtn.querySelector(".btn-text").style.visibility = cargando ? "hidden" : "visible";
  submitBtn.querySelector(".spinner").hidden = !cargando;
}

emailInput.addEventListener("input", () => limpiarError(emailGroup, emailError));

form.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  formMessage.textContent = "";
  formMessage.className = "form-message";

  const email = emailInput.value.trim();

  if (!email) {
    mostrarError(emailGroup, emailError, "Ingresa tu correo electrónico.");
    return;
  }
  if (!esCorreoValido(email)) {
    mostrarError(emailGroup, emailError, "Ingresa un correo válido.");
    return;
  }
  limpiarError(emailGroup, emailError);

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/password/enviar-codigo`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({ email }),
    });

    const datos = await respuesta.json();
    setCargando(false);

    if (!respuesta.ok) {
      formMessage.classList.add("error");
      formMessage.textContent = datos.mensaje || "No se pudo enviar el código.";
      mostrarToast(datos.mensaje || "Error al enviar el código");
      return;
    }

    // Guardamos el correo para usarlo en el siguiente paso.
    sessionStorage.setItem("roommatch_recuperar_email", email);

    mostrarToast(datos.mensaje);
    setTimeout(() => {
      window.location.href = "/verificar-codigo";
    }, 700);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});
