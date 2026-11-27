/**
 * Ayudas puras para escribir y transformar tokens de PrimeVue (los objetos
 * `primitive`/`semantic`/`components` de un preset), con tests.
 */

/**
 * Tokens de diseño de PrimeVue: objetos anidados libres que se combinan y
 * sobrescriben por clave.
 */
// eslint-disable-next-line @typescript-eslint/no-explicit-any
export type Tokens = Record<string, any>

export const esObjeto = (valor: unknown): valor is Tokens => typeof valor === 'object' && valor !== null && !Array.isArray(valor)

/**
 * Completa `modificaciones` con lo que `base` no sobrescribe: recursivo por
 * clave; donde ambos traen un objeto se combinan, en cualquier otro caso gana
 * la modificación. No muta ninguno de los dos.
 */
export function completarConBase(base: Tokens, modificaciones: Tokens): Tokens {
  const out: Tokens = { ...base }
  for (const [clave, valor] of Object.entries(modificaciones)) {
    const valorBase = base[clave]
    out[clave] = esObjeto(valorBase) && esObjeto(valor) ? completarConBase(valorBase, valor) : valor
  }
  return out
}

/**
 * Un color que sigue al parámetro «Contraste» (`--apariencia-contraste`: 0
 * normal, 1 alto): `normal` o `alto`, tokens de PrimeVue (`{surface.800}`) o
 * colores CSS. En oklch: con 0 da `normal` exacto.
 *
 * Sin espacios alrededor de `*`: PrimeVue envuelve en `calc()` todo un valor
 * con referencias que parezca una cuenta (`0 * 100`).
 */
export const contrastado = (normal: string, alto: string): string =>
  `color-mix(in oklch, ${alto} calc(var(--apariencia-contraste)*100%), ${normal})`

const PASOS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const

/**
 * Una referencia a un paso de rampa (`{surface.300}`) `pasos` más allá
 * (`{surface.500}`), hasta el 950. Lo que no sea una referencia así queda igual.
 */
export function masMarcado(valor: string, pasos = 2): string {
  const ref = /^\{(\w+)\.(\d+)\}$/.exec(valor)
  const i = ref ? PASOS.indexOf(Number(ref[2]) as (typeof PASOS)[number]) : -1
  if (!ref || i < 0) return valor
  return `{${ref[1]}.${PASOS[Math.min(i + pasos, PASOS.length - 1)]}}`
}

const esRampa = (valor: unknown): valor is Record<string, string> =>
  esObjeto(valor) && PASOS.every((paso) => typeof valor[paso] === 'string')

/** Invierte una rampa de color (50 ↔ 950, 100 ↔ 900…; el 500 se queda). */
export function invertirRampa(rampa: Record<string, string>): Record<string, string> {
  const out: Record<string, string> = {}
  PASOS.forEach((paso, i) => {
    out[String(paso)] = rampa[PASOS[PASOS.length - 1 - i]!]!
  })
  return out
}

/** Invierte cada rampa de una paleta (`{ red: {50…950}, … }`); lo demás queda igual. */
export function invertirPaleta<T>(paleta: T): T {
  const out: Record<string, unknown> = {}
  for (const [nombre, valor] of Object.entries(paleta as object)) out[nombre] = esRampa(valor) ? invertirRampa(valor) : valor
  return out as T
}

/** `paddingY` → `padding.y`, como nombra PrimeVue las referencias (`{form.field.padding.y}`). */
const comoReferencia = (clave: string) => clave.replace(/[A-Z]/g, (letra) => `.${letra.toLowerCase()}`).toLowerCase()

/**
 * El valor de una referencia de PrimeVue (`{form.field.padding.y}`) en
 * `primitive` o `semantic` de `preset`, siguiendo referencias encadenadas.
 * `undefined` si no está.
 */
export function resolverReferencia(preset: Tokens, valor: unknown, saltos = 8): unknown {
  if (typeof valor !== 'string' || saltos === 0) return valor
  const ref = /^\{([^}]+)\}$/.exec(valor.trim())
  if (!ref) return valor
  const partes = ref[1]!.split('.')
  for (const raiz of [preset.semantic, preset.primitive]) {
    const hallado = buscar(raiz, partes)
    if (hallado !== undefined) return resolverReferencia(preset, hallado, saltos - 1)
  }
  return undefined
}

function buscar(nodo: unknown, partes: string[]): unknown {
  if (partes.length === 0) return nodo
  if (!esObjeto(nodo)) return undefined
  for (const [clave, valor] of Object.entries(nodo)) {
    const nombre = comoReferencia(clave).split('.')
    if (nombre.every((p, i) => partes[i] === p)) {
      const hallado = buscar(valor, partes.slice(nombre.length))
      if (hallado !== undefined) return hallado
    }
  }
  return undefined
}
