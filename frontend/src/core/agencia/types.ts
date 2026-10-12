/** Depósitos y saldos de agencia (ADR-034, `/api/depositos-agencia`). */
import type { Importe } from '@/core/venta/types'

export type EstadoDeposito = 'registrado' | 'acreditado' | 'rechazado'

export interface DepositoFila {
  id: number
  /** `AAAA-MM-DD`: día del depósito en el banco. */
  fecha: string
  agencia: { id: number; nombre: string }
  estado: EstadoDeposito
  importe: Importe
  aplicaBono: boolean
  /** Registrado: el que se acreditaría hoy; acreditado: el que se acreditó. */
  bono: Importe
  numeroBoleta: string
  observacion: string | null
  motivoRechazo: string | null
  creadoEn: string
  creadoPor: string | null
  resueltoEn: string | null
  resueltoPor: string | null
}

export interface PaginaDepositos {
  items: DepositoFila[]
  total: number
  desde: string
  hasta: string
}

export interface AgenciaOpcion {
  id: number
  nombre: string
  moneda: string
  porcentajeBonificacion: string | null
  activo: boolean
}

export interface OpcionesDepositos {
  hoy: string
  desde: string
  hasta: string
  porPagina: number[]
  agencias: AgenciaOpcion[]
  /** La agencia del usuario (solo ve y registra para ella). */
  agenciaPropia: number | null
  puede: { acreditar: boolean; elegirBono: boolean }
}

export interface NuevoDeposito {
  agencia: number
  fecha: string
  /** Centavos. */
  importe: number
  numeroBoleta: string
  aplicaBono: boolean
  observacion?: string
}

export interface SaldoAgenciaFila {
  id: number
  nombre: string
  activo: boolean
  moneda: string
  porcentajeBonificacion: string | null
  saldo: Importe
  totalDepositado: Importe
  totalBonificado: Importe
  pendientes: number
  importePendiente: Importe
}
