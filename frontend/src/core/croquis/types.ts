/**
 * Croquis de un bus (ADR-019): una rejilla por planta (hasta dos) con
 * coordenadas desde 1, donde cada celda tiene como mucho un elemento —un
 * asiento vendible o una señal (chofer, puerta)—.
 *
 * Es el mismo dato para todos los usos del mapa del bus: edición del bus,
 * ocupación de un recorrido, selección de asientos en taquilla o en la venta
 * en línea. Lo que cambia entre usos es cómo se pinta cada asiento
 * (`EstadoAsiento`), no el croquis.
 */

export type ClaseAsiento = 'A' | 'B'
export type TipoSenal = 'chofer' | 'puerta'
export type TipoElemento = 'asiento' | TipoSenal

export interface Posicion {
  /** 1 = planta baja (o única), 2 = planta alta. */
  planta: number
  fila: number
  columna: number
}

export interface AsientoCroquis extends Posicion {
  tipo: 'asiento'
  /** Id del `Asiento`; null si aún no se guardó. */
  id: number | null
  numero: number
  clase: ClaseAsiento
  /** Tiene boletos vendidos: no se puede quitar del croquis (solo lectura). */
  conBoletos?: boolean
}

export interface SenalCroquis extends Posicion {
  tipo: TipoSenal
  /** Id del `BusSenal`; null si aún no se guardó. */
  id: number | null
}

export type ElementoCroquis = AsientoCroquis | SenalCroquis

/** Dimensiones de la rejilla de una planta. */
export interface Rejilla {
  filas: number
  columnas: number
}

/** Croquis distinto de la flota con los buses que lo usan (`GET /croquis/plantillas`). */
export interface PlantillaCroquis {
  firma: string
  buses: Array<{ id: number; codigo: string }>
  asientos: number
  plantas: number
  elementos: ElementoCroquis[]
}

/**
 * Cómo se pinta un asiento en un uso concreto del mapa. El croquis no lo
 * sabe: lo decide quien pinta (p. ej. la ocupación de un recorrido).
 */
export type EstadoAsiento = 'disponible' | 'ocupado' | 'seleccionado' | 'reservado' | 'bloqueado'
