/* =========================================================
   Roommatch - Recuperar contraseña (Paso 3: nueva contraseña)
   ========================================================= */

const API_BASE = "/api";

const email = sessionStorage.getItem("roommatch_recuperar_email");
const token = sessionStorage.getItem("roommatch_recuperar_token");

if (!email || !token) {
  // No pasó por los pasos anteriores.
  window.location.href = "/recuperar-password";
}

const form = document.getElementById("nuevaPasswordForm");
const passwordInput = document.getElementById("password");
const passwordConfirmInput = document.getElementById("passwordConfirm");
const passwordGroup = document.getElementById("passwordGroup");
const passwordConfirmGroup = document.getElementById("passwordConfirmGroup");
const passwordError = document.getElementById("passwordError");
const passwordConfirmError = document.getElementById("passwordConfirmError");
const formMessage = document.getElementById("formMessage");
const submitBtn = document.getElementById("submitBtn");
const togglePasswordBtn = document.getElementById("togglePassword");
const toast = document.getElementById("toast");

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

togglePasswordBtn.addEventListener("click", () => {
  const esPassword = passwordInput.type === "password";
  passwordInput.type = esPassword ? "text" : "password";
  togglePasswordBtn.textContent = esPassword ? "Ocultar" : "Mostrar";
});

passwordInput.addEventListener("input", () => limpiarError(passwordGroup, passwordError));
passwordConfirmInput.addEventListener("input", () => limpiarError(passwordConfirmGroup, passwordConfirmError));

form.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  formMessage.textContent = "";
  formMessage.className = "form-message";

  const password = passwordInput.value;
  const passwordConfirm = passwordConfirmInput.value;
  let esValido = true;

  if (!password || password.length < 6) {
    mostrarError(passwordGroup, passwordError, "Debe tener al menos 6 caracteres.");
    esValido = false;
  } else {
    limpiarError(passwordGroup, passwordError);
  }

  if (password !== passwordConfirm) {
    mostrarError(passwordConfirmGroup, passwordConfirmError, "Las contraseñas no coinciden.");
    esValido = false;
  } else {
    limpiarError(passwordConfirmGroup, passwordConfirmError);
  }

  if (!esValido) return;

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/password/restablecer`, {
      method: "POST",
      headers: { "Content-Type": "application/json", Accept: "application/json" },
      body: JSON.stringify({
        email,
        token,
        password,
        password_confirmation: passwordConfirm,
      }),
    });

    const datos = await respuesta.json();
    setCargando(false);

    if (!respuesta.ok) {
      formMessage.classList.add("error");
      formMessage.textContent = datos.mensaje || "No se pudo actualizar la contraseña.";
      mostrarToast(datos.mensaje || "Error al actualizar la contraseña");
      return;
    }

    sessionStorage.removeItem("roommatch_recuperar_email");
    sessionStorage.removeItem("roommatch_recuperar_token");

    formMessage.classList.add("success");
    formMessage.textContent = "¡Contraseña actualizada! Redirigiendo al login...";
    mostrarToast("Contraseña actualizada");
    setTimeout(() => {
      window.location.href = "/login";
    }, 1000);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});
