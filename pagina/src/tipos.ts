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
  tiposDocumento: Opcion[]
  naciones: Opcion[]
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
  ruta: string
  /** Precio en la web (con recargo) y asientos de cada clase. */
  clases: Array<{ clase: 'A' | 'B'; precio: Importe; asientos: number }>
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

export interface ViajeCarrito {
  /** `salidaOrigen`: hora estimada donde sube el pasajero. */
  salida: { id: number; salida: string; salidaOrigen: string; llegada: string | null; empresa: string | null }
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
  tipoDocumento?: number | null
  numeroDocumento?: string
  nacionalidad?: number | null
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
  salida: { id: number; salida: string; salidaOrigen: string } | null
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
