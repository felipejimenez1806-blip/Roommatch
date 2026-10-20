/**
 * core/convivencia.js
 *
 * Puente entre "Información convivencial" (perfil.js, guardada en
 * usuario.preferenciasConvivencia) y los wizards de crear perfil de roomie y de
 * crear publicación: lo que la persona ya contó allí llega prellenado al
 * wizard, así no lo escribe dos veces. Solo se copian los campos que significan
 * EXACTAMENTE lo mismo en ambos lados, y cada valor se revalida: si la
 * preferencia guardada es inválida (p. ej. datos viejos), se ignora en vez de
 * mandarla y provocar un 422 al publicar.
 *
 * El prellenado solo aplica al CREAR. Al editar, los wizards sobrescriben todo
 * con lo que ya está guardado en el perfil o en la publicación.
 */
import { LIMITES as L } from "./config.js";
import { validarHabitantes, validarPresupuesto } from "./validacion.js";

/** Opciones de "Ambiente" (mismos valores que acepta el servidor). */
export const OPCIONES_AMBIENTE = {
  estudiantes: "Estudiantes",
  profesionales: "Profesionales",
  otro: "Otro",
};

const enumDe = (valor, permitidos) => (permitidos.includes(valor) ? valor : "");
const bool = (valor) => valor === true;
const texto = (valor) => (typeof valor === "string" ? valor.trim().slice(0, L.texto_corto_max) : "");
const ambiente = (valor) => enumDe(valor, Object.keys(OPCIONES_AMBIENTE));

/** Campos del wizard de perfil de roomie que salen de "Mi estilo de vida". */
export function estadoRoomieDesdeConvivencia(prefs) {
  const p = prefs || {};
  return {
    fumador: bool(p.fumador),
    tiene_mascota: bool(p.tiene_mascota),
    ordenado: bool(p.ordenado),
    sociable: bool(p.sociable),
    madrugador: bool(p.madrugador),
    trasnochador: bool(p.trasnochador),
    quiere_amueblado: bool(p.quiere_amueblado),
    quiere_bano_privado: bool(p.quiere_bano_privado),
    cerca_universidad: bool(p.cerca_universidad),
    cerca_transporte_publico: bool(p.cerca_transporte_publico),
    ambiente_preferido: ambiente(p.ambiente_preferido),
    horario: enumDe(p.horario, ["diurno", "nocturno", "mixto"]),
    tiempo_busqueda: enumDe(p.tiempo_busqueda, ["corto", "largo"]),
    fecha_mudanza: enumDe(p.fecha_mudanza, ["inmediata", "1mes", "flexible"]),
    // "Presupuesto máximo mensual" -> presupuesto del perfil
    presupuesto: p.presupuesto_max != null && !validarPresupuesto(p.presupuesto_max) ? String(p.presupuesto_max) : "",
  };
}

/** Campos del wizard de publicación que salen de "Reglas de mi hogar". */
export function estadoPublicacionDesdeConvivencia(prefs) {
  const p = prefs || {};
  return {
    ambiente_hogar: ambiente(p.ambiente_hogar),
    numero_habitantes: p.numero_habitantes != null && !validarHabitantes(p.numero_habitantes) ? String(p.numero_habitantes) : "",
    horario_entrada: texto(p.horario_entrada),
    horario_silencio: texto(p.horario_silencio),
    permite_mascotas: bool(p.permite_mascotas),
    permite_fumar: bool(p.permite_fumar),
    permite_fiestas: bool(p.permite_fiestas),
    permite_visitas: bool(p.permite_visitas),
    permite_parejas: bool(p.permite_parejas),
  };
}
