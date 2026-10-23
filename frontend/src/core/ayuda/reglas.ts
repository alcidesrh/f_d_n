/** Reglas puras del manual de uso: quién ve qué guía, búsqueda y ayuda por pantalla. */
import { tiene } from '@/core/acceso/reglas'
import type { MiAcceso } from '@/core/acceso/tipos'
import type { Guia, Tema } from './tipos'

/** Para todos, o el usuario encaja en alguna de sus audiencias. */
export function veGuia(guia: Guia, acceso: MiAcceso | null): boolean {
  return guia.para.length === 0 || guia.para.some((a) => tiene(acceso, a.exige))
}

/** Minúsculas y sin tildes: «contraseña» encuentra «contrasena». */
export function normalizar(texto: string): string {
  return texto.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
}

function textoDe(guia: Guia): string {
  return normalizar(
    [guia.titulo, guia.resumen, guia.antes, ...(guia.palabras ?? []), ...(guia.pasos ?? []), ...(guia.tabla ?? []).flatMap((f) => [f.antes, f.ahora])].filter(Boolean).join(' '),
  )
}

/** Cada palabra de la búsqueda aparece en la guía, en cualquier orden. */
export function coincide(guia: Guia, busqueda: string): boolean {
  const palabras = normalizar(busqueda).split(/\s+/).filter(Boolean)
  if (!palabras.length) return true
  const texto = textoDe(guia)
  return palabras.every((p) => texto.includes(p))
}

/** Claves de una ruta para la ayuda contextual: `nombre` y, en el CRUD genérico, `nombre:entidad`. */
export function clavesDeRuta(nombre: string | symbol | null | undefined, params: Record<string, unknown> = {}): string[] {
  if (typeof nombre !== 'string') return []
  const entidad = typeof params.entity === 'string' ? params.entity : null
  return entidad ? [`${nombre}:${entidad}`, nombre] : [nombre]
}

export function deLaPantalla(guia: Guia, claves: string[]): boolean {
  return (guia.pantallas ?? []).some((p) => claves.includes(p))
}

/** Los temas en su orden con sus guías; los vacíos no aparecen. */
export function agruparPorTema(temas: Tema[], guias: Guia[]): { tema: Tema; guias: Guia[] }[] {
  return temas.map((tema) => ({ tema, guias: guias.filter((g) => g.tema === tema.id) })).filter((g) => g.guias.length)
}
