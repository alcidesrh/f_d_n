/** Contratos de la gestión de rutas (`/api/gestion-rutas/*`, ADR-030). */

export interface RutaFila {
  id: number
  nombre: string
  origen: string | null
  destino: string | null
  paradas: number
  duracionMinutos: number | null
  kilometros: number | null
  activo: boolean
  /** Código de la ruta del legado. */
  codigo: string | null
  /** Salidas programadas que aún no salen. */
  salidasFuturas: number
}

export interface TiempoClase {
  claseId: number
  minutos: number
}

export interface ParadaRuta {
  enclaveId: number
  nombre: string
  /** Desde el origen; null en el origen o si no se conoce. */
  minutos: number | null
  km: number | null
  permiteSubir: boolean
  permiteBajar: boolean
  /** Minutos para las clases de bus que difieren de `minutos`. */
  tiempos: TiempoClase[]
}

export interface RutaDetalle {
  id: number
  nombre: string
  kilometros: number | null
  activo: boolean
  codigo: string | null
  paradas: ParadaRuta[]
  salidasFuturas: number
  /** De esas, cuántas tienen boletos: conservan sus paradas al guardar. */
  salidasConBoletos: number
  /** Por posición de la parada: minutos que retroceden. */
  avisos: Record<string, string>
}

export interface Lugar {
  id: number
  nombre: string
  departamento: string | null
  latitud: number | null
  longitud: number | null
}

export interface OpcionesRutas {
  lugares: Lugar[]
  clases: Array<{ id: number; nombre: string }>
  puedeEditar: boolean
}

export interface RutaPayload {
  nombre: string
  kilometros: number | null
  activo: boolean
  paradas: Array<Omit<ParadaRuta, 'nombre' | 'enclaveId'> & { enclaveId: number }>
}
