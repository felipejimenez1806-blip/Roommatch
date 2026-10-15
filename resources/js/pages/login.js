/* =========================================================
   Roommatch - Login
   ---------------------------------------------------------
   Conectado a la API real de Laravel (POST /api/login). El
   servidor abre la sesión con una cookie HttpOnly: aquí no se
   guarda nada, solo se muestra el resultado y se navega.

   Roles N:N: /api/login puede devolver requiereSeleccionRol=true
   cuando la cuenta tiene más de un rol (cliente Y admin). En ese
   caso el login queda PENDIENTE en el servidor (todavía sin
   sesión) mientras se muestra la pantalla "Ingresar como…"; la
   sesión real se abre cuando el usuario elige (POST
   /api/seleccionar-rol). Lo mismo pasa tras un login social con
   más de un rol: el servidor vuelve a /login con el login
   pendiente y esta página muestra el selector al cargar.
   ========================================================= */

import { appUrl } from "../core/config.js";
import { iniciarSesion, seleccionarRol, obtenerRolesPendientes } from "../core/auth.js";

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

function sesionAbiertaYRedirigir() {
  formMessage.classList.add("success");
  formMessage.textContent = "¡Bienvenido a Roommatch! Redirigiendo...";
  mostrarToast("Inicio de sesión exitoso");
  setTimeout(() => {
    // El middleware zona.cliente del servidor manda a /admin si el rol
    // activo resultó ser 'admin', y a /onboarding si falta completar perfil.
    window.location.href = appUrl("/dashboard");
  }, 900);
}

// ============================================================
// "INGRESAR COMO…" — cuenta con más de un rol asignado
// ------------------------------------------------------------
// El login está pendiente en el servidor (sin sesión todavía); elegir
// el rol con POST /seleccionar-rol lo completa.
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

function mostrarSelectorRol(roles) {
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
        const { ok, datos } = await seleccionarRol(btn.dataset.rol);

        if (!ok) {
          errorEl.textContent = datos.mensaje || "No se pudo continuar con ese rol.";
          btn.disabled = false;
          return;
        }

        overlay.remove();
        sesionAbiertaYRedirigir();
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
    const { ok, datos } = await iniciarSesion(email, password);
    setCargando(false);

    if (!ok) {
      formMessage.classList.add("error");
      formMessage.textContent = datos.mensaje || "Datos incorrectos vuelva a intentar.";
      mostrarToast(datos.mensaje || "No se pudo iniciar sesión");
      return;
    }

    if (datos.requiereSeleccionRol) {
      mostrarSelectorRol(datos.roles);
      return;
    }

    sesionAbiertaYRedirigir();
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
// /auth/{proveedor}/callback (SocialAuthController), que abre la sesión
// en el servidor y redirige a /onboarding o /dashboard (o de vuelta aquí
// con el login pendiente si la cuenta tiene más de un rol).
socialButtons.forEach((boton) => {
  boton.addEventListener("click", () => {
    const proveedor = boton.dataset.provider;
    window.location.href = appUrl(`/auth/${proveedor}/redirect`);
  });
});

// Login social con más de un rol: el servidor ya dejó el login pendiente y
// volvió a esta página; se muestra el selector de inmediato.
const rolesPendientes = obtenerRolesPendientes();
if (rolesPendientes.length > 0) mostrarSelectorRol(rolesPendientes);
