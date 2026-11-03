/**
 * Reglas puras de accesos en el cliente (ADR-039). Solo deciden qué se
 * muestra: quien autoriza es siempre el backend. Los menús y la
 * administración ya llegan podados del backend (ADR-049).
 */
import type { Ambito, DefinicionCapacidad, Exige, MiAcceso, RecursoDeRuta } from './tipos'

/** Tiene la capacidad (o alguna de ellas) en algún ámbito. Sin `exige`, sí. */
export function tiene(acceso: MiAcceso | null, exige: Exige | undefined): boolean {
  if (exige === undefined) return true
  if (!acceso) return false
  const codigos = typeof exige === 'string' ? [exige] : exige
  return codigos.length === 0 || codigos.some((c) => (acceso.capacidades[c]?.length ?? 0) > 0)
}

/** `bus-clase` (parámetro de `/lista/:entity`) → `BusClase` (la entidad). */
export function recursoDeRuta(entidad: string): string {
  return entidad
    .split(/[-_]/)
    .filter(Boolean)
    .map((p) => p.charAt(0).toUpperCase() + p.slice(1))
    .join('')
}

export function puedeRecurso(acceso: MiAcceso | null, entidad: string, operacion: 'ver' | 'editar' | 'eliminar'): boolean {
  return Boolean(acceso?.recursos[recursoDeRuta(entidad)]?.[operacion])
}

interface RutaDestino {
  params?: Record<string, unknown>
  /** `RouteMeta`: lo que exige la pantalla (`capacidad`, `recurso`). */
  meta?: Record<PropertyKey, unknown>
}

/** La `capacidad` de `meta`, si es un código o una lista de códigos. */
function exigeDe(meta: Record<PropertyKey, unknown> | undefined): Exige | undefined {
  const c = meta?.capacidad
  return typeof c === 'string' || (Array.isArray(c) && c.every((x) => typeof x === 'string')) ? (c as Exige) : undefined
}

const parametro = (params: Record<string, unknown> | undefined, nombre: string | undefined): string =>
  nombre && typeof params?.[nombre] === 'string' ? (params[nombre] as string).trim() : ''

/**
 * Puede abrir la pantalla (ADR-049; la misma regla que `App\Navigation\Pantalla`
 * en el backend): alguna capacidad de `meta.capacidad` y, en una pantalla
 * del CRUD genérico (`meta.recurso`), lo que pide sobre su entidad; sin la
 * entidad, lo que pide el selector.
 */
export function puedeEntrar(acceso: MiAcceso | null, ruta: RutaDestino): boolean {
  if (!tiene(acceso, exigeDe(ruta.meta))) return false
  const recurso = ruta.meta?.recurso as RecursoDeRuta | undefined
  if (!recurso) return true
  const entidad = parametro(ruta.params, recurso.parametro)
  if (!entidad) return tiene(acceso, recurso.selector)
  return puedeRecurso(acceso, entidad, parametro(ruta.params, recurso.registro) ? 'ver' : recurso.operacion)
}

/**
 * Hay que pedirle la estación de trabajo: no tiene una válida, no es de
 * agencia, puede elegir y tiene algo que solo puede hacer en estaciones
 * concretas (con "todas" no hace falta).
 */
export function debeElegirEstacion(acceso: MiAcceso | null): boolean {
  if (!acceso || acceso.agencia || acceso.estacionTrabajo || acceso.estaciones.length === 0) return false
  return Object.values(acceso.capacidades).some((ambitos) => ambitos.length > 0 && ambitos.every((a) => a.estaciones !== null))
}

// ---- Administración ------------------------------------------------------

export interface GrupoCapacidades {
  area: string
  /** Del dominio: una casilla por capacidad. */
  dominio: DefinicionCapacidad[]
  /** Del CRUD genérico: una fila por entidad con ver, editar y eliminar. */
  recursos: Array<{ recurso: string; nombre: string; ver?: DefinicionCapacidad; editar?: DefinicionCapacidad; eliminar?: DefinicionCapacidad }>
}

/** El catálogo agrupado por área, en el orden en que llega (las del dominio primero). */
export function agruparCatalogo(capacidades: DefinicionCapacidad[]): GrupoCapacidades[] {
  const grupos = new Map<string, GrupoCapacidades>()
  for (const c of capacidades) {
    let g = grupos.get(c.area)
    if (!g) grupos.set(c.area, (g = { area: c.area, dominio: [], recursos: [] }))
    if (!c.recurso || !c.operacion) {
      g.dominio.push(c)
      continue
    }
    let fila = g.recursos.find((r) => r.recurso === c.recurso)
    if (!fila) g.recursos.push((fila = { recurso: c.recurso, nombre: c.nombre.replace(/: (ver|editar|eliminar)$/, '') }))
    fila[c.operacion] = c
  }
  return [...grupos.values()]
}

/** Texto corto de un ámbito: «Todas las empresas · Estación Cobán». */
export function describirAmbito(
  ambito: Pick<Ambito, 'empresas' | 'estaciones'> & { agencia?: number | string | null },
  nombres: { empresas: Map<number, string>; estaciones: Map<number, string>; agencia?: string | null },
): string {
  if (ambito.agencia) return `Agencia ${nombres.agencia ?? ambito.agencia}`
  const lista = (ids: number[] | null, todas: string, mapa: Map<number, string>, una: string) => {
    if (ids === null) return todas
    if (ids.length === 0) return `Ninguna ${una}`
    return ids.map((id) => mapa.get(id) ?? `#${id}`).join(', ')
  }
  return `${lista(ambito.empresas, 'Todas las empresas', nombres.empresas, 'empresa')} · ${lista(ambito.estaciones, 'todas las estaciones', nombres.estaciones, 'estación')}`
}

/** Las capacidades agregadas y quitadas entre dos listas (para avisar antes de guardar un rol). */
export function cambiosDeCapacidades(antes: string[], despues: string[]): { agregadas: string[]; quitadas: string[] } {
  const a = new Set(antes)
  const d = new Set(despues)
  return { agregadas: [...d].filter((c) => !a.has(c)), quitadas: [...a].filter((c) => !d.has(c)) }
}
