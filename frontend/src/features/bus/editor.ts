/**
 * Estado del editor de croquis y sus operaciones (puras: cada una devuelve un
 * estado nuevo, lo que da deshacer/rehacer gratis).
 *
 * El editor es la versión web del "lienzo" del legado: una rejilla por planta
 * de alto ajustable y cuatro pilas infinitas (chofer, puerta, asiento A,
 * asiento B) de las que se toma una pieza para soltarla en una celda.
 */
import {
  celda,
  COLUMNAS_MAX,
  COLUMNAS_POR_DEFECTO,
  esAsiento,
  FILAS_MAX,
  mismaCelda,
  ordenar,
  PLANTAS_MAX,
  siguienteNumero,
} from '@/core/croquis/model'
import type {
  AsientoCroquis,
  ClaseAsiento,
  ElementoCroquis,
  Posicion,
  TipoSenal,
} from '@/core/croquis/types'

/** Elemento con una clave local estable (los nuevos aún no tienen id). */
export type ElementoEditor = ElementoCroquis & { uid: string }

export interface EstadoEditor {
  elementos: ElementoEditor[]
  /** Filas de la rejilla de cada planta presente. */
  filas: Record<number, number>
  columnas: number
}

/** Pieza de una de las pilas de la paleta. */
export type Pieza = { tipo: 'asiento'; clase: ClaseAsiento } | { tipo: TipoSenal }

export const FILAS_BUS_NUEVO = 12

let secuencia = 0
const nuevoUid = () => `n${++secuencia}`

export function crearEstado(
  elementos: readonly ElementoCroquis[] = [],
  columnasMin = COLUMNAS_POR_DEFECTO,
): EstadoEditor {
  const conUid = elementos.map((e) => ({ ...e, uid: nuevoUid() }) as ElementoEditor)
  const filas: Record<number, number> = {}
  for (const e of conUid) filas[e.planta] = Math.max(filas[e.planta] ?? 1, e.fila)
  if (!filas[1])
    filas[1] = elementos.length ? Math.max(1, ...Object.values(filas)) : FILAS_BUS_NUEVO
  return {
    elementos: conUid,
    filas,
    columnas: Math.min(COLUMNAS_MAX, Math.max(columnasMin, ...conUid.map((e) => e.columna))),
  }
}

export const plantasEditor = (estado: EstadoEditor) =>
  Object.keys(estado.filas)
    .map(Number)
    .sort((a, b) => a - b)

export const elementoEn = (estado: EstadoEditor, p: Posicion) =>
  estado.elementos.find((e) => mismaCelda(e, p)) ?? null

export const buscar = (estado: EstadoEditor, uid: string | null) =>
  uid ? (estado.elementos.find((e) => e.uid === uid) ?? null) : null

export function dentroDeRejilla(estado: EstadoEditor, p: Posicion): boolean {
  const filas = estado.filas[p.planta]
  return (
    filas !== undefined &&
    p.fila >= 1 &&
    p.fila <= filas &&
    p.columna >= 1 &&
    p.columna <= estado.columnas
  )
}

/** Croquis para guardar (sin las claves locales). */
export function aCroquis(estado: EstadoEditor): ElementoCroquis[] {
  return ordenar(estado.elementos).map(({ uid: _uid, ...e }) => e as ElementoCroquis)
}

const conElementos = (estado: EstadoEditor, elementos: ElementoEditor[]): EstadoEditor => ({
  ...estado,
  elementos,
})

// Piezas -----------------------------------------------------------------------

export interface Colocacion {
  estado: EstadoEditor
  /** Elemento colocado (o movido, si era el chofer). */
  uid: string | null
}

/**
 * Suelta una pieza de la paleta en una celda vacía. El chofer es uno solo: si
 * ya hay uno, se mueve. Un asiento nuevo toma el menor número libre.
 */
export function colocar(estado: EstadoEditor, pieza: Pieza, p: Posicion): Colocacion {
  if (!dentroDeRejilla(estado, p) || elementoEn(estado, p)) return { estado, uid: null }
  const posicion = { planta: p.planta, fila: p.fila, columna: p.columna }

  if (pieza.tipo === 'chofer') {
    const chofer = estado.elementos.find((e) => e.tipo === 'chofer')
    if (chofer) return { estado: mover(estado, chofer.uid, p), uid: chofer.uid }
  }

  const uid = nuevoUid()
  const elemento: ElementoEditor =
    pieza.tipo === 'asiento'
      ? {
          uid,
          tipo: 'asiento',
          id: null,
          numero: siguienteNumero(estado.elementos),
          clase: pieza.clase,
          ...posicion,
        }
      : { uid, tipo: pieza.tipo, id: null, ...posicion }
  return { estado: conElementos(estado, [...estado.elementos, elemento]), uid }
}

/** Mueve un elemento a otra celda; si está ocupada, los intercambia. */
export function mover(estado: EstadoEditor, uid: string, p: Posicion): EstadoEditor {
  const origen = buscar(estado, uid)
  if (!origen || !dentroDeRejilla(estado, p) || mismaCelda(origen, p)) return estado
  const ocupante = elementoEn(estado, p)
  const destino = { planta: p.planta, fila: p.fila, columna: p.columna }
  const desde = { planta: origen.planta, fila: origen.fila, columna: origen.columna }
  return conElementos(
    estado,
    estado.elementos.map((e) => {
      if (e.uid === uid) return { ...e, ...destino }
      if (ocupante && e.uid === ocupante.uid) return { ...e, ...desde }
      return e
    }),
  )
}

/** Quita un elemento (no los asientos con boletos vendidos). */
export function quitar(estado: EstadoEditor, uid: string): EstadoEditor {
  const elemento = buscar(estado, uid)
  if (!elemento || (esAsiento(elemento) && elemento.conBoletos)) return estado
  return conElementos(
    estado,
    estado.elementos.filter((e) => e.uid !== uid),
  )
}

export function actualizarAsiento(
  estado: EstadoEditor,
  uid: string,
  cambios: Partial<Pick<AsientoCroquis, 'numero' | 'clase'>>,
): EstadoEditor {
  return conElementos(
    estado,
    estado.elementos.map((e) => (e.uid === uid && esAsiento(e) ? { ...e, ...cambios } : e)),
  )
}

// Numeración -------------------------------------------------------------------

function renumerarEn(estado: EstadoEditor, orden: ElementoEditor[]): EstadoEditor {
  const numeros = new Map(orden.map((a, i) => [a.uid, i + 1]))
  return conElementos(
    estado,
    estado.elementos.map((e) => (esAsiento(e) ? { ...e, numero: numeros.get(e.uid)! } : e)),
  )
}

const asientosDe = (estado: EstadoEditor) =>
  estado.elementos.filter((e): e is ElementoEditor & AsientoCroquis => esAsiento(e))

/** 1..N conservando el orden actual de los números (cierra huecos y repetidos). */
export const compactarNumeracion = (estado: EstadoEditor) =>
  renumerarEn(
    estado,
    [...asientosDe(estado)].sort(
      (a, b) =>
        a.numero - b.numero || a.planta - b.planta || a.fila - b.fila || a.columna - b.columna,
    ),
  )

/** 1..N en orden de lectura: planta, fila, columna. */
export const numerarPorPosicion = (estado: EstadoEditor) =>
  renumerarEn(estado, ordenar(asientosDe(estado)))

// Rejilla ----------------------------------------------------------------------

export function agregarFila(estado: EstadoEditor, planta: number): EstadoEditor {
  const filas = estado.filas[planta]
  if (filas === undefined || filas >= FILAS_MAX) return estado
  return { ...estado, filas: { ...estado.filas, [planta]: filas + 1 } }
}

/** Quita la última fila de la planta si está vacía. */
export function quitarFila(estado: EstadoEditor, planta: number): EstadoEditor {
  const filas = estado.filas[planta]
  if (filas === undefined || filas <= 1) return estado
  if (estado.elementos.some((e) => e.planta === planta && e.fila === filas)) return estado
  return { ...estado, filas: { ...estado.filas, [planta]: filas - 1 } }
}

/** Inserta una fila vacía en `fila`, desplazando hacia atrás las siguientes. */
export function insertarFila(estado: EstadoEditor, planta: number, fila: number): EstadoEditor {
  const filas = estado.filas[planta]
  if (filas === undefined || filas >= FILAS_MAX || fila < 1 || fila > filas) return estado
  return {
    ...estado,
    filas: { ...estado.filas, [planta]: filas + 1 },
    elementos: estado.elementos.map((e) =>
      e.planta === planta && e.fila >= fila ? { ...e, fila: e.fila + 1 } : e,
    ),
  }
}

/** Elimina una fila vacía, subiendo las siguientes. */
export function eliminarFila(estado: EstadoEditor, planta: number, fila: number): EstadoEditor {
  const filas = estado.filas[planta]
  if (filas === undefined || filas <= 1) return estado
  if (estado.elementos.some((e) => e.planta === planta && e.fila === fila)) return estado
  return {
    ...estado,
    filas: { ...estado.filas, [planta]: filas - 1 },
    elementos: estado.elementos.map((e) =>
      e.planta === planta && e.fila > fila ? { ...e, fila: e.fila - 1 } : e,
    ),
  }
}

export function agregarPlanta(estado: EstadoEditor): EstadoEditor {
  const plantas = plantasEditor(estado)
  if (plantas.length >= PLANTAS_MAX) return estado
  const nueva = Math.max(...plantas) + 1
  return { ...estado, filas: { ...estado.filas, [nueva]: estado.filas[1] ?? FILAS_BUS_NUEVO } }
}

/** Quita una planta vacía (la baja no se quita). */
export function quitarPlanta(estado: EstadoEditor, planta: number): EstadoEditor {
  if (planta === 1 || estado.elementos.some((e) => e.planta === planta)) return estado
  const { [planta]: _quitada, ...filas } = estado.filas
  return { ...estado, filas }
}

/**
 * Columna de pasillo del marco: la central cuando el ancho es impar (la 3 en
 * el marco de 5 del legado).
 */
export const columnaPasilloMarco = (columnas: number) =>
  columnas % 2 === 1 ? (columnas + 1) / 2 : null

/**
 * Rellena con asientos de `clase` las celdas vacías de la planta que no son
 * pasillo ni filas con señales (chofer, puertas): distribución 2 + 2.
 */
export function rellenar(estado: EstadoEditor, planta: number, clase: ClaseAsiento): EstadoEditor {
  const filas = estado.filas[planta]
  if (filas === undefined) return estado
  const pasillo = columnaPasilloMarco(estado.columnas)
  const filasConSenal = new Set(
    estado.elementos.filter((e) => e.planta === planta && !esAsiento(e)).map((e) => e.fila),
  )
  let actual = estado
  for (let fila = 1; fila <= filas; fila++) {
    if (filasConSenal.has(fila)) continue
    for (let columna = 1; columna <= estado.columnas; columna++) {
      if (columna === pasillo) continue
      actual = colocar(actual, { tipo: 'asiento', clase }, { planta, fila, columna }).estado
    }
  }
  return actual
}

export const vaciar = (estado: EstadoEditor): EstadoEditor =>
  conElementos(
    estado,
    estado.elementos.filter((e) => esAsiento(e) && e.conBoletos),
  )

// Plantillas -------------------------------------------------------------------

export interface PlantillaAplicada {
  estado: EstadoEditor
  /** Números de asientos con boletos que la plantilla no tiene (no se podrá guardar). */
  conBoletosPerdidos: number[]
}

/**
 * Sustituye el croquis por el de una plantilla conservando la identidad de lo
 * que ya existe: un asiento con el mismo número sigue siendo el mismo
 * registro (y sus boletos), y las señales se reutilizan por tipo.
 */
export function aplicarPlantilla(
  estado: EstadoEditor,
  plantilla: readonly ElementoCroquis[],
): PlantillaAplicada {
  const asientos = new Map(asientosDe(estado).map((a) => [a.numero, a]))
  const senales = estado.elementos.filter((e) => !esAsiento(e))
  const usados = new Set<string>()

  const elementos: ElementoCroquis[] = plantilla.map((p) => {
    if (esAsiento(p)) {
      const previo = asientos.get(p.numero)
      if (previo) usados.add(previo.uid)
      return { ...p, id: previo?.id ?? null, conBoletos: previo?.conBoletos ?? false }
    }
    const previa = senales.find((s) => s.tipo === p.tipo && !usados.has(s.uid))
    if (previa) usados.add(previa.uid)
    return { ...p, id: previa?.id ?? null }
  })

  const conBoletosPerdidos = asientosDe(estado)
    .filter((a) => a.conBoletos && !usados.has(a.uid))
    .map((a) => a.numero)
    .sort((a, b) => a - b)

  return { estado: crearEstado(elementos, estado.columnas), conBoletosPerdidos }
}

/** Celdas ocupadas por clave (para pintar la rejilla). */
export const porCelda = (estado: EstadoEditor) =>
  new Map(estado.elementos.map((e) => [celda(e), e]))
