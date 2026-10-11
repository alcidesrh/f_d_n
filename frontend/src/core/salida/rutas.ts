/**
 * Buscar rutas por palabras sueltas (`guatem santa elena`) y agruparlas por
 * estación de salida (su origen). Sin mayúsculas ni acentos. Puro.
 */
import type { RutaOpcion } from './types'

export interface GrupoRutas<R> {
  origen: string
  rutas: R[]
}

/** Sin distinguir mayúsculas ni acentos. */
export const normalizar = (s: string | null | undefined) => (s ?? '').normalize('NFD').replace(/\p{M}/gu, '').toLowerCase()

export const palabras = (texto: string) => normalizar(texto).split(/\s+/).filter(Boolean)

type Buscable = Pick<RutaOpcion, 'nombre' | 'origen' | 'destino'>

/**
 * Cuánto coincide la ruta con las palabras: basta una en el origen, el
 * destino o el nombre. Cada palabra en el origen o el destino vale 2 y solo
 * en el nombre 1, así `guatem santa elena` pone primero Guatemala → Santa Elena.
 */
export function puntaje(r: Buscable, terminos: readonly string[]): number {
  const puntas = [normalizar(r.origen), normalizar(r.destino)]
  const nombre = normalizar(r.nombre)
  return terminos.reduce((p, t) => p + (puntas.some((c) => c.includes(t)) ? 2 : nombre.includes(t) ? 1 : 0), 0)
}

/**
 * Rutas que coinciden con alguna palabra, agrupadas por origen. Con búsqueda,
 * primero los grupos y las rutas que más coinciden; sin ella, todo por nombre.
 */
export function agruparRutas<R extends Buscable>(rutas: readonly R[], texto = ''): GrupoRutas<R>[] {
  const terminos = palabras(texto)
  const grupos = new Map<string, { origen: string; mejor: number; rutas: Array<{ r: R; p: number }> }>()
  for (const r of rutas) {
    const p = terminos.length ? puntaje(r, terminos) : 0
    if (terminos.length && !p) continue
    const origen = r.origen ?? 'Sin origen'
    const g = grupos.get(origen) ?? { origen, mejor: 0, rutas: [] }
    g.rutas.push({ r, p })
    g.mejor = Math.max(g.mejor, p)
    grupos.set(origen, g)
  }
  const porNombre = (a: string, b: string) => a.localeCompare(b, 'es', { numeric: true, sensitivity: 'base' })
  return [...grupos.values()]
    .sort((a, b) => b.mejor - a.mejor || porNombre(a.origen, b.origen))
    .map((g) => ({ origen: g.origen, rutas: g.rutas.sort((a, b) => b.p - a.p || porNombre(a.r.nombre, b.r.nombre)).map((x) => x.r) }))
}
