/** Respuesta de `GET /api/seguimiento/buses` (mapa de buses en recorrido). Tiempos en epoch, en segundos. */

export type EstadoMovimiento = 'por_salir' | 'en_ruta' | 'detenido' | 'llego'

/** `simulada` se infiere del cronograma; `gps` viene de un dispositivo real. */
export type FuentePosicion = 'simulada' | 'gps'

export interface PosicionServidor {
  lat: number
  lng: number
  rumbo: number
  velocidadKmh: number
  km: number
  estado: EstadoMovimiento
  instante: number
  fuente: FuentePosicion
}

export interface ParadaPlan {
  nombre: string
  lat: number
  lng: number
  km: number
  llegada: number
  salida: number
  /** De dónde sale la coordenada de la estación: `gps`, `catalogo` o `departamento`. */
  origen: string
}

export interface EstacionMapa {
  nombre: string
  lat: number
  lng: number
  origen: string
}

export interface BusEnRecorrido {
  salidaId: number
  /** El sistema ya la marcó iniciada; si no, se asume que salió por su hora y sus boletos vendidos. */
  marcadaIniciada: boolean
  estadoSistema: 'programada' | 'abordando' | 'iniciada' | 'otro'
  empresaId: number
  empresa: string
  bus: string
  placa: string | null
  piloto: string | null
  ruta: string
  rutaNombre: string
  origen: string
  destino: string
  partida: number
  llegadaEstimada: number
  kilometros: number
  progreso: number
  proxima: { nombre: string; llegada: number } | null
  posicion: PosicionServidor
  /** Cronograma para animar entre consultas; vacío cuando la posición es GPS. */
  paradas: ParadaPlan[]
  trazado: [number, number][]
  estaciones: EstacionMapa[]
}

export interface Empresa {
  id: number
  nombre: string
}

export interface RespuestaSeguimiento {
  generado: number
  buses: BusEnRecorrido[]
  empresas: Empresa[]
  /** Salidas cuya ruta no se pudo ubicar en el mapa. */
  sinTrazado: number
}

export interface PosicionEstimada {
  lat: number
  lng: number
  rumbo: number
  velocidadKmh: number
  km: number
  estado: EstadoMovimiento
  /** 0..1 del recorrido total. */
  progreso: number
  /** En marcha: índice del tramo (parada de salida) y fracción 0..1 recorrida de él. */
  tramo?: number
  fraccion?: number
}

export interface FiltroSeguimiento {
  empresaId: number | null
  salidaId: number | null
}
