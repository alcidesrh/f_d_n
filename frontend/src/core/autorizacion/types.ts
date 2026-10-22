/** Contratos de las autorizaciones, pases de un solo uso (`/api/autorizaciones`, ADR-040). */

export type Dispensa = 'permiso' | 'limite' | 'hora'
export type EstadoAutorizacion = 'pendiente' | 'aprobada' | 'rechazada' | 'usada' | 'retirada' | 'vencida'
export type SujetoAutorizacion = 'boletos' | 'salida'
export type VistaAutorizaciones = 'por-resolver' | 'mias' | 'todas'

/**
 * Lo que necesita una operación bloqueada para hacerse con una autorización:
 * viene en el error del bloqueo (`autorizable`) y vuelve tal cual al pedirla.
 */
export interface Autorizable {
  capacidad: string
  /** «anular boletos», «emitir cortesías». */
  operacion: string
  dispensa: Dispensa
  dispensaNombre: string
  sujeto: SujetoAutorizacion
  ids: number[]
  cantidad: number
  /** Ya hay una solicitud pendiente para eso (su número). */
  pendiente?: number | null
}

export interface BoletoAutorizacion {
  id: number
  asiento: string | number | null
  estado: string
  salida: number
  fecha: string | null
  ruta: string | null
  trayecto: string
  pasajero: string | null
}

export interface Autorizacion {
  id: number
  capacidad: string
  operacion: string
  dispensa: Dispensa
  dispensaNombre: string
  sujeto: SujetoAutorizacion
  ids: number[]
  cantidad: number
  detalle: {
    venta?: number
    boletos?: BoletoAutorizacion[]
    salida?: { id: number; fecha: string | null; ruta: string | null; bus: string | null }
    asientos?: number
  }
  motivo: string
  estado: EstadoAutorizacion
  estadoNombre: string
  solicitante: { id: number | null; nombre: string }
  empresa: { id: number; nombre: string } | null
  estacion: { id: number; nombre: string } | null
  creadaEn: string
  venceEn: string
  resueltaPor: { id: number | null; nombre: string } | null
  resueltaEn: string | null
  /** Nota de quien aprobó o motivo del rechazo. */
  respuesta: string | null
  usadaEn: string | null
  /** Lo que puede hacer con ella quien mira. */
  acciones: { aprobar: boolean; retirar: boolean }
}

export interface PaginaAutorizaciones {
  items: Autorizacion[]
  total: number
  pagina: number
  porPagina: number
}

export interface ResumenAutorizaciones {
  /** Pendientes que el usuario puede resolver. */
  porResolver: number
  /** Sus pases aprobados sin usar. */
  aprobadas: number
  /** Sus solicitudes esperando respuesta. */
  pendientes: number
  /** Tiene `autorizacion.aprobar` en algún lado. */
  aprueba: boolean
}
