// ============================================================
// ROOMMATCH – vista puente de OAuth (auth/oauth-callback.blade.php)
// ------------------------------------------------------------
// El token, el usuario y el destino los entrega el propio Blade
// (única forma de pasarlos: son datos del servidor, no existen en un
// JS estático) a través de variables window.__oauth* fijadas en un
// script inline justo antes de este archivo. Aquí vive la lógica que
// no depende del servidor: guardarlos en sessionStorage y redirigir.
//
// CAMBIO (roles N:N, Fase 4): si la cuenta social tiene más de un rol
// (window.__oauthRequiereSeleccionRol === true), el token recibido es
// limitado (solo sirve para POST /api/seleccionar-rol) y todavía no
// se guarda — se muestra el mismo selector "Ingresar como…" que usa
// login.js, y solo al elegir se guarda la sesión real y se redirige.
// Con un solo rol, el comportamiento es el de siempre: se guarda todo
// de una vez y se redirige a window.__oauthRedirectTo.
// ============================================================

const API_BASE = "/api";
const STORAGE_TOKEN_KEY = "roommatch_token";
const STORAGE_USER_KEY = "roommatch_user";
const STORAGE_ROL_ACTIVO_KEY = "roommatch_rol_activo";

function guardarSesionYRedirigir(token, usuario, rolActivo, redirectTo) {
  sessionStorage.setItem(STORAGE_TOKEN_KEY, token);
  sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(usuario));
  sessionStorage.setItem(STORAGE_ROL_ACTIVO_KEY, rolActivo);
  window.location.href = redirectTo;
}

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

function mostrarSelectorRol(tokenLimitado, roles, redirectTo) {
  // La "caja" con el spinner de "Conectando tu cuenta..." ya no aplica
  // una vez hay que decidir el rol: se reemplaza por el selector.
  const caja = document.querySelector(".caja");
  if (caja) caja.remove();

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

        guardarSesionYRedirigir(datos.token, datos.usuario, datos.rolActivo, redirectTo);
      } catch (e) {
        errorEl.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
        btn.disabled = false;
      }
    });
  });
}

if (window.__oauthRequiereSeleccionRol) {
  mostrarSelectorRol(window.__oauthToken, window.__oauthRoles, window.__oauthRedirectTo);
} else {
  guardarSesionYRedirigir(window.__oauthToken, window.__oauthUser, window.__oauthRolActivo, window.__oauthRedirectTo);
}
