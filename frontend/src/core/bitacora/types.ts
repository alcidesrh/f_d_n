/** Bitácora de un registro (`GET /api/bitacora/{tipo}/{id}`). */
export type TipoBitacora = 'salida' | 'boleto'

export interface EntradaBitacora {
  id: number
  operacion: string
  /** Texto de la operación («Boleto anulado»). */
  etiqueta: string
  fecha: string
  /** Quien la hizo; null si fue el sistema o la página web. */
  usuario: { id: number | null; username: string | null; nombre: string | null } | null
  detalle: Record<string, unknown> | null
}

export interface Bitacora {
  tipo: TipoBitacora
  id: number
  etiqueta: string
  entradas: EntradaBitacora[]
}
