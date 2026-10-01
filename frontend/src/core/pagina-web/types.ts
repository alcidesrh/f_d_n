/** Contratos del dashboard de la página web (`/api/pagina/*`, ADR-023). */

export interface Importe {
  centavos: number
  moneda: string
  texto: string
}

export type EstadoPago = 'autenticacion' | 'aprobado' | 'rechazado' | 'completado' | 'reembolsado' | 'reembolso_pendiente'
export type EstadoFacturacion = 'certificada' | 'pendiente' | 'no_aplica'

export interface VentaCompra {
  id: number
  codigo: string
  salidaId: number
  salida: string
  origen: string
  destino: string
  empresa: string | null
  asientos: string
  cantidad: number
  total: Importe
  facturacion: EstadoFacturacion
  errorFacturacion: string | null
  factura: { serie: string; numero: number; uuid: string; urlPdf: string | null } | null
}

export interface CompraWeb {
  id: number
  token: string
  estado: EstadoPago
  creado: string
  actualizado: string
  monto: Importe
  recargoPorciento: string
  viajes: number
  tarjeta: { marca: string | null; ultimos4: string | null }
  autorizacion: string | null
  referencia: string | null
  mensaje: string | null
  empresa: { id: number; nombre: string } | null
  comprador: { nombre: string; email: string | null; telefono: string | null; nit: string }
  ventas: VentaCompra[]
}

export interface ResumenCompras {
  compras: number
  /** De las filtradas, las completadas: lo cobrado, los promedios y el recargo son de estas. */
  completadas: number
  idaVuelta: number
  asientos: number
  /** Suma de todo lo filtrado (cualquier estado). */
  monto: Importe
  cobrado: Importe
  recargo: Importe
  promedioCompra: Importe
  promedioAsiento: Importe
  porEstado: Array<{ estado: EstadoPago; compras: number; monto: Importe }>
  porEmpresa: Array<{ empresa: string; compras: number; monto: Importe }>
}

export interface PaginaCompras {
  items: CompraWeb[]
  total: number
  pagina: number
  porPagina: number
  resumen: ResumenCompras
}

export interface ConfiguracionPagina {
  recargoPorciento: string
  ventaEnLinea: boolean
  cierreMinutos: number
  actualizadaEn: string | null
  actualizadaPor: string | null
  limites: { recargoMaximo: number; cierreMinimo: number; cierreMaximo: number }
}

export interface MensajeContacto {
  id: number
  nombre: string
  email: string
  telefono: string | null
  mensaje: string
  idioma: string
  creado: string
  leido: boolean
}

export interface Opcion {
  id: number
  nombre: string
}
