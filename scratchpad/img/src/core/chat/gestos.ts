/**
 * Reglas puras de los gestos táctiles del chat (teléfono): deslizar un
 * mensaje para responderlo, mantenerlo pulsado para sus acciones y deslizar
 * desde el borde izquierdo para volver a la bandeja. Las distancias en px,
 * los tiempos en ms.
 */

/** Movimiento a partir del cual el dedo ya eligió dirección (y deja de ser una pulsación). */
export const UMBRAL_MOVIMIENTO = 10
/** Lo que hay que deslizar un mensaje para responderlo. */
export const UMBRAL_RESPUESTA = 64
/** Lo más que se mueve el mensaje, aunque el dedo siga. */
export const MAX_DESPLAZAMIENTO = 88
/** Pulsación larga: lo que hay que mantener el dedo quieto. */
export const PULSACION_LARGA = 450
/** Ancho de la franja del borde izquierdo desde la que se vuelve a la bandeja. */
export const BORDE = 24

export type Eje = 'x' | 'y'

/** Dirección del gesto, o null mientras el dedo apenas se movió. */
export function ejeDe(dx: number, dy: number): Eje | null {
  if (Math.hypot(dx, dy) < UMBRAL_MOVIMIENTO) return null
  return Math.abs(dx) > Math.abs(dy) ? 'x' : 'y'
}

/**
 * Cuánto se mueve el mensaje con el dedo: solo hacia la derecha, 1:1 hasta
 * el umbral y con resistencia después, sin pasar del máximo.
 */
export function desplazamientoRespuesta(dx: number): number {
  if (dx <= 0) return 0
  if (dx <= UMBRAL_RESPUESTA) return dx
  return Math.min(MAX_DESPLAZAMIENTO, UMBRAL_RESPUESTA + (dx - UMBRAL_RESPUESTA) * 0.35)
}

export const respondeAl = (dx: number) => desplazamientoRespuesta(dx) >= UMBRAL_RESPUESTA

/** Al soltar desde el borde: vuelve si pasó un tercio del ancho o fue un golpe rápido hacia la derecha. */
export function vuelveAlSoltar(dx: number, ancho: number, msDesdeInicio: number): boolean {
  if (dx <= 0) return false
  const velocidad = dx / Math.max(1, msDesdeInicio)
  return dx > ancho / 3 || (dx > 40 && velocidad > 0.5)
}
