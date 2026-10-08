/** Contratos de la gestión de salidas (`/api/gestion-salidas/*`, ADR-024). */
import type { ElementoCroquis } from '@/core/croquis/types'
import type { AsientoOcupado, Importe } from '@/core/venta/types'

export type EstadoSalida = 'programada' | 'abordando' | 'iniciada' | 'finalizada' | 'cancelada'

export interface TrayectoOpcion {
  id: number
  origen: string | null
  destino: string | null
  ruta: string
  duracionMinutos: number | null
}

export interface BusOpcion {
  id: number
  codigo: string
  matricula: string | null
  empresaId: number | null
}

export interface Opcion {
  id: number
  nombre: string
}

export interface OpcionesSalidas {
  empresas: Opcion[]
  trayectos: TrayectoOpcion[]
  buses: BusOpcion[]
  puede: { crear: boolean; editar: boolean; anular: boolean; eliminar: boolean }
}

export interface SalidaFila {
  id: number
  /** ISO con zona */
  fecha: string
  estado: EstadoSalida
  /** Programada y ya debió salir. */
  atrasada: boolean
  trayecto: TrayectoOpcion
  bus: BusOpcion | null
  empresa: Opcion | null
  /** Asientos comprometidos: boletos vivos + reservas web vigentes. */
  vendidos: number
  capacidad: number | null
}

export interface PaginaSalidas {
  items: SalidaFila[]
  total: number
  pagina: number
  porPagina: number
  porEstado: Partial<Record<EstadoSalida, number>>
}

/** Referencia corta de una salida en mensajes y resultados. */
export interface SalidaRef {
  id: number | null
  fecha: string
  ruta: string
  bus: string | null
}

export interface Momento {
  /** `HH:MM` */
  hora: string
  busId: number | null
}

export interface ProgramacionPayload {
  trayectoId: number
  momentos: Array<{ hora: string; busId: number }>
  /** `AAAA-MM-DD` */
  desde: string
  hasta: string | null
  intervaloDias: number
  guardarComo?: string
}

export type EstadoPlan = 'nueva' | 'existe' | 'conflicto' | 'pasada'

export interface ItemPlan {
  fecha: string
  busId: number
  bus: string
  estado: EstadoPlan
  choque: SalidaRef | null
}

export interface VistaPrevia {
  items: ItemPlan[]
  resumen: Record<EstadoPlan | 'total', number>
}

export interface ResultadoProgramacion {
  creadas: number
  resumen: VistaPrevia['resumen']
  omitidas: ItemPlan[]
  esquema: Esquema | null
}

export interface Esquema {
  id: number
  nombre: string
  trayecto: TrayectoOpcion
  intervaloDias: number
  momentos: Array<{ hora: string; busId: number; bus: string }>
  actualizadoEn: string
  actualizadoPor: string | null
}

export interface EsquemaPayload {
  nombre: string
  trayectoId: number
  intervaloDias: number
  momentos: Array<{ hora: string; busId: number }>
}

export interface Propagacion {
  salida: SalidaRef
  vendidos: number
  identicas: number
  identicasConAsientos: number
  ultima: string | null
}

export type MotivoOmision = 'asientos' | 'conflicto' | 'historial'

export interface ResultadoOperacion {
  aplicadas: SalidaRef[]
  omitidas: Array<SalidaRef & { motivo: MotivoOmision; asientos?: number; choque?: SalidaRef }>
}

export interface CambioSalida {
  trayectoId?: number
  busId?: number
  /** `AAAA-MM-DDTHH:MM` local */
  fecha?: string
  propagar: boolean
}

export type TipoManifiesto = 'interno' | 'piloto'

export interface ResumenClase {
  clase: 'A' | 'B'
  asientos: number
  vendidos: number
  reservados: number
}

/** Qué se vendió: asientos (no boletos) por canal; cortesías y vouchers van aparte. */
export interface ResumenSalida {
  clases: ResumenClase[]
  asientos: number
  vendidos: number
  reservados: number
  canales: { estacion: number; agencia: number; web: number; cortesia: number; voucher: number }
  /** Boletos vivos por estado (`emitido`, `chequeado`, `transito`, `finalizado`). */
  boletosPorEstado: Partial<Record<string, number>>
  /** Lo cobrado (sin cortesías ni vouchers); null si no hay boletos. */
  ingresos: Importe | null
}

/** `GET /gestion-salidas/{id}/detalle`: lo que muestra "Ver". */
export interface DetalleSalida {
  id: number
  /** ISO con zona */
  salida: string
  estado: EstadoSalida
  empresa: Opcion | null
  bus: { id: number; codigo: string; gama: string | null } | null
  trayecto: { id: number; origen: Opcion; destino: Opcion }
  croquis: ElementoCroquis[]
  /** Asientos ocupados para el viaje completo (los libres no aparecen). */
  ocupados: AsientoOcupado[]
  /** Piloto y copiloto del bus (`N/D` si falta). */
  pilotos: string[]
  /** Quien la programó; null en las migradas del legado. */
  creadaPor: string | null
  creadaEn: string | null
  resumen: ResumenSalida
}
