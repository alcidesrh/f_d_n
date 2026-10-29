/** Abordaje de salidas (ADR-032): `/api/abordaje/*`. */
import type { EstadoSalida } from '@/core/salida/types'

export interface SalidaAbordaje {
  id: number
  /** ISO con zona */
  fecha: string
  estado: EstadoSalida
  ruta: string
  origenId: number | null
  destinoId: number | null
  empresa: string | null
  bus: string | null
}

export interface PuedeAbordaje {
  chequear: boolean
  abordar: boolean
  iniciar: boolean
  finalizar: boolean
  manifiestos: boolean
}

export type EstadoBoletoAbordaje = 'emitido' | 'chequeado' | 'transito' | 'finalizado'

export interface BoletoAbordaje {
  id: number
  asiento: number
  clase: string
  estado: EstadoBoletoAbordaje
  /** Factura (`serie número`), `Voucher`, `Cortesía`, `Agencia …` o `Página web`. */
  documento: string | null
  sube: string | null
  baja: string | null
  /** 0 = sube en el origen de la ruta. */
  ordenSube: number
  cliente: string | null
  /** Este usuario puede marcarlo ahora (emitido, sube en su estación, salida en ventana). */
  chequeable: boolean
}

export interface DetalleAbordaje {
  salida: SalidaAbordaje
  topico: string
  chequeable: boolean
  /** Por qué no se puede chequear la salida ahora. */
  motivo: string | null
  boletos: BoletoAbordaje[]
}

export interface ResultadoChequeo {
  chequeados: number
  cancelados: number
}

export interface ResultadoFinal {
  salida: SalidaAbordaje
  finalizados: number
  cancelados: number
}

export interface ResultadoInicio {
  salida: SalidaAbordaje
  enTransito: number
  cancelados: number
}
