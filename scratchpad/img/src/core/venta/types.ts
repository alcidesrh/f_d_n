/**
 * Contratos de la venta de asientos (ADR-021): `VentaController` (taquilla y
 * agencias) y `AgenciaController`. Ids numéricos; importes en centavos.
 */
import type { ClaseAsiento, ElementoCroquis } from '@/core/croquis/types'
import type { ArticuloPedido, DatosEncomienda, EspecialOpcion } from '@/core/encomienda/types'

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
  /** Encomiendas que lleva el pasajero (ADR-035): taquilla con `encomienda.registrar`. */
  encomiendas: { permitido: boolean; especiales: EspecialOpcion[] }
}

/** Ruta que recorre el bus de una salida (ADR-030). */
export interface RutaVenta {
  id: number
  nombre: string
  /** `alias`: la sigla de la estación (GUA, SEP); sin ella, se muestra el nombre. */
  origen: Opcion & { alias: string | null }
  destino: Opcion & { alias: string | null }
}

/** Una punta del viaje del cliente: hora null si no se conoce; `aprox` si es estimada. */
export interface ParadaViaje {
  id: number
  nombre: string | null
  hora: string | null
  aprox: boolean
}

/** El viaje del cliente dentro de la salida (ADR-030). */
export interface ViajeCliente {
  sube: ParadaViaje
  baja: ParadaViaje
  /** Paradas entre la de subida y la de bajada. */
  intermedias: number
  /** Primera y última parada de la ruta del bus, con su hora. */
  inicio: ParadaViaje
  fin: ParadaViaje
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
  ruta: RutaVenta
  /** Asientos ocupados en el tramo que se ofrece (en la lista de la estación). */
  vendidos: number | null
  capacidad: number | null
  /**
   * Precio por clase de asiento desde la estación hasta el destino elegido
   * (o hasta el final); vacío si ese tramo no tiene tarifa. Solo en la lista.
   */
  precios?: { clase: ClaseAsiento; precio: Importe | null }[]
  /** Dónde sube y baja quien compra en la estación (solo en la lista). */
  viaje?: ViajeCliente
}

export interface Parada {
  id: number
  nombre: string | null
  direccion: string | null
  posicion: number
  hora: string | null
}

/** Par de paradas de la salida con tarifa: solo esos se venden. `completo`: de la primera a la última. */
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
  /** Taquilla: por qué ya no se vende (anulado, iniciado, día pasado…); null si se vende. */
  noVendible?: string | null
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

/** Lo que hay detrás de un asiento ocupado (`GET /venta/salidas/{id}/asientos/{asiento}`). */
export interface TrayectoAsiento {
  id: number | null
  origen: string
  destino: string
}

export interface BoletoDeAsiento {
  id: number
  /** Emitido y antes de la hora de salida: se puede anular o reasignar (si el usuario tiene permiso). */
  operable: boolean
  /** `false`: venta de otro vendedor; solo se ve lo operativo. */
  completo: boolean
  estado: string
  trayecto: TrayectoAsiento
  pasajero?: {
    nombre: string
    documento: string | null
    tipoDocumento: string | null
    nacionalidad: string | null
    telefono: string | null
    email: string | null
  } | null
  observacion?: string | null
  precio?: Importe | null
  venta: {
    id: number
    canal: CanalVenta
    cortesia: boolean
    voucher: boolean
    creada?: string | null
    vendedor?: string | null
    estacion?: string | null
    agencia?: string | null
    tipoPago?: string | null
    total?: Importe | null
    referenciaPago?: string | null
    comprador?: string | null
    estadoFacturacion?: string
    factura?: {
      serie: string | null
      numero: number | null
      nit: string | null
      nombre: string | null
      urlPdf: string | null
    } | null
  }
}

export interface DetalleAsiento {
  asiento: { id: number; numero: number; clase: 'A' | 'B' }
  reserva: { creada: string; expiraEn: string; trayecto: TrayectoAsiento } | null
  boletos: BoletoDeAsiento[]
  /** Quien consulta audita (`bitacora.ver` en la empresa de la salida, ADR-039): ve la venta y la bitácora, no los datos del pasajero. */
  auditoria?: boolean
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
  /** Encomiendas del pasajero: se suman al total y van en la misma factura. */
  encomiendas?: Array<{ articulos: ArticuloPedido[]; observacion?: string | null }>
}

/** Comprobante de una venta (`App\Venta\Boleto\DatosBoleto`). */
export interface Comprobante {
  id: number
  token: string | null
  canal: CanalVenta
  estado: 'pendiente' | 'confirmada'
  estadoFacturacion: 'certificada' | 'pendiente' | 'no_aplica' | 'anulada'
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
  /**
   * `salida`: inicio de la ruta; `salidaOrigen`: hora donde sube el pasajero,
   * `exacta` (sube en el origen del bus), `estimada` o `desconocida` (entonces
   * es la del origen del bus, `rutaOrigen`; ADR-030).
   */
  salida: { id: number; salida: string; salidaOrigen: string; salidaPrecision?: 'exacta' | 'estimada' | 'desconocida'; rutaOrigen?: string | null; bus: string | null } | null
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
  /** Encomiendas que lleva el pasajero (cada bulto con su guía). */
  encomiendas?: DatosEncomienda[]
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
  venta?: number
  boletos?: number[]
}

export interface MovimientoAgencia {
  id: number
  fecha: string
  tipo: 'deposito' | 'bonificacion' | 'venta' | 'ajuste' | 'anulacion'
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

/** Un boleto con lo necesario para anularlo, reasignarlo o reimprimirlo (`GET /venta/boletos`). */
export interface BoletoOperable {
  id: number
  estado: string
  asiento: { id: number; numero: number; clase: 'A' | 'B' }
  pasajero: string | null
  precio: Importe | null
  observacion: string | null
  trayecto: { id: number; origenId: number; destinoId: number; origen: string; destino: string }
  salida: {
    id: number
    fecha: string
    estado: SalidaResumen['estado']
    empresa: Opcion | null
    bus: string | null
  }
  venta: {
    id: number
    canal: CanalVenta
    cortesia: boolean
    estadoFacturacion: Comprobante['estadoFacturacion']
    factura: { serie: string | null; numero: number | null } | null
    /** Boletos vivos que cubre su factura; con más de uno es conjunta (anular parte certifica otra con lo que quede). */
    boletosFactura: number[]
    cliente: Cliente | null
  }
  /** Se puede anular o reasignar todavía; si no, `motivo` dice por qué. */
  operable: boolean
  motivo: string | null
  /**
   * No es operable pero se puede con una autorización (ADR-040): sin el
   * permiso sobre su venta o con la salida ya pasada de hora. La operación se
   * intenta y el backend la usa (o responde qué pedir).
   */
  conAutorizacion?: 'permiso' | 'hora' | null
}

/** Qué puede hacer el usuario con los boletos (`GET /venta/boletos/permisos`). */
export interface PermisosBoleto {
  anular: boolean
  reasignar: boolean
}

/** Resultado de anular: por venta, lo que se anuló y lo que no. */
export interface ResultadoAnulacion {
  anulados: number[]
  fallidos: Array<ErrorVenta & { venta: number; boletos: number[] }>
  /** Factura nueva de lo que quedó de una factura conjunta; con `error`, quedó por certificar. */
  refacturadas?: Array<{ venta: number; factura: { serie: string | null; numero: number | null } | null; error: string | null }>
}

export interface PedidoReasignacion {
  /** Boletos que se reasignan; el boleto `boletos[i]` pasa al asiento `asientos[i]`. */
  boletos: number[]
  salida: number
  trayecto: number | null
  asientos: number[]
  cobrarTrayectoCompleto?: boolean
}
