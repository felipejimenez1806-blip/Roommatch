// ============================================================
// ROOMMATCH – Barra de navegación según sesión activa
// ------------------------------------------------------------
// Misma responsabilidad que la versión original: pintar, dentro
// de <div class="nav-actions" id="navActions"></div>, el estado
// correcto según haya o no sesión.
//
// La sesión vive en sessionStorage (se borra sola al cerrar la
// pestaña/ventana) en tres claves:
//   - "roommatch_token":      el token Sanctum de la sesión actual
//   - "roommatch_user":       snapshot del usuario (paraFrontend()),
//                              incluye 'roles' (arreglo completo,
//                              ej. ["cliente"] o ["cliente","admin"])
//   - "roommatch_rol_activo": qué rol tiene fijado el token ACTUAL
//                              ('cliente' o 'admin') — depende del
//                              token, no del usuario, así que se
//                              guarda aparte (ver Fase 4).
// ============================================================

(function () {
  const STORAGE_TOKEN_KEY = "roommatch_token";
  const STORAGE_USER_KEY = "roommatch_user";
  const STORAGE_ROL_ACTIVO_KEY = "roommatch_rol_activo";

  function obtenerToken() {
    return sessionStorage.getItem(STORAGE_TOKEN_KEY);
  }

  function obtenerUsuarioGuardado() {
    return JSON.parse(sessionStorage.getItem(STORAGE_USER_KEY) || "null");
  }

  function obtenerRolActivo() {
    return sessionStorage.getItem(STORAGE_ROL_ACTIVO_KEY);
  }

  function guardarSesion(token, usuario, rolActivo) {
    sessionStorage.setItem(STORAGE_TOKEN_KEY, token);
    sessionStorage.setItem(STORAGE_USER_KEY, JSON.stringify(usuario));
    sessionStorage.setItem(STORAGE_ROL_ACTIVO_KEY, rolActivo);
  }

  function cerrarSesionLocal() {
    sessionStorage.removeItem(STORAGE_TOKEN_KEY);
    sessionStorage.removeItem(STORAGE_USER_KEY);
    sessionStorage.removeItem(STORAGE_ROL_ACTIVO_KEY);
  }

  function primerNombre(nombreCompleto) {
    return (nombreCompleto || "Usuario").trim().split(/\s+/)[0];
  }

  function inicialAvatar(nombreCompleto) {
    return (nombreCompleto || "U").trim().charAt(0).toUpperCase();
  }

  // ============================================================
  // PERFIL INCOMPLETO -> ONBOARDING
  // ------------------------------------------------------------
  // El servidor no puede hacer este chequeo en una carga de página
  // normal (no viaja el header Authorization en una navegación),
  // así que vive aquí, del lado del cliente, igual que el resto de
  // la sesión. Usa el snapshot ya guardado en sessionStorage —el
  // mismo que ya se usa para pintar el nombre/avatar— sin llamar a
  // la API de nuevo.
  //
  // CAMBIO (roles N:N): igual que Usuario::perfilCompleto() en el
  // backend, esto mira si el usuario TIENE el rol admin asignado
  // (usuario.roles), no si está activo como admin ahora mismo — un
  // admin nunca debería tener que pasar por onboarding, ni siquiera
  // navegando como cliente.
  // ============================================================
  function perfilCompleto(usuario) {
    if (usuario && (usuario.roles || []).includes("admin")) return true;
    return Boolean(usuario && usuario.telefono);
  }

  function redirigirSiPerfilIncompleto() {
    const token = obtenerToken();
    const usuario = obtenerUsuarioGuardado();

    if (!token || !usuario) return; // sin sesión, no aplica aquí
    if (perfilCompleto(usuario)) return;
    if (window.location.pathname === "/onboarding") return; // evita loop

    window.location.href = "/onboarding";
  }

  function inyectarEstilosSesion() {
    if (document.getElementById("rmSesionEstilos")) return;
    const style = document.createElement("style");
    style.id = "rmSesionEstilos";
    style.textContent = `
      .rm-overlay { position: fixed; inset: 0; background: rgba(20,20,20,.5); display:flex; align-items:center; justify-content:center; z-index:1000; padding:20px; font-family: 'Inter', sans-serif; }
      .rm-logout-box { background:#fff; border-radius:16px; padding:32px 40px; display:flex; flex-direction:column; align-items:center; gap:14px; }
      .rm-logout-spinner { width:30px; height:30px; border:3px solid #ececec; border-top-color:#1a9ecf; border-radius:50%; animation: rm-spin .7s linear infinite; }
      @keyframes rm-spin { to { transform: rotate(360deg); } }
      .rm-logout-text { font-size:14px; color:#555; font-weight:600; }
      .rm-inactividad-modal { background:#fff; border-radius:16px; padding:28px; max-width:360px; width:100%; text-align:center; }
      .rm-inactividad-title { font-size:17px; font-weight:800; color:#1c1c1c; margin:0 0 6px; }
      .rm-inactividad-text { font-size:13.5px; color:#666; margin:0 0 20px; line-height:1.5; }
      .rm-inactividad-btn { font-family:inherit; font-size:13.5px; font-weight:700; padding:10px 24px; border-radius:24px; border:none; background:#1a9ecf; color:#fff; cursor:pointer; }
      .rm-inactividad-btn:hover { background:#1585b0; }
      .rm-rol-modal { background:#fff; border-radius:16px; padding:28px; max-width:360px; width:100%; }
      .rm-rol-title { font-size:17px; font-weight:800; color:#1c1c1c; margin:0 0 6px; }
      .rm-rol-text { font-size:13.5px; color:#666; margin:0 0 18px; line-height:1.5; }
      .rm-rol-opciones { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
      .rm-rol-btn { font-family:inherit; font-size:13.5px; font-weight:700; padding:11px 16px; border-radius:12px; border:1.5px solid #ececec; background:#fff; color:#333; cursor:pointer; text-align:left; }
      .rm-rol-btn:hover { border-color:#1a9ecf; color:#1a9ecf; }
      .rm-rol-btn.is-active { border-color:#1a9ecf; background:#eaf6fc; color:#1a9ecf; cursor:default; }
      .rm-rol-password { width:100%; box-sizing:border-box; font-family:inherit; font-size:13.5px; padding:10px 12px; border:1.5px solid #ececec; border-radius:10px; margin-bottom:8px; }
      .rm-rol-error { font-size:12.5px; color:#c0261c; margin:0 0 10px; min-height:15px; }
      .rm-rol-cerrar { font-family:inherit; font-size:13px; font-weight:700; padding:9px 16px; border-radius:20px; border:1.5px solid #ececec; background:#fff; color:#555; cursor:pointer; width:100%; }
      .rm-rol-cerrar:hover { background:#f2f2f2; }
      .rm-rol-buttons { display:flex; gap:8px; }
      .rm-rol-buttons .rm-rol-cerrar { width:auto; flex:1; }
      .rm-rol-buttons .rm-inactividad-btn { flex:1; padding:9px 16px; }
    `;
    document.head.appendChild(style);
  }

  function mostrarOverlayCerrandoSesion() {
    inyectarEstilosSesion();
    const overlay = document.createElement("div");
    overlay.className = "rm-overlay";
    overlay.innerHTML = `
      <div class="rm-logout-box">
        <div class="rm-logout-spinner"></div>
        <p class="rm-logout-text">Cerrando sesión...</p>
      </div>`;
    document.body.appendChild(overlay);
  }

  async function cerrarSesion() {
    limpiarTimersInactividad();
    mostrarOverlayCerrandoSesion();

    const token = obtenerToken();
    try {
      if (token) {
        await fetch(`${API_BASE}/logout`, {
          method: "POST",
          headers: { Authorization: `Bearer ${token}`, Accept: "application/json" },
        });
      }
    } catch (e) {
      // Si falla la llamada (sin conexión, token ya vencido, etc.) igual
      // limpiamos la sesión local: el usuario quiere salir de todas formas.
    }

    // Pequeña pausa deliberada para que "Cerrando sesión..." no se sienta
    // instantáneo/brusco, incluso si la llamada anterior fue muy rápida.
    await new Promise((resolve) => setTimeout(resolve, 900));

    cerrarSesionLocal();
    window.location.href = "/";
  }

  // ============================================================
  // CAMBIAR DE ROL
  // ------------------------------------------------------------
  // Solo aparece cuando el usuario tiene más de un rol asignado
  // (usuario.roles.length > 1). Pide contraseña únicamente cuando el
  // destino es 'admin' (mismo criterio que el backend, ver
  // CambiarRolRequest). Al terminar, reemplaza el token/rolActivo
  // guardados y navega a la zona correspondiente — admin-guard.js en
  // la página de destino ya se encarga de que quede en el lugar
  // correcto.
  // ============================================================
  const ETIQUETAS_ROL = { cliente: "Cliente", admin: "Administrador" };

  function abrirModalCambiarRol(usuario, rolActivo) {
    inyectarEstilosSesion();
    const roles = usuario.roles || [];

    const overlay = document.createElement("div");
    overlay.className = "rm-overlay";
    overlay.innerHTML = `
      <div class="rm-rol-modal">
        <p class="rm-rol-title">Cambiar de rol</p>
        <p class="rm-rol-text">Ingresaste como ${ETIQUETAS_ROL[rolActivo] || rolActivo}. Elige con qué rol quieres continuar.</p>
        <div class="rm-rol-opciones">
          ${roles.map((r) => `
            <button type="button" class="rm-rol-btn${r === rolActivo ? " is-active" : ""}" data-rol="${r}" ${r === rolActivo ? "disabled" : ""}>
              ${ETIQUETAS_ROL[r] || r}${r === rolActivo ? " (actual)" : ""}
            </button>`).join("")}
        </div>
        <div id="rmRolPasswordWrap" hidden>
          <input type="password" class="rm-rol-password" id="rmRolPassword" placeholder="Tu contraseña actual" autocomplete="off"/>
        </div>
        <p class="rm-rol-error" id="rmRolError"></p>
        <button type="button" class="rm-rol-cerrar" id="rmRolCerrar">Cancelar</button>
      </div>`;
    document.body.appendChild(overlay);

    const cerrar = () => overlay.remove();
    overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
    overlay.querySelector("#rmRolCerrar").addEventListener("click", cerrar);

    const passwordWrap = overlay.querySelector("#rmRolPasswordWrap");
    const passwordInput = overlay.querySelector("#rmRolPassword");
    const errorEl = overlay.querySelector("#rmRolError");

    let rolElegido = null;

    overlay.querySelectorAll("[data-rol]").forEach((btn) => {
      btn.addEventListener("click", async () => {
        rolElegido = btn.dataset.rol;
        errorEl.textContent = "";

        // Pasar a admin exige contraseña; si el input todavía no está
        // visible, se muestra y se espera a que el usuario la escriba
        // en vez de mandar la petición de una vez.
        if (rolElegido === "admin" && passwordWrap.hidden) {
          passwordWrap.hidden = false;
          passwordInput.focus();
          return;
        }

        await confirmarCambioRol(rolElegido, passwordInput.value);
      });
    });

    async function confirmarCambioRol(rol, password) {
      const body = rol === "admin" ? { rol, password } : { rol };
      const respuesta = await fetch(`${API_BASE}/cambiar-rol`, {
        method: "POST",
        headers: {
          Authorization: `Bearer ${obtenerToken()}`,
          "Content-Type": "application/json",
          Accept: "application/json",
        },
        body: JSON.stringify(body),
      });
      const datos = await respuesta.json().catch(() => ({}));

      if (!respuesta.ok) {
        errorEl.textContent = datos.mensaje || "No se pudo cambiar de rol.";
        return;
      }

      guardarSesion(datos.token, datos.usuario, datos.rolActivo);
      cerrar();
      window.location.href = datos.rolActivo === "admin" ? "/admin" : "/dashboard";
    }

    // Si ya se mostró el campo de contraseña, Enter también confirma.
    passwordInput.addEventListener("keydown", (e) => {
      if (e.key === "Enter" && rolElegido) {
        e.preventDefault();
        confirmarCambioRol(rolElegido, passwordInput.value);
      }
    });
  }

  // ============================================================
  // "USAR TAMBIÉN COMO CLIENTE"
  // ------------------------------------------------------------
  // Solo aparece cuando el usuario tiene 'admin' pero NO 'cliente'
  // (típicamente una cuenta creada desde "Crear administrador", que
  // nace solo con el rol admin). Agregarse el rol cliente no es una
  // elevación de privilegios, así que no pide contraseña — a
  // diferencia de "Cambiar de rol" hacia admin. Tras agregarlo,
  // encadena automáticamente un cambio de rol hacia 'cliente' (ese sí
  // ya lo permite el backend sin contraseña) para dejarlo navegando
  // como cliente de una vez, sin un segundo clic.
  // ============================================================
  function abrirModalUsarComoCliente() {
    inyectarEstilosSesion();

    const overlay = document.createElement("div");
    overlay.className = "rm-overlay";
    overlay.innerHTML = `
      <div class="rm-rol-modal">
        <p class="rm-rol-title">Usar también como cliente</p>
        <p class="rm-rol-text">Tu cuenta es de administrador. Puedes agregarle también el rol de cliente para usar Roommatch como cualquier usuario — no perderás tu acceso de administrador.</p>
        <p class="rm-rol-error" id="rmClienteError"></p>
        <div class="rm-rol-buttons">
          <button type="button" class="rm-rol-cerrar" id="rmClienteCancelar">Cancelar</button>
          <button type="button" class="rm-inactividad-btn" id="rmClienteConfirmar">Sí, agregar</button>
        </div>
      </div>`;
    document.body.appendChild(overlay);

    const cerrar = () => overlay.remove();
    overlay.addEventListener("click", (e) => { if (e.target === overlay) cerrar(); });
    overlay.querySelector("#rmClienteCancelar").addEventListener("click", cerrar);

    const errorEl = overlay.querySelector("#rmClienteError");
    const confirmarBtn = overlay.querySelector("#rmClienteConfirmar");

    confirmarBtn.addEventListener("click", async () => {
      confirmarBtn.disabled = true;
      errorEl.textContent = "";

      try {
        const respuestaRol = await fetch(`${API_BASE}/agregar-rol-cliente`, {
          method: "POST",
          headers: { Authorization: `Bearer ${obtenerToken()}`, Accept: "application/json" },
        });
        const datosRol = await respuestaRol.json().catch(() => ({}));

        if (!respuestaRol.ok) {
          errorEl.textContent = datosRol.mensaje || "No se pudo agregar el rol de cliente.";
          confirmarBtn.disabled = false;
          return;
        }

        // Ya tiene el rol cliente asignado; ahora se canjea el token
        // actual (rol:admin) por uno con rol:cliente para dejarlo
        // navegando como cliente de inmediato.
        const respuestaCambio = await fetch(`${API_BASE}/cambiar-rol`, {
          method: "POST",
          headers: {
            Authorization: `Bearer ${obtenerToken()}`,
            "Content-Type": "application/json",
            Accept: "application/json",
          },
          body: JSON.stringify({ rol: "cliente" }),
        });
        const datosCambio = await respuestaCambio.json().catch(() => ({}));

        if (!respuestaCambio.ok) {
          // El rol ya quedó agregado en BD aunque esto falle; el usuario
          // puede entrar como cliente después con "Cambiar de rol".
          errorEl.textContent = datosCambio.mensaje || "Se agregó el rol, pero no se pudo cambiar automáticamente. Usa \"Cambiar de rol\".";
          confirmarBtn.disabled = false;
          return;
        }

        guardarSesion(datosCambio.token, datosCambio.usuario, datosCambio.rolActivo);
        cerrar();
        window.location.href = "/dashboard";
      } catch (e) {
        errorEl.textContent = "No se pudo conectar con el servidor. Intenta de nuevo.";
        confirmarBtn.disabled = false;
      }
    });
  }

  function renderNavbar() {
    const navActions = document.getElementById("navActions");
    if (!navActions) return; // La página no tiene navbar dinámico (p.ej. login/signup)

    const token = obtenerToken();
    const usuario = obtenerUsuarioGuardado();

    // ---------- SIN SESIÓN ----------
    if (!token || !usuario) {
      navActions.innerHTML = `
        <a class="btn-outline" href="/login">Iniciar sesión</a>
        <a class="btn-solid" href="/signup">Registrarse</a>`;
      return;
    }

    // ---------- CON SESIÓN ----------
    const avatarInterior = usuario.fotoPerfil
      ? `<img src="${usuario.fotoPerfil}" alt="${primerNombre(usuario.nombre)}"/>`
      : inicialAvatar(usuario.nombre);

    const roles = usuario.roles || [];

    // "Cambiar de rol" solo tiene sentido si el usuario tiene más de un
    // rol asignado. Es un <button>, no un <a href="/admin">: cambiar
    // de rol requiere un token nuevo con la ability correcta antes de
    // poder entrar a /admin (ver abrirModalCambiarRol).
    const tieneVariosRoles = roles.length > 1;
    const botonCambiarRol = tieneVariosRoles
      ? `<button type="button" class="btn-outline" id="navCambiarRolBtn" title="Cambiar de rol">Cambiar de rol</button>`
      : "";

    // Admin sin rol cliente todavía: ofrece agregárselo (autoservicio,
    // sin contraseña — ver abrirModalUsarComoCliente).
    const esSoloAdmin = roles.includes("admin") && !roles.includes("cliente");
    const botonUsarComoCliente = esSoloAdmin
      ? `<button type="button" class="btn-outline" id="navUsarClienteBtn" title="Usar también como cliente">Usar también como cliente</button>`
      : "";

    navActions.innerHTML = `
      ${botonCambiarRol}
      ${botonUsarComoCliente}
      <a class="nav-bell" id="navBell" href="/perfil#panelNotificaciones" title="Notificaciones">
        <svg class="nav-bell-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/>
          <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
        </svg>
        <span class="nav-bell-badge" id="navBellBadge" hidden>0</span>
      </a>
      <a class="user-menu-trigger" id="userMenuTrigger" href="/perfil" title="Mi perfil">
        <span class="user-avatar" id="userAvatar">${avatarInterior}</span>
        <span class="user-name" id="userName">${primerNombre(usuario.nombre)}</span>
      </a>`;

    if (tieneVariosRoles) {
      document.getElementById("navCambiarRolBtn").addEventListener("click", () => {
        abrirModalCambiarRol(usuario, obtenerRolActivo());
      });
    }
    if (esSoloAdmin) {
      document.getElementById("navUsarClienteBtn").addEventListener("click", abrirModalUsarComoCliente);
    }

    actualizarBadgeNotificaciones();
  }

  async function actualizarBadgeNotificaciones() {
    const badge = document.getElementById("navBellBadge");
    if (!badge) return;
    const tok = obtenerToken();
    if (!tok) return;
    try {
      const respuesta = await fetch(`${API_BASE}/notificaciones`, {
        headers: { Authorization: `Bearer ${tok}`, Accept: "application/json" },
      });
      if (!respuesta.ok) return;
      const datos = await respuesta.json();
      const noLeidas = (datos.notificaciones || []).filter((n) => !n.leida).length;
      badge.hidden = noLeidas === 0;
      badge.textContent = noLeidas > 9 ? "9+" : noLeidas;
    } catch (e) {
      // Silencioso: el badge simplemente no se actualiza si falla la red.
    }
  }

  // ============================================================
  // LINK "POPULAR" — apunta a #popularesSection, pero esa sección
  // solo existe en index/dashboard. Si estamos en Habitaciones o
  // Roomies (donde no existe), el clic no hacía nada porque el
  // navegador buscaba el ancla EN LA PÁGINA ACTUAL. Se reescribe el
  // href para que primero navegue a la página correcta y luego
  // baje al ancla (el navegador hace scroll automático al fragmento
  // en la carga si el id existe ahí).
  // ============================================================
  function ajustarLinkPopular() {
    const link = document.getElementById("navPopularLink");
    if (!link) return;

    // Si la página actual ya tiene la sección, se deja el ancla tal
    // cual (comportamiento ya funcional en index/dashboard).
    if (document.getElementById("popularesSection")) return;

    const destino = obtenerToken() ? "/dashboard" : "/";
    link.setAttribute("href", `${destino}#popularesSection`);
  }

  // ============================================================
  // MENÚ MÓVIL (hamburguesa) — sin cambios respecto al original
  // ============================================================
  function inicializarMenuMovil() {
    const nav = document.querySelector("nav");
    if (!nav || nav.querySelector(".nav-burger")) return;

    const burger = document.createElement("button");
    burger.type = "button";
    burger.className = "nav-burger";
    burger.setAttribute("aria-label", "Abrir menú de navegación");
    burger.setAttribute("aria-expanded", "false");
    burger.innerHTML = "<span></span><span></span><span></span>";
    nav.appendChild(burger);

    const cerrarMenu = () => {
      nav.classList.remove("nav-open");
      burger.setAttribute("aria-expanded", "false");
    };

    burger.addEventListener("click", () => {
      const abierto = nav.classList.toggle("nav-open");
      burger.setAttribute("aria-expanded", abierto ? "true" : "false");
    });

    nav.addEventListener("click", (e) => {
      if (e.target.closest("a") && nav.classList.contains("nav-open")) cerrarMenu();
    });

    const mqEscritorio = window.matchMedia("(min-width: 861px)");
    mqEscritorio.addEventListener("change", (e) => { if (e.matches) cerrarMenu(); });
  }

  // ============================================================
  // AUTO-LOGOUT POR INACTIVIDAD (15 min, con aviso 1 min antes)
  // ------------------------------------------------------------
  // Vive en nav.js porque se carga en TODAS las páginas autenticadas
  // (dashboard, perfil, mis-publicaciones, admin, etc.), así que
  // aplica igual sin importar el rol del usuario. Como el sitio es
  // multi-página (no una SPA), el timer se reinicia en cada carga de
  // página — y cada navegación interna ya cuenta como "actividad".
  // ============================================================
  const INACTIVIDAD_LIMITE_MS = 15 * 60 * 1000;
  const INACTIVIDAD_AVISO_MS = 60 * 1000; // mostrar el aviso 1 min antes del límite
  const EVENTOS_ACTIVIDAD = ["mousedown", "mousemove", "keydown", "scroll", "touchstart"];

  let inactividadTimer = null;
  let avisoTimer = null;
  let avisoOverlay = null;

  function limpiarTimersInactividad() {
    clearTimeout(inactividadTimer);
    clearTimeout(avisoTimer);
  }

  function cerrarAvisoInactividad() {
    if (avisoOverlay) {
      avisoOverlay.remove();
      avisoOverlay = null;
    }
  }

  function mostrarAvisoInactividad() {
    if (avisoOverlay) return; // ya se está mostrando
    inyectarEstilosSesion();
    avisoOverlay = document.createElement("div");
    avisoOverlay.className = "rm-overlay";
    avisoOverlay.innerHTML = `
      <div class="rm-inactividad-modal">
        <p class="rm-inactividad-title">¿Sigues ahí?</p>
        <p class="rm-inactividad-text">Tu sesión está por cerrarse por inactividad en 1 minuto.</p>
        <button type="button" class="rm-inactividad-btn" id="rmSeguirActivoBtn">Seguir conectado</button>
      </div>`;
    document.body.appendChild(avisoOverlay);
    document.getElementById("rmSeguirActivoBtn").addEventListener("click", () => {
      cerrarAvisoInactividad();
      reiniciarTimerInactividad();
    });

    // El límite final sigue corriendo en paralelo al aviso: si no hay
    // respuesta, se cierra sesión igual al cumplirse el tiempo total.
    inactividadTimer = setTimeout(() => {
      cerrarAvisoInactividad();
      cerrarSesion();
    }, INACTIVIDAD_AVISO_MS);
  }

  function reiniciarTimerInactividad() {
    if (!obtenerToken()) return; // sin sesión activa no hay nada que cerrar
    limpiarTimersInactividad();
    avisoTimer = setTimeout(mostrarAvisoInactividad, INACTIVIDAD_LIMITE_MS - INACTIVIDAD_AVISO_MS);
  }

  function inicializarDetectorInactividad() {
    if (!obtenerToken()) return; // páginas públicas (index sin sesión, login, signup) no necesitan esto

    EVENTOS_ACTIVIDAD.forEach((evento) => {
      document.addEventListener(evento, () => {
        // Mientras el aviso está visible, cualquier movimiento del mouse
        // NO lo descarta solo — el usuario debe confirmar con el botón,
        // para no reiniciar el timer sin querer con el cursor de paso.
        if (avisoOverlay) return;
        reiniciarTimerInactividad();
      }, { passive: true });
    });

    reiniciarTimerInactividad();
  }

  redirigirSiPerfilIncompleto();
  inicializarMenuMovil();
  ajustarLinkPopular();
  renderNavbar();
  inicializarDetectorInactividad();

  window.RoommatchNav = { renderNavbar, cerrarSesion, actualizarBadgeNotificaciones, guardarSesion, abrirModalUsarComoCliente };
})();
