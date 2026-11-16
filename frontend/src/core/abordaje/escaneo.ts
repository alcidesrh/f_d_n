/** Chequeo por escaneo (ADR-063): reglas puras de lo que lee la cámara o el lector. */
import type { BoletoAbordaje } from './types'

/** Formatos que se piden al detector: el QR del boleto y el código de barras de los impresos. */
export const FORMATOS = ['qr_code', 'code_128'] as const

/** Tiempo en que la misma lectura no se vuelve a enviar (la cámara ve el código muchas veces por segundo). */
export const PAUSA_REPETIDO_MS = 4000

export interface Lectura {
  codigo: string
  /** `Date.now()` de cuando se envió. */
  momento: number
}

/** Lo leído, sin espacios ni saltos (los lectores USB terminan en Enter). */
export const limpiar = (texto: string) => texto.replace(/\s+/g, '')

/** Si lo leído se envía: no vacío y no la misma lectura de hace un momento. */
export function debeEnviar(codigo: string, ultima: Lectura | null, ahora: number): boolean {
  if (!codigo) return false
  return ultima === null || ultima.codigo !== codigo || ahora - ultima.momento >= PAUSA_REPETIDO_MS
}

/** «Asiento 12 · JUAN PÉREZ» de cada boleto chequeado por una lectura. */
export const resumen = (boletos: BoletoAbordaje[]) =>
  boletos.map((b) => [`Asiento ${b.asiento}`, b.cliente].filter(Boolean).join(' · ')).join('\n')
