/**
 * Contratos de la venta de asientos (ADR-021): `VentaController` (taquilla y
 * agencias) y `AgenciaController`. Ids numéricos; importes en centavos.
 */
import type { ClaseAsiento, ElementoCroquis } from '@/core/croquis/types'

export type CanalVenta = 'estacion' | 'agencia' | 'web'

export interface Importe {
  centavos: number
  moneda: string
  /** Formateado para mostrar (`Q 100.00`). */
  texto: string
}

export interface Opcion {
  id: number
  nombre: string
}

export interface ContextoVenta {
  canal: 'estacion' | 'agencia'
  usuario: { id: number; username: string; nombre: string }
  estacion: (Opcion & { departamento: string | null }) | null
  agencia: (Opcion & { saldo: Importe }) | null
  permisos: { cortesia: boolean; sinFactura: boolean }
  estaciones: Array<Opcion & { direccion: string | null; departamento: string | null }>
  tiposPago: Opcion[]
  monedas: Array<Opcion & { sigla: string }>
  tiposDocumento: Opcion[]
  naciones: Opcion[]
}

export interface SalidaResumen {
  id: number
  /** Salida del origen del salida (ISO). */
  salida: string
  /** Hora estimada en la estación elegida (ISO), si se conoce. */
  salidaEstacion: string | null
  estado: 'programada' | 'abordando' | 'iniciada' | 'finalizada' | 'cancelada'
  empresa: Opcion | null
  bus: { id: number; codigo: string; gama: string | null } | null
  trayecto: { id: number; origen: Opcion; destino: Opcion }
  vendidos: number | null
  capacidad: number | null
}

export interface Parada {
  id: number
  nombre: string | null
  direccion: string | null
  posicion: number
  hora: string | null
}

/** Trayecto de la salida (el suyo o un subtrayecto) con tarifa: solo esos se venden. */
export interface TrayectoVendible {
  id: number
  origen: number
  destino: number
  completo: boolean
  /** Clases de asiento con tarifa en este trayecto; las demás no se venden en él. */
  clases: ClaseAsiento[]
}

export interface SalidaDetalle extends SalidaResumen {
  paradas: Parada[]
  trayectos: TrayectoVendible[]
  croquis: ElementoCroquis[]
  cierreEnLinea: string
  /** Tópico Mercure con los cambios de ocupación. */
  topico: string
}

export type EstadoOcupacion = 'vendido' | 'reservado' | 'propio'

export interface AsientoOcupado {
  asiento: number
  estado: EstadoOcupacion
  canal: CanalVenta | null
  /** Vendido sin cobro: cortesía de taquilla o voucher del legado. */
  sinCobro?: 'cortesia' | 'voucher' | null
}

export interface LineaCotizacion {
  asiento: number
  numero: number
  clase: 'A' | 'B'
  precio: Importe
  tarifa: number | null
}

export interface Cotizacion {
  asientos: LineaCotizacion[]
  total: Importe
}

export interface Cliente {
  id: number
  nombre: string
  apellido: string | null
  nombreCompleto: string
  nit: string
  email: string | null
  telefono: string | null
  tipoDocumento: number | null
  numeroDocumento: string | null
  nacionalidad: number | null
  label: string
}

export type ClienteDatos = Partial<Omit<Cliente, 'id' | 'nombreCompleto' | 'label'>> & {
  nombre: string
}

export interface PedidoVenta {
  token: string
  salida: number
  trayecto: number | null
  asientos: Array<{ asiento: number; cliente?: number | null }>
  cliente: number
  estacion?: number | null
  observacion?: string | null
  cobrarTrayectoCompleto?: boolean
  tipoPago?: number | null
  moneda?: number | null
  enviarCorreo?: boolean
  cortesia?: boolean
  sinFacturaElectronica?: boolean
}

/** Comprobante de una venta (`App\Venta\Boleto\DatosBoleto`). */
export interface Comprobante {
  id: number
  token: string | null
  canal: CanalVenta
  estado: 'pendiente' | 'confirmada'
  estadoFacturacion: 'certificada' | 'pendiente' | 'no_aplica'
  /** Número de acceso de la SAT de una venta en contingencia (sin factura aún). */
  numeroAcceso: number | null
  cortesia: boolean
  creada: string | null
  codigoBarras: string
  empresa: {
    nombre: string
    nit: string | null
    direccion: string | null
    telefono: string | null
  } | null
  estacion: { nombre: string; direccion: string | null } | null
  agencia: string | null
  vendedor: string | null
  factura: {
    numero: number
    serie: string
    uuid: string
    fechaCertificacion: string | null
    certificador: string | null
    certificadorNit: string | null
    receptorNit: string
    receptorNombre: string
    /** PDF del DTE en el portal del certificador. */
    urlPdf: string | null
  } | null
  cliente: { nombre: string; nit: string; email: string | null } | null
  /** `salida`: inicio de la ruta; `salidaOrigen`: hora estimada donde sube el pasajero. */
  salida: { id: number; salida: string; salidaOrigen: string; bus: string | null } | null
  origen: { nombre: string; direccion: string | null } | null
  destino: { nombre: string; direccion: string | null } | null
  boletos: Array<{
    id: number
    asiento: number
    clase: 'A' | 'B'
    pasajero: string | null
    precio: Importe | null
    observacion: string | null
    estado: string
  }>
  total: Importe
  tipoPago: string | null
}

/** Cuerpo de error de la API de venta. */
export interface ErrorVenta {
  error: string
  codigo: string
  /** Facturación: el fallo es de comunicación (tiene sentido reintentar). */
  recuperable?: boolean
  /** Facturación: el usuario puede seguir sin factura electrónica. */
  permiteSinFactura?: boolean
  asientos?: number[]
}

export interface MovimientoAgencia {
  id: number
  fecha: string
  tipo: 'deposito' | 'bonificacion' | 'venta' | 'ajuste'
  monto: Importe
  saldo: Importe
  referencia: string | null
  observacion: string | null
  usuario: string | null
  venta: number | null
}

export interface EstadoAgencia {
  id: number
  nombre: string
  saldo: Importe
  porcentajeBonificacion: string | null
  puedeAcreditar: boolean
  movimientos: MovimientoAgencia[]
}
