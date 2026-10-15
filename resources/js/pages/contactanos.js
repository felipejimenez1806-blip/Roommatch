import { API_BASE } from "../core/config.js";
import { cabecerasAutenticadas } from "../core/auth.js";

// El formulario es público, así que la sesión es OPCIONAL: si hay sesión,
// cabecerasAutenticadas() la incluye para que el backend guarde quién escribe
// (mensajes_contacto.id_usuario); si no hay, el mensaje se guarda como de
// visitante (NULL).

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('contactForm');
  const submitBtn = document.getElementById('contactSubmit');
  const successBox = document.getElementById('contactSuccess');
  const generalErrorBox = document.getElementById('contactGeneralError');

  if (!form) return;

  const campos = {
    contactNombre: (v) => v.trim().length >= 2 || 'Escribe tu nombre.',
    contactCorreo: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()) || 'Escribe un correo válido.',
    contactAsunto: (v) => v !== '' || 'Selecciona un asunto.',
    contactMensaje: (v) => v.trim().length >= 10 || 'Cuéntanos un poco más (mínimo 10 caracteres).',
  };

  function mostrarError(idCampo, mensaje) {
    const input = document.getElementById(idCampo);
    const error = form.querySelector(`.contact-error[data-for="${idCampo}"]`);
    if (mensaje === true) {
      input.classList.remove('invalid');
      if (error) error.textContent = '';
      return true;
    }
    input.classList.add('invalid');
    if (error) error.textContent = mensaje;
    return false;
  }

  function validarFormulario() {
    let valido = true;
    Object.entries(campos).forEach(([idCampo, validar]) => {
      const input = document.getElementById(idCampo);
      const resultado = validar(input.value);
      if (!mostrarError(idCampo, resultado)) valido = false;
    });
    return valido;
  }

  // Traduce los nombres de campo de Laravel (nombre, correo, asunto,
  // mensaje) a los ids reales del formulario.
  const idsPorCampo = {
    nombre: 'contactNombre',
    correo: 'contactCorreo',
    asunto: 'contactAsunto',
    mensaje: 'contactMensaje',
  };

  function mostrarErroresServidor(errores) {
    Object.entries(errores || {}).forEach(([campo, mensajes]) => {
      const idCampo = idsPorCampo[campo];
      if (idCampo) mostrarError(idCampo, mensajes[0]);
    });
  }

  Object.keys(campos).forEach((idCampo) => {
    const input = document.getElementById(idCampo);
    input.addEventListener('input', () => mostrarError(idCampo, campos[idCampo](input.value)));
    input.addEventListener('change', () => mostrarError(idCampo, campos[idCampo](input.value)));
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    successBox.classList.remove('show');
    generalErrorBox.classList.remove('show');

    if (!validarFormulario()) return;

    submitBtn.disabled = true;
    submitBtn.textContent = 'Enviando…';

    try {
      const headers = cabecerasAutenticadas();

      const respuesta = await fetch(`${API_BASE}/contacto`, {
        method: 'POST',
        headers,
        body: JSON.stringify({
          nombre: document.getElementById('contactNombre').value.trim(),
          correo: document.getElementById('contactCorreo').value.trim(),
          asunto: document.getElementById('contactAsunto').value,
          mensaje: document.getElementById('contactMensaje').value.trim(),
        }),
      });

      if (respuesta.status === 422) {
        const data = await respuesta.json();
        mostrarErroresServidor(data.errors);
        return;
      }

      if (!respuesta.ok) {
        throw new Error('Respuesta no exitosa del servidor');
      }

      successBox.classList.add('show');
      form.reset();
    } catch (error) {
      generalErrorBox.classList.add('show');
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Enviar mensaje';
    }
  });
});
