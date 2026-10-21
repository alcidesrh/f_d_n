/**
 * Estaciones agrupadas por departamento, como en la compra de la página web:
 * primero el departamento del usuario (si tiene estación), luego Guatemala y
 * Petén, luego los demás por nombre y, sin departamento, `otros` al final.
 * En Guatemala y Petén, las primeras estaciones van antes que el resto.
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
/** Primeras estaciones de cada departamento, en orden (sin acentos; «melchor» vale por «Melchor de Mencos»). */
const PRIMERAS_ESTACIONES: Record<string, string[]> = {
  guatemala: ['aguilar batres', 'guatemala'],
  peten: ['santa elena', 'melchor', 'poptun'],
}
const clave = (d: string) => d.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase().trim()
const comparar = (a: string, b: string) => a.localeCompare(b, 'es', { sensitivity: 'base' })

/** Posición de la estación entre las primeras de su departamento; sin lugar, al final (`lista.length`). */
const posicionEstacion = (departamento: string, nombre: string) => {
  const lista = PRIMERAS_ESTACIONES[clave(departamento)] ?? []
  const n = clave(nombre)
  const i = lista.findIndex((p) => n === p || n.startsWith(`${p} `) || n.startsWith(`${p},`))
  return i < 0 ? lista.length : i
}

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
    .map(([d, lista]) => ({
      departamento: d || otros,
      estaciones: [...lista].sort((x, y) => posicionEstacion(d, x.nombre) - posicionEstacion(d, y.nombre) || comparar(x.nombre, y.nombre)),
    }))
}
