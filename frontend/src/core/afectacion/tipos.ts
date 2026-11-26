/** Afectaciones de la logística (ADR-070): lo que devuelve `/api/afectaciones`. */

export type PrioridadAfectacion = 'normal' | 'grave' | 'urgente'
export type EstadoAfectacion = 'creada' | 'pendiente' | 'resuelta'
export type SujetoAfectacion = 'estacion' | 'bus' | 'empresa'
export type VistaAfectaciones = 'abiertas' | 'resueltas' | 'todas'

export interface Lugar {
  id: number
  nombre: string
}

export interface Persona {
  id: number
  nombre: string
}

export interface Afectacion {
  id: number
  tipo: { id: number; codigo: string; nombre: string; prioridad: PrioridadAfectacion; sujeto: SujetoAfectacion }
  estado: EstadoAfectacion
  descripcion: string
  /** «Estación Central», «bus 120 (Estación Central)» o la empresa. */
  lugar: string
  empresa: Lugar | null
  estacion: Lugar | null
  bus: { id: number; codigo: string } | null
  /** null si la detectó el sistema. */
  reportadaPor: Persona | null
  automatica: boolean
  /** ISO con zona */
  creadaEn: string
  enviadaEn: string | null
  escaladaEn: string | null
  ultimoAvisoEn: string | null
  avisos: number
  /** Cuándo vuelve a avisar el centinela; null si ya está resuelta. */
  proximoAviso: string | null
  resueltaEn: string | null
  resueltaPor: Persona | null
  resolucion: string | null
  puede: { editar: boolean; resolver: boolean }
}

export interface PaginaAfectaciones {
  filas: Afectacion[]
  total: number
  pagina: number
  porPagina: number
  puede: { tipos: boolean; supervisar: boolean }
}

/** Un tipo que el usuario puede reportar; `empresas`/`estaciones`: dónde (null = en todas). */
export interface TipoReportable {
  id: number
  codigo: string
  nombre: string
  descripcion: string | null
  sujeto: SujetoAfectacion
  prioridad: PrioridadAfectacion
  empresas: number[] | null
  estaciones: number[] | null
}

export interface BusReportable {
  id: number
  codigo: string
  matricula: string | null
  empresaId: number | null
}

/** `GET /api/afectaciones/para-reportar`. */
export interface ParaReportar {
  tipos: TipoReportable[]
  /** La estación de trabajo del usuario, para proponerla. */
  estacion: Lugar | null
  empresas: Lugar[]
  estaciones: Lugar[]
  buses: BusReportable[]
}

export interface Reporte {
  tipo: number
  descripcion: string
  empresa?: number | null
  estacion?: number | null
  bus?: number | null
}

/** `GET /api/afectaciones/capacidades`: para elegir `owner`, `target` y `escalamiento` de un tipo. */
export interface CapacidadElegible {
  codigo: string
  nombre: string
  area: string
}
