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
  /** Teléfonos de prueba con lectura vigente (solo para quien ve todas las empresas). */
  telefonos?: TelefonoEnVivo[]
}

/**
 * Teléfono de prueba (temporal, ADR-050): un Traccar Client con un nombre acordado en vez del código
 * de un bus. Se sigue como un bus pero sin salida: más de 5 min sin lecturas cierra su trayectoria.
 */
export interface TelefonoEnVivo {
  dispositivo: string
  /** Primera lectura de la trayectoria en curso (epoch). */
  desde: number
  posicion: PosicionServidor
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
  /** Solo los buses con lectura GPS vigente (si no, también los simulados). */
  soloGps: boolean
}

/** Lectura GPS guardada (ADR-050): `[instante, lat, lng, velocidadKmh, rumbo]`. */
export type PuntoGps = [number, number, number, number, number]

/** Salida con lecturas GPS guardadas (`GET /api/seguimiento/trayectorias`). */
export interface SalidaConTrayectoria {
  salidaId: number
  estadoSistema: string
  empresaId: number
  empresa: string
  bus: string
  placa: string | null
  ruta: string
  rutaNombre: string
  origen: string
  destino: string
  partida: number
  /** Hora programada en la última parada. */
  llegadaProgramada: number | null
  lecturas: number
  primera: number | null
  ultima: number | null
}

export interface RespuestaTrayectorias {
  fecha: string
  salidas: SalidaConTrayectoria[]
  /** Salidas del día sin lecturas GPS. */
  sinGps: number
  /** Trayectorias de los teléfonos de prueba que empiezan ese día. */
  telefonos?: TelefonoConTrayectoria[]
}

/** Trayectoria de un teléfono de prueba: lecturas seguidas, sin más de 5 min entre dos. */
export interface TelefonoConTrayectoria {
  dispositivo: string
  /** Primera y última lectura (epoch). */
  desde: number
  hasta: number
  lecturas: number
}

/** Trayectoria de un teléfono de prueba para reproducirla (`GET /api/seguimiento/telefonos/trayectoria`). */
export interface TrayectoriaDeTelefono extends TelefonoConTrayectoria {
  puntos: PuntoGps[]
}

/** Trayectoria de una salida para reproducirla (`GET /api/seguimiento/salidas/{id}/trayectoria`). */
export interface Trayectoria extends SalidaConTrayectoria {
  piloto: string | null
  /** Lapso del que se tomaron las lecturas del bus (epoch). */
  desde: number
  hasta: number
  /** Ruta planificada; vacía si sus estaciones no se pudieron ubicar. */
  trazado: [number, number][]
  estaciones: EstacionMapa[]
  puntos: PuntoGps[]
}
