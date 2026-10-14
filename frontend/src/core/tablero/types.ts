/** Contrato del tablero de gestión (`GET /api/tablero`, permiso `reporte.ventas`). Importes en centavos. */

export type DiasTablero = 1 | 7 | 30 | 90

export interface Comparado<T = number | null> {
  actual: T
  anterior: T
}

export interface Kpis {
  ingresos: Comparado<number>
  boletos: Comparado<number>
  ticketPromedio: Comparado
  ocupacion: Comparado
  ingresoPorSalida: Comparado
  salidas: Comparado<number>
  salidasCanceladas: Comparado<number>
  anulados: Comparado<number>
  montoAnulado: Comparado<number>
  tasaAnulacion: Comparado
  tasaNoPresentados: Comparado
  cortesias: Comparado<number>
  valorCortesias: Comparado<number>
}

export type Canal = 'estacion' | 'agencia' | 'web'

export interface CuboSerie {
  /** `AAAA-MM-DD` o `AAAA-MM-DD HH:00` (hoy, por hora). */
  t: string
  /** Null: el cubo todavía no llega. */
  actual: (Record<Canal, number> & { total: number; boletos: number }) | null
  anterior: { total: number; boletos: number }
}

export interface CanalVentas {
  canal: Canal
  ingresos: number
  boletos: number
  anterior: number
}

export type EstadoSalida = 'programada' | 'abordando' | 'iniciada' | 'finalizada' | 'cancelada'

export interface SalidaProxima {
  id: number
  fecha: string
  ruta: string
  bus: string | null
  empresa: string | null
  estado: EstadoSalida
  boletos: number
  asientos: number
  ocupacion: number | null
}

export interface OperacionHoy {
  total: number
  estados: Record<EstadoSalida, number>
  asientosVendidos: number
  ocupacion: number | null
  proximas: SalidaProxima[]
  masProximas: number
}

export interface RutaTablero {
  id: number
  nombre: string
  salidas: number
  boletos: number
  ingresos: number
  ingresoPorSalida: number
  ocupacion: number | null
  ocupacionAnterior: number | null
  senal: 'saturada' | 'baja' | null
}

export interface CeldaDemanda {
  /** 1 = lunes … 7 = domingo. */
  dia: number
  hora: number
  salidas: number
  ocupacion: number | null
  boletos: number
}

export interface DiaAnticipado {
  dia: string
  salidas: number
  asientos: number
  boletos: number
  ocupacion: number | null
  semanaAnterior: number | null
}

export interface PuntoVenta {
  tipo: Canal
  nombre: string
  ingresos: number
  boletos: number
  anterior: number
}

export interface AgenciaCobertura {
  id: number
  nombre: string
  saldo: number
  moneda: string
  ventaDiaria: number
  /** Días de venta que cubre el saldo; null si no vendió en la semana. */
  cobertura: number | null
}

export interface BusTarjeta {
  id: number
  codigo: string
  vence: string
}

export interface Flota {
  buses: number
  enServicio: number
  vencidas: BusTarjeta[]
  porVencer: BusTarjeta[]
}

export type NivelAlerta = 'critico' | 'alerta' | 'aviso'

export interface Alerta {
  nivel: NivelAlerta
  codigo: string
  titulo: string
  detalle: string
  /** Ruta del frontend donde se resuelve. */
  destino: string | null
}

export interface Tablero {
  alcance: { empresa: number | null; fija: boolean }
  empresas: { id: number; nombre: string }[]
  moneda: string
  generado: string
  periodo: {
    dias: DiasTablero
    desde: string
    hasta: string
    anteriorDesde: string
    anteriorHasta: string
    rotuloAnterior: string
    porHora: boolean
  }
  kpis: Kpis
  serie: CuboSerie[]
  canales: CanalVentas[]
  operacionHoy: OperacionHoy
  rutas: RutaTablero[]
  demanda: { desde: string; celdas: CeldaDemanda[] }
  anticipada: DiaAnticipado[]
  puntos: PuntoVenta[]
  agencias: AgenciaCobertura[]
  flota: Flota
  alertas: Alerta[]
}
