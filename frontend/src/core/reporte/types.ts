/** Contratos de los reportes de venta (`/api/reportes/*`, permiso `reporte.ventas`). */

export interface Opcion {
  id: number
  nombre: string
}

export interface Moneda {
  id: number
  sigla: string
  nombre: string
}

export interface OpcionesReporte {
  /** Hoy en el servidor (`AAAA-MM-DD`). */
  hoy: string
  estaciones: Opcion[]
  empresas: Opcion[]
  monedas: Moneda[]
  /** Estación y empresa fijas del usuario; con valor, no puede elegir otra. */
  alcance: { estacion: number | null; empresa: number | null }
}

/** Cifras del cuadre; los importes van en centavos. */
export interface ResumenCuadre {
  moneda: string
  ventas: number
  boletos: number
  recibido: number
  anulado: number
  facturado: number
  usuarios: number
  salidas: number
  prepagados: number
  tarjetas: number
  anulados: number
}

export interface ResumenDetalle {
  cantidad: number
  totales: { moneda: string; cantidad: number; total: number }[]
  conTarjeta: number
  sinFactura: number
}

export type FormatoReporte = 'pdf' | 'xlsx'
