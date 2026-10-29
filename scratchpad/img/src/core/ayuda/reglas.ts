/** Reglas puras del manual de uso: quién ve qué guía, referencias entre guías, búsqueda y ayuda por pantalla. */
import { tiene } from '@/core/acceso/reglas'
import type { Exige, MiAcceso } from '@/core/acceso/tipos'
import type { Guia, Tema } from './tipos'

/** Para todos, o el usuario encaja en alguna de sus audiencias. */
export function veGuia(guia: Guia, acceso: MiAcceso | null): boolean {
  return guia.para.length === 0 || guia.para.some((a) => tiene(acceso, a.exige))
}

/** Minúsculas y sin tildes: «contraseña» encuentra «contrasena». */
export function normalizar(texto: string): string {
  return texto
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
}

/** `[texto](ayuda:id)` dentro de un párrafo: un enlace a otra guía. */
const REFERENCIA = /\[([^\]]+)\]\(ayuda:([a-z0-9-]+)\)/g

export interface Trozo {
  texto: string
  /** Id de la guía a la que enlaza el trozo, si es una referencia. */
  guia?: string
}

/** Parte un texto en trozos normales y referencias a otras guías. */
export function partirReferencias(texto: string): Trozo[] {
  const trozos: Trozo[] = []
  let desde = 0
  for (const m of texto.matchAll(REFERENCIA)) {
    const inicio = m.index ?? 0
    if (inicio > desde) trozos.push({ texto: texto.slice(desde, inicio) })
    trozos.push({ texto: m[1] ?? '', guia: m[2] })
    desde = inicio + m[0].length
  }
  if (desde < texto.length) trozos.push({ texto: texto.slice(desde) })
  return trozos
}

/** Todo el texto visible de una guía, en el orden en que se muestra. */
export function textosDe(guia: Guia): string[] {
  return [
    guia.titulo,
    guia.resumen,
    guia.antes,
    ...(guia.pasos ?? []),
    ...(guia.tabla ?? []).flatMap((f) => [f.antes, f.ahora]),
    ...(guia.ejemplo ? [guia.ejemplo.caso, ...guia.ejemplo.pasos] : []),
    ...(guia.ojo ?? []),
    guia.abrir?.etiqueta,
  ].filter((t): t is string => Boolean(t))
}

/** Ids de las guías a las que enlaza esta. */
export function referenciasDe(guia: Guia): string[] {
  return textosDe(guia).flatMap((t) =>
    partirReferencias(t).flatMap((trozo) => (trozo.guia ? [trozo.guia] : [])),
  )
}

/**
 * Quien ve `origen` ve también `destino`: así un enlace nunca lleva a una
 * guía que el usuario no puede abrir (ni revela que existe). Cada capacidad
 * que abre el origen debe abrir también el destino; «para todos» abre todo.
 */
export function veSiVeOrigen(origen: Guia, destino: Guia): boolean {
  if (destino.para.length === 0) return true
  if (origen.para.length === 0) return false
  return origen.para.every((a) =>
    codigosDe(a.exige).every((c) => destino.para.some((b) => codigosDe(b.exige).includes(c))),
  )
}

function codigosDe(exige: Exige): string[] {
  return typeof exige === 'string' ? [exige] : [...exige]
}

/** Minúsculas y sin tildes, sin las marcas de los enlaces: lo que el usuario lee. */
function textoDe(guia: Guia): string {
  const visible = [...textosDe(guia), ...(guia.palabras ?? [])].map((t) =>
    partirReferencias(t)
      .map((p) => p.texto)
      .join(''),
  )
  return normalizar(visible.join(' '))
}

/** Cada palabra de la búsqueda aparece en la guía, en cualquier orden. */
export function coincide(guia: Guia, busqueda: string): boolean {
  const palabras = normalizar(busqueda).split(/\s+/).filter(Boolean)
  if (!palabras.length) return true
  const texto = textoDe(guia)
  return palabras.every((p) => texto.includes(p))
}

/** Claves de una ruta para la ayuda contextual: `nombre` y, en el CRUD genérico, `nombre:entidad`. */
export function clavesDeRuta(
  nombre: string | symbol | null | undefined,
  params: Record<string, unknown> = {},
): string[] {
  if (typeof nombre !== 'string') return []
  const entidad = typeof params.entity === 'string' ? params.entity : null
  return entidad ? [`${nombre}:${entidad}`, nombre] : [nombre]
}

export function deLaPantalla(guia: Guia, claves: string[]): boolean {
  return (guia.pantallas ?? []).some((p) => claves.includes(p))
}

/** Los temas en su orden con sus guías; los vacíos no aparecen. */
export function agruparPorTema(temas: Tema[], guias: Guia[]): { tema: Tema; guias: Guia[] }[] {
  return temas
    .map((tema) => ({ tema, guias: guias.filter((g) => g.tema === tema.id) }))
    .filter((g) => g.guias.length)
}
