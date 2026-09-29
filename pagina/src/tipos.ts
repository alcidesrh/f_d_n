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

export interface Catalogos {
  tiposDocumento: Opcion[]
  naciones: Opcion[]
  cierreMinutos: number
  reservaMinutos: number
  maxAsientos: number
}

export interface Salida {
  id: number
  trayecto: number
  /** Hora estimada en el origen elegido (null si no se conoce). */
  salida: string | null
  llegada: string | null
  salidaRecorrido: string
  empresa: string | null
  ruta: string
  clases: Array<{ clase: 'A' | 'B'; precio: Importe }>
  desde: Importe
  disponibles: number
  cierre: string
}

export interface RecorridoPublico {
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

export interface Carrito {
  token: string
  expira: string | null
  /** `salidaOrigen`: hora estimada donde sube el pasajero. */
  recorrido?: { id: number; salida: string; salidaOrigen: string; empresa: string | null }
  trayecto?: { id: number; origen: string; destino: string }
  asientos: Array<{ asiento: number; numero: number; clase: 'A' | 'B'; precio: Importe }>
  total: Importe | null
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
  factura: { numero: number; serie: string; uuid: string } | null
  cliente: { nombre: string; nit: string; email: string | null } | null
  recorrido: { id: number; salida: string; salidaOrigen: string } | null
  origen: { nombre: string; direccion: string | null } | null
  destino: { nombre: string; direccion: string | null } | null
  boletos: Array<{ asiento: number; clase: 'A' | 'B'; precio: Importe | null }>
  total: Importe
}

export type ResultadoPago =
  | { estado: 'completado'; compra: Compra }
  | { estado: 'autenticacion'; url: string; campos: Record<string, string> }

export interface ErrorApi {
  error: string
  codigo: string
}
