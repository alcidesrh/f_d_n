/** Reglas puras de la suplantación en el cliente (quien decide es el backend). */

/** Minutos que faltan para `expira` (redondeado hacia arriba, nunca negativo); null sin vencimiento. */
export function minutosRestantes(expira: string | null | undefined, ahora: number): number | null {
  if (!expira) return null
  const fin = Date.parse(expira)
  if (Number.isNaN(fin)) return null
  return Math.max(0, Math.ceil((fin - ahora) / 60_000))
}

/** «vence en 45 min», «vence en 1 h 5 min», «vencida». */
export function textoVencimiento(minutos: number | null): string {
  if (minutos === null) return ''
  if (minutos <= 0) return 'vencida'
  if (minutos < 60) return `vence en ${minutos} min`
  const h = Math.floor(minutos / 60)
  const m = minutos % 60
  return m ? `vence en ${h} h ${m} min` : `vence en ${h} h`
}

/** El motivo es suficiente (mismo mínimo que el backend). */
export const MOTIVO_MIN = 5
export function motivoValido(motivo: string, exigido: boolean): boolean {
  return !exigido || motivo.trim().replace(/\s+/g, ' ').length >= MOTIVO_MIN
}
