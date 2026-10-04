/**
 * Estaciones agrupadas por departamento, como en la compra de la página web:
 * primero el departamento del usuario (si tiene estación), luego Guatemala y
 * Petén, luego los demás por nombre y, sin departamento, `otros` al final.
 * Estaciones por nombre.
 */
export interface EstacionAgrupable {
  id: number
  nombre: string
  departamento?: string | null
}

export interface GrupoEstaciones<E extends EstacionAgrupable = EstacionAgrupable> {
  departamento: string
  estaciones: E[]
}

const PRIMEROS = ['guatemala', 'peten']
const clave = (d: string) => d.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim()
const comparar = (a: string, b: string) => a.localeCompare(b, 'es', { sensitivity: 'base' })

export function agruparPorDepartamento<E extends EstacionAgrupable>(estaciones: readonly E[], opciones: { propio?: string | null; otros?: string } = {}): GrupoEstaciones<E>[] {
  const otros = opciones.otros ?? 'Otros'
  const propio = opciones.propio ? clave(opciones.propio) : null
  const grupos = new Map<string, E[]>()
  for (const e of estaciones) {
    const d = e.departamento?.trim() || ''
    grupos.set(d, [...(grupos.get(d) ?? []), e])
  }
  const rango = (d: string) => {
    if (d === '') return Infinity
    if (propio && clave(d) === propio) return -1
    const i = PRIMEROS.indexOf(clave(d))
    return i < 0 ? PRIMEROS.length : i
  }
  return [...grupos.entries()]
    .sort(([a], [b]) => rango(a) - rango(b) || comparar(a, b))
    .map(([d, lista]) => ({ departamento: d || otros, estaciones: [...lista].sort((x, y) => comparar(x.nombre, y.nombre)) }))
}
