/** Listado de boletos (`/api/boletos`, el "Boletos" del legado). */
import type { Importe } from '@/core/venta/types'

export type EstadoBoleto = 'emitido' | 'chequeado' | 'transito' | 'finalizado' | 'anulado' | 'reasignado' | 'cancelado'

/** Documento de la venta (`ListadoBoletos::documento`). */
export type TipoDocumento = 'factura' | 'pendiente' | 'sin_factura' | 'agencia' | 'cortesia' | 'voucher'

export type OrdenBoletos = 'id' | 'fecha' | 'numero' | 'ruta' | 'clienteDocumento' | 'clienteBoleto' | 'creada'

export interface ClienteBoleto {
  id: number
  nombre: string
  /** NIT (cliente del documento) o documento de identidad (pasajero). */
  documento: string | null
  email: string | null
}

export interface BoletoFila {
  id: number
  estado: EstadoBoleto
  /** Hora de salida (ISO). */
  fecha: string
  salidaId: number
  salidaEstado: string
  empresa: string | null
  asiento: number
  clase: 'A' | 'B'
  documento: { tipo: TipoDocumento; serie: string | null; numero: number | null }
  ruta: string
  sube: string | null
  baja: string | null
  clienteDocumento: ClienteBoleto | null
  clienteBoleto: ClienteBoleto | null
  tipoPago: string | null
  precio: Importe | null
  sinCobro: boolean
  canal: 'estacion' | 'agencia' | 'web'
  ventaId: number
  ventaConfirmada: boolean
  reasignadoDe: number | null
  referenciaExterna: string | null
  autorizacion: string | null
  creada: string | null
  usuario: string | null
  estacion: string | null
}

export interface PaginaBoletos {
  items: BoletoFila[]
  total: number
  pagina: number
  porPagina: number
  desde: string
  hasta: string
}

export interface DetalleBoleto extends BoletoFila {
  observacion: string | null
  reasignadoA: number | null
  salida: { id: number; fecha: string; estado: string; empresa: string | null; bus: string | null }
  venta: {
    id: number
    canal: string
    estado: string
    estadoFacturacion: string
    errorFacturacion: string | null
    total: Importe | null
    agencia: string | null
    enviarCorreo: boolean
  }
  factura: {
    serie: string | null
    numero: number | null
    uuid: string | null
    certificada: string | null
    receptorNit: string | null
    receptorNombre: string | null
    anulada: string | null
    motivoAnulacion: string | null
    urlPdf: string | null
  } | null
  otros: Array<{ id: number; asiento: number; estado: EstadoBoleto }>
}

export interface PuedeBoleto {
  cancelar: boolean
  anular: boolean
  reasignar: boolean
  chequear: boolean
}

export interface OpcionesBoletos {
  desde: string
  hasta: string
  maxDias: number
  porPagina: number[]
  estaciones: Array<{ id: number; nombre: string; departamento: string | null }>
  puede: PuedeBoleto
}
