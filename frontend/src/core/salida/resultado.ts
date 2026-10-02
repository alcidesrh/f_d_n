/**
 * Mensajes del resultado de editar, anular o eliminar (con propagación):
 * qué se hizo y qué salidas quedaron sin tocar y por qué. Puro.
 */
import type { MotivoOmision, ResultadoOperacion } from './types'

export type Operacion = 'editar' | 'anular' | 'eliminar'

const PARTICIPIO: Record<Operacion, [string, string]> = {
  editar: ['actualizada', 'actualizadas'],
  anular: ['anulada', 'anuladas'],
  eliminar: ['eliminada', 'eliminadas'],
}

export const MOTIVOS: Record<MotivoOmision, string> = {
  asientos: 'Tiene asientos vendidos o apartados: reasígnelos o anúlelos en la venta y vuelva a intentarlo.',
  conflicto: 'El bus está en otro viaje a esa hora.',
  historial: 'Tiene boletos en su historial: no se borra, anúlela.',
}

/** Resumen de una línea para el toast. */
export function resumen(r: ResultadoOperacion, op: Operacion): { texto: string; severidad: 'success' | 'warning' | 'error' } {
  const n = r.aplicadas.length
  const omitidas = r.omitidas.length
  const [uno, varios] = PARTICIPIO[op]
  const hecho = n === 0 ? `Ninguna salida ${uno}` : n === 1 ? `1 salida ${uno}` : `${n} salidas ${varios}`
  if (!omitidas) return { texto: `${hecho}.`, severidad: 'success' }
  const texto = `${hecho}; ${omitidas === 1 ? '1 quedó' : `${omitidas} quedaron`} sin cambios.`
  return { texto, severidad: n === 0 ? 'error' : 'warning' }
}

/** Omitidas agrupadas por motivo, para listarlas al usuario. */
export function omitidasPorMotivo(r: ResultadoOperacion): Array<{ motivo: MotivoOmision; texto: string; salidas: ResultadoOperacion['omitidas'] }> {
  const grupos = new Map<MotivoOmision, ResultadoOperacion['omitidas']>()
  for (const o of r.omitidas) grupos.set(o.motivo, [...(grupos.get(o.motivo) ?? []), o])
  return [...grupos].map(([motivo, salidas]) => ({ motivo, texto: MOTIVOS[motivo] ?? motivo, salidas }))
}
