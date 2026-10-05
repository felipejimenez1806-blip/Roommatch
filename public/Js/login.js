/* =========================================================
   Roommatch - Login
   ---------------------------------------------------------
   Igual que la versión original, pero conectado a la API
   real de Laravel + Sanctum (POST /api/login) en vez de
   localStorage. Ya no se necesita sembrar usuario demo ni
   admin manualmente: el admin vive en la base de datos
   real (AdminUsuarioSeeder).

   CAMBIO (roles N:N, Fase 4): /api/login puede devolver
   requiereSeleccionRol=true cuando la cuenta tiene más de un
   rol (cliente Y admin). En ese caso el token que llega es uno
   limitado (solo sirve para POST /api/seleccionar-rol) y NO se
   guarda todavía en sessionStorage — se mantiene en memoria
   mientras se muestra la pantalla "Ingresar como…", y solo se
   guarda la sesión real una vez el usuario elige.
   ========================================================= */

const API_BASE = "/api";
const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";
const STORAGE_ROL_ACTIVO_KEY = "roommatch_rol_activo";

// --- Referencias DOM ---
const form = document.getElementById("loginForm");
const emailInput = document.getElementById("email");
const passwordInput = document.getElementById("password");
const emailGroup = document.getElementById("emailGroup");
const passwordGroup = document.getElementById("passwordGroup");
const emailError = document.getElementById("emailError");
const passwordError = document.getElementById("passwordError");
const formMessage = document.getElementById("formMessage");
const togglePasswordBtn = document.getElementById("togglePassword");
const submitBtn = document.getElementById("submitBtn");
const toast = document.getElementById("toast");
const socialButtons = document.querySelectorAll(".btn-social");

// --- Utilidades ---
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

function guardarSesionYRedirigir(token, usuario, rolActivo) {
  sessionStorage.setItem(STORAGE_TOKEN_KEY, token);
  sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(usuario));
  sessionStorage.setItem(STORAGE_ROL_ACTIVO_KEY, rolActivo);

  formMessage.classList.add("success");
  formMessage.textContent = "¡Bienvenido a Roommatch! Redirigiendo...";
  mostrarToast("Inicio de sesión exitoso");
  setTimeout(() => {
    // admin-guard.js, en la página de destino, ya se encarga de mandar
    // a /admin si el rol activo resultó ser 'admin'.
    window.location.href = "/dashboard";
  }, 900);
}

// ============================================================
// "INGRESAR COMO…" — cuenta con más de un rol asignado
// ------------------------------------------------------------
// tokenLimitado solo sirve para canjearlo en POST /seleccionar-rol
// (ability 'seleccionar-rol'); nunca se guarda en sessionStorage.
// ============================================================
const ETIQUETAS_ROL = { cliente: "Cliente", admin: "Administrador" };

function inyectarEstilosSelectorRol() {
  if (document.getElementById("rmSelectorRolEstilos")) return;
  const style = document.createElement("style");
  style.id = "rmSelectorRolEstilos";
  style.textContent = `
    .rm-overlay { position: fixed; inset: 0; background: rgba(20,20,20,.5); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; font-family: 'Inter', sans-serif; }
    .rm-rol-modal { background:#fff; border-radius:16px; padding:28px; max-width:360px; width:100%; }
    .rm-rol-title { font-size:17px; font-weight:800; color:#1c1c1c; margin:0 0 6px; }
    .rm-rol-text { font-size:13.5px; color:#666; margin:0 0 18px; line-height:1.5; }
    .rm-rol-opciones { display:flex; flex-direction:column; gap:8px; }
    .rm-rol-btn { font-family:inherit; font-size:14px; font-weight:700; padding:13px 16px; border-radius:12px; border:1.5px solid #ececec; background:#fff; color:#333; cursor:pointer; text-align:left; }
    .rm-rol-btn:hover { border-color:#1a9ecf; color:#1a9ecf; }
    .rm-rol-error { font-size:12.5px; color:#c0261c; margin:12px 0 0; min-height:15px; }
  `;
  document.head.appendChild(style);
}

function mostrarSelectorRol(tokenLimitado, roles) {
  inyectarEstilosSelectorRol();

  const overlay = document.createElement("div");
  overlay.className = "rm-overlay";
  overlay.innerHTML = `
    <div class="rm-rol-modal">
      <p class="rm-rol-title">Ingresar como…</p>
      <p class="rm-rol-text">Tu cuenta tiene más de un rol. Elige con cuál quieres iniciar sesión esta vez.</p>
      <div class="rm-rol-opciones">
        ${roles.map((r) => `<button type="button" class="rm-rol-btn" data-rol="${r}">${ETIQUETAS_ROL[r] || r}</button>`).join("")}
      </div>
      <p class="rm-rol-error" id="rmSelectorRolError"></p>
    </div>`;
  document.body.appendChild(overlay);

  const errorEl = overlay.querySelector("#rmSelectorRolError");

  overlay.querySelectorAll("[data-rol]").forEach((btn) => {
    btn.addEventListener("click", async () => {
      btn.disabled = true;
      errorEl.textContent = "";

      try {
        const respuesta = await fetch(`${API_BASE}/seleccionar-rol`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${tokenLimitado}`,
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ rol: btn.dataset.rol }),
        });
        const datos = await respuesta.json().catch(() => ({}));

        if (!respuesta.ok) {
          errorEl.textContent = datos.mensaje || "No se pudo continuar con ese rol.";
          btn.disabled = false;
          return;
        }

        overlay.remove();
        guardarSesionYRedirigir(datos.token, datos.usuario, datos.rolActivo);
      } catch (e) {
        errorEl.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
        btn.disabled = false;
      }
    });
  });
}

// --- Mostrar / ocultar contraseña ---
togglePasswordBtn.addEventListener("click", () => {
  const esPassword = passwordInput.type === "password";
  passwordInput.type = esPassword ? "text" : "password";
  togglePasswordBtn.textContent = esPassword ? "Ocultar" : "Mostrar";
});

// --- Validación en vivo ---
emailInput.addEventListener("input", () => limpiarError(emailGroup, emailError));
passwordInput.addEventListener("input", () => limpiarError(passwordGroup, passwordError));

// --- Envío del formulario ---
form.addEventListener("submit", async (evento) => {
  evento.preventDefault();
  formMessage.textContent = "";
  formMessage.className = "form-message";

  const email = emailInput.value.trim();
  const password = passwordInput.value;

  let esValido = true;

  if (!email) {
    mostrarError(emailGroup, emailError, "Ingresa tu correo electrónico.");
    esValido = false;
  } else if (!esCorreoValido(email)) {
    mostrarError(emailGroup, emailError, "Ingresa un correo válido.");
    esValido = false;
  } else {
    limpiarError(emailGroup, emailError);
  }

  if (!password) {
    mostrarError(passwordGroup, passwordError, "Ingresa tu contraseña.");
    esValido = false;
  } else if (password.length < 6) {
    mostrarError(passwordGroup, passwordError, "Debe tener al menos 6 caracteres.");
    esValido = false;
  } else {
    limpiarError(passwordGroup, passwordError);
  }

  if (!esValido) return;

  setCargando(true);

  try {
    const respuesta = await fetch(`${API_BASE}/login`, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ email, password }),
    });

    const datos = await respuesta.json();
    setCargando(false);

    if (!respuesta.ok) {
      formMessage.classList.add("error");
      formMessage.textContent = datos.mensaje || "Datos incorrectos vuelva a intentar.";
      mostrarToast(datos.mensaje || "No se pudo iniciar sesión");
      return;
    }

    if (datos.requiereSeleccionRol) {
      mostrarSelectorRol(datos.token, datos.roles);
      return;
    }

    guardarSesionYRedirigir(datos.token, datos.usuario, datos.rolActivo);
  } catch (error) {
    setCargando(false);
    formMessage.classList.add("error");
    formMessage.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
    mostrarToast("Error de conexión");
  }
});

// --- Botones sociales (Google / Facebook) ---
// Navegación de página completa: OAuth necesita que el navegador
// vaya realmente a Google/Facebook. El flujo continúa en
// /auth/{proveedor}/callback -> auth/oauth-callback.blade.php, que
// guarda el token en sessionStorage y redirige a /onboarding o /dashboard.
socialButtons.forEach((boton) => {
  boton.addEventListener("click", () => {
    const proveedor = boton.dataset.provider;
    window.location.href = `/auth/${proveedor}/redirect`;
  });
});
