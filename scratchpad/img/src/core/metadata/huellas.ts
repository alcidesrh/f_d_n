/**
 * Huella de la metadata de cada entidad (`EntitySchema`, de la
 * introspección): dice qué entidades cambiaron entre dos introspecciones sin
 * guardar la anterior. El mismo contenido da la misma huella sin importar el
 * orden de las claves (ADR-036). Puro.
 */
import type { EntitySchema } from '@/core/graphql/types'

/** JSON con las claves de cada objeto ordenadas. */
function canonico(valor: unknown): string {
  if (Array.isArray(valor)) return `[${valor.map(canonico).join(',')}]`
  if (valor !== null && typeof valor === 'object') {
    const objeto = valor as Record<string, unknown>
    const pares = Object.keys(objeto)
      .filter((clave) => objeto[clave] !== undefined)
      .sort()
      .map((clave) => `${JSON.stringify(clave)}:${canonico(objeto[clave])}`)
    return `{${pares.join(',')}}`
  }
  return JSON.stringify(valor ?? null)
}

/** cyrb53: hash de 53 bits, de sobra para distinguir dos versiones de una entidad. */
function cyrb53(texto: string): string {
  let h1 = 0xdeadbeef
  let h2 = 0x41c6ce57
  for (let i = 0; i < texto.length; i++) {
    const c = texto.charCodeAt(i)
    h1 = Math.imul(h1 ^ c, 2654435761)
    h2 = Math.imul(h2 ^ c, 1597334677)
  }
  h1 = Math.imul(h1 ^ (h1 >>> 16), 2246822507) ^ Math.imul(h2 ^ (h2 >>> 13), 3266489909)
  h2 = Math.imul(h2 ^ (h2 >>> 16), 2246822507) ^ Math.imul(h1 ^ (h1 >>> 13), 3266489909)
  return (4294967296 * (2097151 & h2) + (h1 >>> 0)).toString(36)
}

export function huellaEntidad(entidad: EntitySchema): string {
  return cyrb53(canonico(entidad))
}

export function huellasDe(entidades: Record<string, EntitySchema>): Record<string, string> {
  return Object.fromEntries(Object.entries(entidades).map(([nombre, entidad]) => [nombre, huellaEntidad(entidad)]))
}

export interface DiferenciasEsquema {
  agregadas: string[]
  modificadas: string[]
  eliminadas: string[]
}

/** Qué entidades aparecieron, cambiaron o desaparecieron de `antes` a `despues`. */
export function diferenciasEsquema(antes: Record<string, string>, despues: Record<string, string>): DiferenciasEsquema {
  const agregadas: string[] = []
  const modificadas: string[] = []
  for (const [nombre, huella] of Object.entries(despues)) {
    if (!(nombre in antes)) agregadas.push(nombre)
    else if (antes[nombre] !== huella) modificadas.push(nombre)
  }
  const eliminadas = Object.keys(antes).filter((nombre) => !(nombre in despues))
  return { agregadas: agregadas.sort(), modificadas: modificadas.sort(), eliminadas: eliminadas.sort() }
}
