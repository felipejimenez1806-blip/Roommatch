/* =========================================================
   Roommatch - Crear administrador (panel de admin)
   ---------------------------------------------------------
   Página aparte (no modal) porque otorgar privilegios de admin
   es una acción sensible que merece su propia pantalla, con un
   formulario más riguroso que el registro normal:
     - Pide 'area' y 'cargo' (contexto organizacional).
     - Contraseña de la nueva cuenta: mínimo 10 caracteres (vs. 8
       en el registro normal) + mayúscula/minúscula/número/símbolo.
     - Exige la contraseña ACTUAL del admin que está creando la
       cuenta, como paso de confirmación (ver
       Api\Admin\UsuarioController::crearAdministrador en backend).
   Mismo patrón de autenticación y protección de acceso que admin.js.
   ========================================================= */

// API_BASE NO se declara aquí: esta página extiende layouts.app, que ya
// define esa constante globalmente (mismo motivo por el que admin.js,
// perfil.js y nav.js tampoco la declaran). Declararla de nuevo aquí
// producía un SyntaxError de "Identifier ya declarado" que rompía TODO
// el archivo — por eso no validaba nada ni pintaba el checklist en verde.
const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";

const token = sessionStorage.getItem(STORAGE_TOKEN_KEY);
const usuarioActual = JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");

if (!token || !usuarioActual) {
  window.location.href = "/login";
  throw new Error("Redirigiendo a login: no hay sesión activa.");
}
if (!(usuarioActual.roles || []).includes("admin")) {
  window.location.href = "/dashboard";
  throw new Error("Acceso restringido: la cuenta activa no tiene rol de administrador.");
}

// --- Referencias DOM ---
const form = document.getElementById("crearAdminForm");

const nombreInput = document.getElementById("nombre");
const correoInput = document.getElementById("correo");
const areaInput = document.getElementById("area");
const cargoInput = document.getElementById("cargo");
const passwordInput = document.getElementById("password");
const confirmPasswordInput = document.getElementById("confirmPassword");
const passwordActualInput = document.getElementById("passwordActual");

const nombreGroup = document.getElementById("nombreGroup");
const correoGroup = document.getElementById("correoGroup");
const areaGroup = document.getElementById("areaGroup");
const cargoGroup = document.getElementById("cargoGroup");
const passwordGroup = document.getElementById("passwordGroup");
const confirmPasswordGroup = document.getElementById("confirmPasswordGroup");
const passwordActualGroup = document.getElementById("passwordActualGroup");

const nombreError = document.getElementById("nombreError");
const correoError = document.getElementById("correoError");
const areaError = document.getElementById("areaError");
const cargoError = document.getElementById("cargoError");
const passwordError = document.getElementById("passwordError");
const confirmPasswordError = document.getElementById("confirmPasswordError");
const passwordActualError = document.getElementById("passwordActualError");

const formMessage = document.getElementById("formMessage");
const passwordChecklist = document.getElementById("passwordChecklist");
const togglePasswordBtn = document.getElementById("togglePassword");
const toggleConfirmPasswordBtn = document.getElementById("toggleConfirmPassword");
const togglePasswordActualBtn = document.getElementById("togglePasswordActual");
const submitBtn = document.getElementById("submitBtn");
const toast = document.getElementById("toast");

const PASSWORD_MIN = 10;

// --- Utilidades ---
function esCorreoValido(valor) {
  const patron = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return patron.test(valor.trim());
}

function passwordContieneNombreOCorreo(pw, nombre, correo) {
  const pwMin = pw.toLowerCase();
  const usuarioCorreo = correo.split("@")[0].toLowerCase();
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
    length: pw.length >= PASSWORD_MIN,
    upper: /[A-ZÁÉÍÓÚÑ]/.test(pw),
    lower: /[a-záéíóúñ]/.test(pw),
    number: /[0-9]/.test(pw),
    special: /[^A-Za-z0-9ÁÉÍÓÚÑáéíóúñ]/.test(pw),
    noName: pw.length === 0 || !passwordContieneNombreOCorreo(pw, nombreInput.value, correoInput.value),
  };

  Object.entries(reglas).forEach(([regla, cumple]) => {
    const li = passwordChecklist.querySelector(`[data-rule="${regla}"]`);
    if (li) li.classList.toggle("is-valid", cumple);
  });

  return Object.values(reglas).every(Boolean);
}

passwordInput.addEventListener("input", evaluarPassword);
nombreInput.addEventListener("input", evaluarPassword);
correoInput.addEventListener("input", evaluarPassword);
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
function enlazarToggle(boton, input) {
  boton.addEventListener("click", () => {
    const esPassword = input.type === "password";
    input.type = esPassword ? "text" : "password";
    boton.textContent = esPassword ? "Ocultar" : "Mostrar";
  });
}
enlazarToggle(togglePasswordBtn, passwordInput);
enlazarToggle(toggleConfirmPasswordBtn, confirmPasswordInput);
enlazarToggle(togglePasswordActualBtn, passwordActualInput);

// --- Validación en vivo ---
nombreInput.addEventListener("input", () => limpiarError(nombreGroup, nombreError));
correoInput.addEventListener("input", () => limpiarError(correoGroup, correoError));
areaInput.addEventListener("input", () => limpiarError(areaGroup, areaError));
cargoInput.addEventListener("input", () => limpiarError(cargoGroup, cargoError));
passwordInput.addEventListener("input", () => limpiarError(passwordGroup, passwordError));
confirmPasswordInput.addEventListener("input", () => limpiarError(confirmPasswordGroup, confirmPasswordError));
passwordActualInput.addEventListener("input", () => limpiarError(passwordActualGroup, passwordActualError));

// --- Mapea errores de validación de Laravel (422) a los campos del form ---
function mostrarErroresBackend(errores) {
  const mapa = {
    nombre: [nombreGroup, nombreError],
    correo: [correoGroup, correoError],
    area: [areaGroup, areaError],
    cargo: [cargoGroup, cargoError],
    password: [passwordGroup, passwordError],
    password_actual: [passwordActualGroup, passwordActualError],
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
  const correo = correoInput.value.trim();
  const area = areaInput.value.trim();
  const cargo = cargoInput.value.trim();
  const password = passwordInput.value;
  const confirmPassword = confirmPasswordInput.value;
  const passwordActual = passwordActualInput.value;

  let esValido = true;

  if (!nombre || nombre.length < 3) {
    mostrarError(nombreGroup, nombreError, "Ingresa el nombre completo.");
    esValido = false;
  } else {
    limpiarError(nombreGroup, nombreError);
  }

  if (!correo) {
    mostrarError(correoGroup, correoError, "Ingresa un correo.");
    esValido = false;
  } else if (!esCorreoValido(correo)) {
    mostrarError(correoGroup, correoError, "Ingresa un correo válido.");
    esValido = false;
  } else {
    limpiarError(correoGroup, correoError);
  }

  if (!area || area.length < 2) {
    mostrarError(areaGroup, areaError, "Ingresa el área en la que trabaja.");
    esValido = false;
  } else {
    limpiarError(areaGroup, areaError);
  }

  if (!cargo || cargo.length < 2) {
    mostrarError(cargoGroup, cargoError, "Ingresa el cargo.");
    esValido = false;
  } else {
    limpiarError(cargoGroup, cargoError);
  }

  if (!password) {
    mostrarError(passwordGroup, passwordError, "Ingresa una contraseña para la nueva cuenta.");
    esValido = false;
  } else if (!evaluarPassword()) {
    mostrarError(passwordGroup, passwordError, "La contraseña no cumple con todos los requisitos de la lista.");
    esValido = false;
  } else {
    limpiarError(passwordGroup, passwordError);
  }

  if (!confirmPassword) {
    mostrarError(confirmPasswordGroup, confirmPasswordError, "Confirma la contraseña.");
    esValido = false;
  } else if (confirmPassword !== password) {
    mostrarError(confirmPasswordGroup, confirmPasswordError, "Las contraseñas no coinciden.");
    esValido = false;
  } else {
    limpiarError(confirmPasswordGroup, confirmPasswordError);
  }

  if (!passwordActual) {
    mostrarError(passwordActualGroup, passwordActualError, "Confirma tu propia contraseña para autorizar esta acción.");
    esValido = false;
  } else {
    limpiarError(passwordActualGroup, passwordActualError);
  }

  if (!esValido) return;

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/admin/administradores`, {
      method: "POST",
      headers: {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        nombre,
        correo,
        area,
        cargo,
        password,
        password_confirmation: confirmPassword,
        password_actual: passwordActual,
      }),
    });

    const datos = await respuesta.json();
    setCargando(false);

    if (respuesta.status === 401) {
      sessionStorage.removeItem(STORAGE_TOKEN_KEY);
      sessionStorage.removeItem(STORAGE_USER_KEY);
      window.location.href = "/login";
      return;
    }

    if (!respuesta.ok) {
      if (respuesta.status === 422 && datos.errors) {
        mostrarErroresBackend(datos.errors);
      }
      formMessage.classList.add("error");
      formMessage.textContent = datos.mensaje || "No se pudo crear la cuenta de administrador.";
      mostrarToast(datos.mensaje || "No se pudo crear la cuenta de administrador.");
      return;
    }

    formMessage.classList.add("success");
    formMessage.textContent = `${datos.usuario.nombre} fue creado como administrador. Redirigiendo...`;
    mostrarToast("Administrador creado correctamente");
    form.reset();

    setTimeout(() => {
      window.location.href = "/admin#admPanelUsuarios";
    }, 1500);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});
