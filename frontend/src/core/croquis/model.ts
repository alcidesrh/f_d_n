/**
 * Reglas puras del croquis (espejo de `App\Croquis\Croquis` en el backend):
 * celdas, dimensiones de la rejilla, resumen y validación.
 */
import type {
  AsientoCroquis,
  ClaseAsiento,
  ElementoCroquis,
  Posicion,
  Rejilla,
  SenalCroquis,
} from './types'

export const PLANTAS_MAX = 2
export const FILAS_MAX = 30
export const COLUMNAS_MAX = 6
export const NUMERO_MAX = 999
/** Ancho del marco del legado: 4 columnas de asientos + el pasillo (columna 3). */
export const COLUMNAS_POR_DEFECTO = 5

export const esAsiento = (elemento: ElementoCroquis): elemento is AsientoCroquis =>
  elemento.tipo === 'asiento'

export const esSenal = (elemento: ElementoCroquis): elemento is SenalCroquis =>
  elemento.tipo !== 'asiento'

/** Clave de una celda dentro del bus: `planta:fila:columna`. */
export const celda = (p: Posicion) => `${p.planta}:${p.fila}:${p.columna}`

export const mismaCelda = (a: Posicion, b: Posicion) =>
  a.planta === b.planta && a.fila === b.fila && a.columna === b.columna

/** Elementos por celda. */
export function indexar<T extends Posicion>(elementos: readonly T[]): Map<string, T> {
  return new Map(elementos.map((e) => [celda(e), e]))
}

/** Plantas con algún elemento, en orden (al menos la 1). */
export function plantasDe(elementos: readonly ElementoCroquis[]): number[] {
  const plantas = new Set(elementos.map((e) => e.planta))
  plantas.add(1)
  return [...plantas].sort((a, b) => a - b)
}

/**
 * Rejilla de cada planta: tantas filas como la última ocupada (o `minimo`) y
 * el mismo ancho en todas las plantas (el del marco o el de la columna más
 * alejada).
 */
export function rejillas(
  elementos: readonly ElementoCroquis[],
  minimo: Partial<Rejilla> = {},
  plantas: readonly number[] = plantasDe(elementos),
): Record<number, Rejilla> {
  const columnas = Math.max(
    minimo.columnas ?? COLUMNAS_POR_DEFECTO,
    ...elementos.map((e) => e.columna),
  )
  return Object.fromEntries(
    plantas.map((planta) => [
      planta,
      {
        filas: Math.max(
          minimo.filas ?? 1,
          ...elementos.filter((e) => e.planta === planta).map((e) => e.fila),
        ),
        columnas,
      },
    ]),
  )
}

/**
 * Columnas de pasillo de una planta: las que no tienen asiento salvo, como
 * mucho, en la última fila (la fila trasera suele cruzar el pasillo).
 */
export function columnasPasillo(
  elementos: readonly ElementoCroquis[],
  planta: number,
  rejilla: Rejilla,
): number[] {
  const asientos = elementos.filter((e) => e.planta === planta && esAsiento(e))
  if (asientos.length === 0) return []
  const ultimaFila = Math.max(...asientos.map((a) => a.fila))
  const pasillo: number[] = []
  for (let columna = 1; columna <= rejilla.columnas; columna++) {
    const enColumna = asientos.filter((a) => a.columna === columna && a.fila !== ultimaFila)
    if (enColumna.length === 0) pasillo.push(columna)
  }
  // Una columna vacía en un borde es margen, no pasillo.
  return pasillo.filter((c) => c !== 1 && c !== rejilla.columnas)
}

export interface ResumenCroquis {
  asientos: number
  porClase: Record<ClaseAsiento, number>
  plantas: number
  puertas: number
  chofer: boolean
}

export function resumen(elementos: readonly ElementoCroquis[]): ResumenCroquis {
  const asientos = elementos.filter(esAsiento)
  return {
    asientos: asientos.length,
    porClase: {
      A: asientos.filter((a) => a.clase === 'A').length,
      B: asientos.filter((a) => a.clase === 'B').length,
    },
    plantas: plantasDe(elementos).length,
    puertas: elementos.filter((e) => e.tipo === 'puerta').length,
    chofer: elementos.some((e) => e.tipo === 'chofer'),
  }
}

/** Menor número de asiento libre (mantiene la numeración consecutiva). */
export function siguienteNumero(elementos: readonly ElementoCroquis[]): number {
  const usados = new Set(elementos.filter(esAsiento).map((a) => a.numero))
  let numero = 1
  while (usados.has(numero)) numero++
  return numero
}

/** Elementos en orden de lectura: planta, fila, columna. */
export function ordenar<T extends Posicion>(elementos: readonly T[]): T[] {
  return [...elementos].sort(
    (a, b) => a.planta - b.planta || a.fila - b.fila || a.columna - b.columna,
  )
}

export interface Problema {
  nivel: 'error' | 'aviso'
  mensaje: string
  /** Celdas implicadas (para resaltarlas). */
  celdas: string[]
}

/**
 * Errores (impiden guardar; el backend los rechaza igual) y avisos (reglas
 * del negocio que conviene respetar pero no bloquean, p. ej. numeración no
 * consecutiva en buses migrados).
 */
export function validar(elementos: readonly ElementoCroquis[]): Problema[] {
  const problemas: Problema[] = []
  const error = (mensaje: string, celdas: string[] = []) =>
    problemas.push({ nivel: 'error', mensaje, celdas })
  const aviso = (mensaje: string, celdas: string[] = []) =>
    problemas.push({ nivel: 'aviso', mensaje, celdas })

  for (const e of elementos) {
    if (e.planta < 1 || e.planta > PLANTAS_MAX)
      error(`Planta ${e.planta} fuera de rango.`, [celda(e)])
    if (e.fila < 1 || e.fila > FILAS_MAX)
      error(`Fila ${e.fila} fuera de rango (1–${FILAS_MAX}).`, [celda(e)])
    if (e.columna < 1 || e.columna > COLUMNAS_MAX)
      error(`Columna ${e.columna} fuera de rango (1–${COLUMNAS_MAX}).`, [celda(e)])
  }

  const porCelda = new Map<string, number>()
  for (const e of elementos) porCelda.set(celda(e), (porCelda.get(celda(e)) ?? 0) + 1)
  for (const [clave, n] of porCelda) {
    if (n > 1) error('Hay dos elementos en la misma celda.', [clave])
  }

  const asientos = elementos.filter(esAsiento)
  const porNumero = new Map<number, AsientoCroquis[]>()
  for (const a of asientos) porNumero.set(a.numero, [...(porNumero.get(a.numero) ?? []), a])
  for (const [numero, lista] of porNumero) {
    if (!Number.isInteger(numero) || numero < 1 || numero > NUMERO_MAX)
      error(`Número de asiento inválido: ${numero}.`, lista.map(celda))
    else if (lista.length > 1) error(`El asiento ${numero} está repetido.`, lista.map(celda))
  }

  const choferes = elementos.filter((e) => e.tipo === 'chofer')
  if (choferes.length > 1) error('El bus solo puede tener un chofer.', choferes.map(celda))

  if (asientos.length === 0) aviso('El bus no tiene asientos.')
  else {
    const numeros = [...porNumero.keys()].sort((a, b) => a - b)
    const faltantes = numeros.length > 0 && numeros[numeros.length - 1] !== numeros.length
    if (faltantes) aviso('La numeración de los asientos no es consecutiva desde 1.')
  }
  if (choferes.length === 0) aviso('Falta el chofer.')
  if (!elementos.some((e) => e.tipo === 'puerta')) aviso('Falta al menos una puerta.')

  return problemas
}

export const tieneErrores = (problemas: readonly Problema[]) =>
  problemas.some((p) => p.nivel === 'error')

/** El mismo croquis sin ids (plantilla o copia para otro bus). */
export function sinIds(elementos: readonly ElementoCroquis[]): ElementoCroquis[] {
  return elementos.map((e) =>
    esAsiento(e)
      ? {
          tipo: 'asiento',
          id: null,
          numero: e.numero,
          clase: e.clase,
          planta: e.planta,
          fila: e.fila,
          columna: e.columna,
        }
      : { tipo: e.tipo, id: null, planta: e.planta, fila: e.fila, columna: e.columna },
  )
}
