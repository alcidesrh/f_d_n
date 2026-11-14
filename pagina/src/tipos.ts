/** Contratos de la API pública (`App\Controller\PublicoController`). */
import type { ElementoCroquis } from '@/core/croquis/types'

export interface Importe {
  centavos: number
  moneda: string
  texto: string
}

export interface Opcion {
  id: number
  nombre: string
}

/** Estación de origen o destino, con su departamento para agrupar. */
export interface Estacion extends Opcion {
  departamento: string | null
}

export interface Catalogos {
  cierreMinutos: number
  reservaMinutos: number
  /** Por viaje. */
  maxAsientos: number
  /** Apagada desde el dashboard: se ven los horarios pero no se vende. */
  ventaEnLinea: boolean
}

export interface Salida {
  id: number
  trayecto: number
  /** Hora estimada en el origen elegido (null si no se conoce). */
  salida: string | null
  llegada: string | null
  salidaInicio: string
  empresa: string | null
  /** Ruta del bus, `Origen → Destino` (ADR-030). */
  ruta: string
  rutaOrigen: string | null
  /** Se sube en el origen del bus: la hora de salida es exacta; si no, estimada. */
  subeEnOrigen: boolean
  /** Precio en la web (con recargo) y asientos de cada clase. */
  clases: Array<{ clase: 'A' | 'B'; precio: Importe; asientos: number; ocupados: number }>
  desde: Importe
  capacidad: number
  /** Vendidos y apartados en el tramo del cliente. */
  ocupados: number
  reservados: number
  disponibles: number
  bus: string | null
  /** Paradas intermedias entre el origen y el destino del cliente. */
  paradas: number
  cierre: string
}

export interface SalidaPublico {
  id: number
  salida: string
  empresa: string | null
  bus: string | null
  trayecto: { id: number; origen: string; destino: string }
  paradas: Array<{ id: number; nombre: string | null; hora: string | null; posicion: number }>
  croquis: ElementoCroquis[]
  precios: Array<{ clase: 'A' | 'B'; precio: Importe }>
  ocupacion: Array<{ asiento: number; estado: 'vendido' | 'reservado' | 'propio' }>
  cierre: string
  topico: string
}

/** Hora donde sube el pasajero (ADR-030); si es `desconocida`, `salidaOrigen` es la del origen del bus (`rutaOrigen`). */
export interface HoraSubida {
  salidaOrigen: string
  salidaPrecision?: 'exacta' | 'estimada' | 'desconocida'
  rutaOrigen?: string | null
}

export interface ViajeCarrito {
  salida: { id: number; salida: string; llegada: string | null; empresa: string | null } & HoraSubida
  trayecto: { id: number; origen: string; destino: string }
  asientos: Array<{ asiento: number; numero: number; clase: 'A' | 'B'; precio: Importe }>
  total: Importe
}

/** Asientos apartados al pulsar "Pagar asientos": la ida y, si hay, el regreso. */
export interface Carrito {
  token: string
  expira: string | null
  viajes: ViajeCarrito[]
  total: Importe | null
}

/** Lo que se aparta: un viaje por sentido. */
export interface ViajePedido {
  salida: number
  trayecto: number
  asientos: number[]
}

/** 409 al apartar: asientos que otro tomó, por viaje (0 = ida, 1 = regreso). */
export interface Conflicto {
  viaje: number
  salida: number
  asientos: number[]
  numeros: number[]
}

export interface Comprador {
  nombre: string
  apellido?: string
  email: string
  telefono?: string
  nit?: string
  /** A nombre de quién sale la factura si no es el comprador; con NIT manda la razón social de la SAT. */
  nombreFactura?: string
}

/** Departamento, estado o provincia (`codigo`: el de 2 letras que piden los bancos en EE. UU. y Canadá). */
export interface Region {
  id: number
  codigo: string | null
  nombre: string
}

export interface DatosTarjeta {
  numero: string
  expira: string
  cvv: string
  titular: string
}

export interface Compra {
  id: number
  token: string | null
  estadoFacturacion: 'certificada' | 'pendiente' | 'no_aplica'
  codigoBarras: string
  empresa: { nombre: string; nit: string | null } | null
  factura: { numero: number; serie: string; uuid: string; urlPdf: string | null } | null
  cliente: { nombre: string; nit: string; email: string | null } | null
  salida: ({ id: number; salida: string } & HoraSubida) | null
  origen: { nombre: string; direccion: string | null } | null
  destino: { nombre: string; direccion: string | null } | null
  boletos: Array<{ asiento: number; clase: 'A' | 'B'; precio: Importe | null }>
  total: Importe
}

/** Dirección de facturación de la tarjeta (la registrada en el banco). */
export interface DatosFacturacion {
  /** ISO 3166-1 alfa-2. */
  pais: string
  region?: string
  ciudad: string
  direccion: string
  codigoPostal?: string
}

/** Datos del navegador que 3-D Secure usa para evaluar el riesgo. */
export interface DatosNavegador {
  anchoPantalla: number
  altoPantalla: number
  profundidadColor: number
  diferenciaHoraria: number
  idioma: string
}

/**
 * Respuesta del pago: completado o el paso que hace el navegador antes de
 * volver a enviar el pago con `continuar`.
 */
export type ResultadoPago =
  | { estado: 'completado'; compras: Compra[] }
  /** Recolección de datos del dispositivo: POST oculto; aviso de `origenes`. */
  | { estado: 'dispositivo'; url: string; campos: Record<string, string>; origenes: string[] }
  /** Desafío 3-D Secure: POST en un iframe visible de `ancho`×`alto`. */
  | { estado: 'autenticacion'; url: string; campos: Record<string, string>; ancho: string; alto: string }

export interface SolicitudPago {
  comprador: Comprador
  tarjeta: DatosTarjeta
  facturacion: DatosFacturacion
  navegador: DatosNavegador
  continuar?: Record<string, string>
}

export interface ErrorApi {
  error: string
  codigo: string
}

/** «Mis compras»: reglas con las que el cliente modifica (`ConfiguracionPagina`). */
export interface PoliticaModificacion {
  modificacionesPorAsiento: number
  minutosAntes: number
  penalizacion: Importe | null
  penalizacionPorAsiento: boolean
}

export interface BoletoViaje {
  id: number
  asiento: number
  asientoId: number
  clase: 'A' | 'B'
  precio: Importe | null
  estado: string
  salida: { id: number; llegada: string | null } & HoraSubida
  trayecto: { id: number; origen: Opcion; destino: Opcion }
  /** Veces que el cliente ya cambió este asiento. */
  modificaciones: number
  modificable: boolean
  motivo: string | null
}

/** Un viaje comprado en línea (una venta: la ida o el regreso). */
export interface ViajeComprado {
  id: number
  compra: number
  sentido: 'ida' | 'regreso' | null
  comprada: string
  /** Salida donde sube el pasajero (la del primer asiento). */
  fecha: string
  futuro: boolean
  anulado: boolean
  empresa: string | null
  origen: string
  destino: string
  codigoBarras: string
  boletos: BoletoViaje[]
  total: Importe
  cargoModificacion: Importe | null
  estadoFacturacion: string
  factura: { serie: string | null; numero: number | null; uuid: string; anulada: boolean; urlPdf: string | null } | null
  descargarFactura: boolean
  editable: boolean
  limiteModificacion: string | null
  motivo: string | null
}

export interface MisCompras {
  correo: string
  futuros: ViajeComprado[]
  pasados: ViajeComprado[]
  politica: PoliticaModificacion
  /** Llave renovada: reemplaza a la guardada. */
  llave: string
}

/** A dónde pasa un asiento (o dónde se agrega uno). */
export interface DestinoAsiento {
  salida: number
  trayecto: number
  asiento: number
}

export interface PedidoModificacion {
  cambios: Array<DestinoAsiento & { boleto: number }>
  nuevos: DestinoAsiento[]
}

export interface LugarCotizado extends HoraSubida {
  salida: number
  trayecto: number
  origen: string
  destino: string
  asiento: number
  asientoId: number
  clase: 'A' | 'B'
}

export interface CotizacionModificacion {
  cambios: Array<{ boleto: number; desde: LugarCotizado; hacia: LugarCotizado; pagado: Importe; nuevo: Importe; diferencia: Importe; sinReembolso: Importe | null }>
  nuevos: Array<LugarCotizado & { precio: Importe }>
  diferencias: Importe
  asientosNuevos: Importe
  sinReembolso: Importe
  penalizacion: Importe
  total: Importe
  requierePago: boolean
}

export type ResultadoModificacion =
  | { estado: 'completado'; viaje: number; cobrado: Importe; refacturada: boolean; errorFactura: string | null }
  | { estado: 'dispositivo'; token: string; url: string; campos: Record<string, string>; origenes: string[] }
  | { estado: 'autenticacion'; token: string; url: string; campos: Record<string, string>; ancho: string; alto: string }

export interface CobroModificacion {
  token?: string
  tarjeta: DatosTarjeta
  facturacion: DatosFacturacion
  navegador: DatosNavegador
  continuar?: Record<string, string>
}
