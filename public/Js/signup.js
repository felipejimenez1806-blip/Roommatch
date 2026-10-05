/* =========================================================
   Roommatch - Registro (Crear cuenta)
   ---------------------------------------------------------
   Igual que la versión original, pero ahora conectado a la
   API real de Laravel (POST /api/registro) en vez de
   localStorage. El token que devuelve el backend se guarda
   para mantener la sesión iniciada tras el registro.
   tipo_usuario ya NO se pide en este formulario: todo registro
   público nace 'cliente' por el default de la columna en BD.
   ========================================================= */

const API_BASE = "/api";
const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";
const STORAGE_ROL_ACTIVO_KEY = "roommatch_rol_activo";

// --- Referencias DOM ---
const form = document.getElementById("signupForm");

const nombreInput = document.getElementById("nombre");
const emailInput = document.getElementById("email");
const telefonoInput = document.getElementById("telefono");
const direccionInput = document.getElementById("direccion");
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirmPassword");
const terminosCheckbox = document.getElementById("aceptaTerminos");

const nombreGroup = document.getElementById("nombreGroup");
const emailGroup = document.getElementById("emailGroup");
const telefonoGroup = document.getElementById("telefonoGroup");
const direccionGroup = document.getElementById("direccionGroup");
const passwordGroup = document.getElementById("passwordGroup");
const confirmPasswordGroup = document.getElementById("confirmPasswordGroup");
const terminosGroup = document.getElementById("terminosGroup");

const nombreError = document.getElementById("nombreError");
const emailError = document.getElementById("emailError");
const telefonoError = document.getElementById("telefonoError");
const direccionError = document.getElementById("direccionError");
const passwordError = document.getElementById("passwordError");
const confirmPasswordError = document.getElementById("confirmPasswordError");
const terminosError = document.getElementById("terminosError");

const formMessage = document.getElementById("formMessage");
const passwordChecklist = document.getElementById("passwordChecklist");
const togglePasswordBtn = document.getElementById("togglePassword");
const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPassword");
const submitBtn = document.getElementById("submitBtn");
const toast = document.getElementById("toast");
const socialButtons = document.querySelectorAll(".btn-social");

// --- Utilidades ---
function esCorreoValido(valor) {
  const patron = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return patron.test(valor.trim());
}

function esTelefonoValido(valor) {
  const limpio = valor.replace(/[\s-]/g, "");
  const patron = /^(\+57)?[0-9]{7,10}$/;
  return patron.test(limpio);
}

// --- Fuerza de la contraseña (checklist en vivo) ---
// "Contiene tu nombre o correo" se revisa contra las partes del nombre
// de 3+ letras y contra el usuario del correo (antes del @), para no
// marcar como inválida una coincidencia de una sola letra al azar.
function passwordContieneNombreOCorreo(pw, nombre, email) {
  const pwMin = pw.toLowerCase();
  const usuarioCorreo = email.split("@")[0].toLowerCase();
  if (usuarioCorreo.length >= 3 && pwMin.includes(usuarioCorreo)) return true;
  return nombre
    .toLowerCase()
    .split(/\s+/)
    .filter((parte) => parte.length >= 3)
    .some((parte) => pwMin.includes(parte));
}

function evaluarPassword() {
  const pw = passwordInput.value;
  const reglas = {
    length: pw.length >= 8,
    upper: /[A-ZÁÉÍÓÚÑ]/.test(pw),
    lower: /[a-záéíóúñ]/.test(pw),
    number: /[0-9]/.test(pw),
    special: /[^A-Za-z0-9ÁÉÍÓÚÑáéíóúñ]/.test(pw),
    noName: pw.length === 0 || !passwordContieneNombreOCorreo(pw, nombreInput.value, emailInput.value),
  };

  Object.entries(reglas).forEach(([regla, cumple]) => {
    const li = passwordChecklist.querySelector(`[data-rule="${regla}"]`);
    if (li) li.classList.toggle("is-valid", cumple);
  });

  return Object.values(reglas).every(Boolean);
}

passwordInput.addEventListener("input", evaluarPassword);
nombreInput.addEventListener("input", evaluarPassword);
emailInput.addEventListener("input", evaluarPassword);
evaluarPassword();

function mostrarError(grupo, elementoError, mensaje) {
  grupo.classList.add("has-error");
  elementoError.textContent = mensaje;
}

function limpiarError(grupo, elementoError) {
  grupo.classList.remove("has-error");
  elementoError.textContent = "";
}

function mostrarToast(mensaje, duracion = 3200) {
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

// --- Mostrar / ocultar contraseñas ---
togglePasswordBtn.addEventListener("click", () => {
  const esPassword = passwordInput.type === "password";
  passwordInput.type = esPassword ? "text" : "password";
  togglePasswordBtn.textContent = esPassword ? "Ocultar" : "Mostrar";
});

toggleConfirmPasswordBtn.addEventListener("click", () => {
  const esPassword = confirmPasswordInput.type === "password";
  confirmPasswordInput.type = esPassword ? "text" : "password";
  toggleConfirmPasswordBtn.textContent = esPassword ? "Ocultar" : "Mostrar";
});

// --- Validación en vivo ---
nombreInput.addEventListener("input", () => limpiarError(nombreGroup, nombreError));
emailInput.addEventListener("input", () => limpiarError(emailGroup, emailError));
telefonoInput.addEventListener("input", () => limpiarError(telefonoGroup, telefonoError));
direccionInput.addEventListener("input", () => limpiarError(direccionGroup, direccionError));
passwordInput.addEventListener("input", () => limpiarError(passwordGroup, passwordError));
confirmPasswordInput.addEventListener("input", () => limpiarError(confirmPasswordGroup, confirmPasswordError));
terminosCheckbox.addEventListener("change", () => limpiarError(terminosGroup, terminosError));

// --- Mapea errores de validación de Laravel (422) a los campos del form ---
function mostrarErroresBackend(errores) {
  const mapa = {
    nombre: [nombreGroup, nombreError],
    email: [emailGroup, emailError],
    telefono: [telefonoGroup, telefonoError],
    direccion: [direccionGroup, direccionError],
    password: [passwordGroup, passwordError],
  };
  Object.entries(errores).forEach(([campo, mensajes]) => {
    if (mapa[campo]) {
      mostrarError(mapa[campo][0], mapa[campo][1], mensajes[0]);
    }
  });
}

// --- Envío del formulario ---
form.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  formMessage.textContent = "";
  formMessage.className = "form-message";

  const nombre = nombreInput.value.trim();
  const email = emailInput.value.trim();
  const telefono = telefonoInput.value.trim();
  const direccion = direccionInput.value.trim();
  const password = passwordInput.value;
  const confirmPassword = confirmPasswordInput.value;

  let esValido = true;

  if (!nombre || nombre.length < 3) {
    mostrarError(nombreGroup, nombreError, "Ingresa tu nombre completo.");
    esValido = false;
  } else {
    limpiarError(nombreGroup, nombreError);
  }

  if (!email) {
    mostrarError(emailGroup, emailError, "Ingresa tu correo electrónico.");
    esValido = false;
  } else if (!esCorreoValido(email)) {
    mostrarError(emailGroup, emailError, "Ingresa un correo válido.");
    esValido = false;
  } else {
    limpiarError(emailGroup, emailError);
  }

  if (!telefono) {
    mostrarError(telefonoGroup, telefonoError, "Ingresa tu número de teléfono.");
    esValido = false;
  } else if (!esTelefonoValido(telefono)) {
    mostrarError(telefonoGroup, telefonoError, "Ingresa un teléfono válido (10 dígitos).");
    esValido = false;
  } else {
    limpiarError(telefonoGroup, telefonoError);
  }

  if (!direccion || direccion.length < 5) {
    mostrarError(direccionGroup, direccionError, "Ingresa tu dirección.");
    esValido = false;
  } else {
    limpiarError(direccionGroup, direccionError);
  }

  if (!password) {
    mostrarError(passwordGroup, passwordError, "Ingresa una contraseña.");
    esValido = false;
  } else if (!evaluarPassword()) {
    mostrarError(passwordGroup, passwordError, "Tu contraseña no cumple con todos los requisitos de la lista.");
    esValido = false;
  } else {
    limpiarError(passwordGroup, passwordError);
  }

  if (!confirmPassword) {
    mostrarError(confirmPasswordGroup, confirmPasswordError, "Confirma tu contraseña.");
    esValido = false;
  } else if (confirmPassword !== password) {
    mostrarError(confirmPasswordGroup, confirmPasswordError, "Las contraseñas no coinciden.");
    esValido = false;
  } else {
    limpiarError(confirmPasswordGroup, confirmPasswordError);
  }

  if (!terminosCheckbox.checked) {
    mostrarError(terminosGroup, terminosError, "Debes aceptar los términos y condiciones para continuar.");
    esValido = false;
  } else {
    limpiarError(terminosGroup, terminosError);
  }

  if (!esValido) return;

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/registro`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        nombre,
        email,
        telefono,
        direccion,
        password,
        password_confirmation: confirmPassword,
        acepta_terminos: terminosCheckbox.checked,
      }),
    });

    const datos = await respuesta.json();
    setCargando(false);

    if (!respuesta.ok) {
      if (respuesta.status === 422 && datos.errors) {
        mostrarErroresBackend(datos.errors);
      }
      formMessage.classList.add("error");
      formMessage.textContent = datos.mensaje || "No se pudo crear la cuenta.";
      mostrarToast(datos.mensaje || "No se pudo crear la cuenta.");
      return;
    }

    sessionStorage.setItem(STORAGE_TOKEN_KEY, datos.token);
    sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(datos.usuario));
    sessionStorage.setItem(STORAGE_ROL_ACTIVO_KEY, datos.rolActivo || "cliente");

    formMessage.classList.add("success");
    formMessage.textContent = "¡Cuenta creada con éxito! Redirigiendo al inicio de sesión...";
    mostrarToast("Cuenta creada correctamente");
    form.reset();

    setTimeout(() => {
      window.location.href = "/login";
    }, 1500);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});

// --- Botones sociales (Google / Facebook) ---
// Esto es una navegación de página completa (no fetch): OAuth necesita
// que el navegador vaya realmente a Google/Facebook, así que no hay
// respuesta que manejar aquí. El flujo continúa en
// /auth/{proveedor}/callback -> auth/oauth-callback.blade.php, que
// guarda el token en sessionStorage y redirige a /onboarding o /dashboard.
socialButtons.forEach((boton) => {
  boton.addEventListener("click", () => {
    const proveedor = boton.dataset.provider;
    window.location.href = `/auth/${proveedor}/redirect`;
  });
});
