/** Encomiendas (ADR-035): espejo de `App\Encomienda` y `EncomiendaController`. */
import type { Importe, Opcion } from '@/core/venta/types'

export type EstadoEncomienda = 'recibida' | 'embarcada' | 'transito' | 'desembarcada' | 'entregada' | 'anulada'
export type ModalidadEncomienda = 'pagada' | 'por_cobrar' | 'interna' | 'con_boleto'
export type TipoArticulo = 'paquete' | 'especial'

export interface ClienteResumen {
  id: number
  nombre: string
  nit: string
  telefono: string | null
  documento: string | null
}

export interface EspecialOpcion {
  id: number
  nombre: string
  descripcion: string | null
  precio: Importe
  permitePorCobrar: boolean
}

export interface EstacionOpcion {
  id: number
  nombre: string
  direccion?: string | null
  departamento?: string | null
}

export interface PermisosEncomienda {
  ver: boolean
  registrar: boolean
  interna: boolean
  embarcar: boolean
  entregar: boolean
  anular: boolean
  /** Administrar el catálogo de especiales. */
  especiales: boolean
  sinFactura: boolean
}

export interface ContextoEncomienda {
  usuario: { id: number; username: string }
  estacion: EstacionOpcion | null
  empresa: Opcion | null
  permisos: PermisosEncomienda
  empresas: Array<Opcion & { alias?: string | null }>
  estaciones: EstacionOpcion[]
  especiales: EspecialOpcion[]
  tiposPago: Opcion[]
  monedas: Array<{ id: number; sigla: string; nombre: string }>
  porPagina: number[]
}

/** Un artículo como se pide al cotizar o registrar. */
export interface ArticuloPedido {
  tipo: TipoArticulo
  cantidad: number
  /** Libras por bulto (paquete). */
  peso?: number | null
  /** Id del especial (especial). */
  especial?: number | null
  descripcion?: string | null
  alto?: number | null
  ancho?: number | null
  largo?: number | null
}

export interface CotizacionEncomienda {
  articulos: Array<{ cantidad: number; tarifa: Importe; recargo: Importe; importe: Importe }>
  bultos: number
  subtotal: Importe
  porcentajeDescuento: number
  descuento: Importe
  total: Importe
}

export interface RutaOpcion {
  id: number
  nombre: string
  /** Empresas con salidas de la ruta esta semana. */
  empresas: Opcion[]
}

export interface PedidoEncomienda {
  token: string
  empresa: number
  origen: number | null
  destino: number
  ruta: number | null
  remitente: number
  destinatario: number
  modalidad: Exclude<ModalidadEncomienda, 'con_boleto'>
  articulos: ArticuloPedido[]
  observacion?: string | null
  tipoPago?: number | null
  moneda?: number | null
  clienteFactura?: number | null
  sinFacturaElectronica?: boolean
}

export interface FacturaEncomienda {
  numero: number
  serie: string
  uuid: string
  fechaCertificacion: string | null
  certificador: string | null
  certificadorNit: string | null
  receptorNit: string
  receptorNombre: string
  anulada: boolean
}

export interface ArticuloEncomienda {
  id: number
  tipo: TipoArticulo
  especial: Opcion | null
  descripcion: string | null
  cantidad: number
  peso: number | null
  alto: number | null
  ancho: number | null
  largo: number | null
  /** «Paquete 12 lb ×3 · ropa». */
  resumen: string
  tarifa: Importe
  recargo: Importe
  importe: Importe
}

/** Una encomienda completa (detalle, recibo y guías). */
export interface DatosEncomienda {
  id: number
  codigo: string
  creada: string
  estado: EstadoEncomienda
  estadoEtiqueta: string
  modalidad: ModalidadEncomienda
  modalidadEtiqueta: string
  /** El recibo dice «Por cobrar» hasta que la paga quien la retira. */
  porCobrar: boolean
  empresa: { id: number; nombre: string; nit: string | null; direccion: string | null; telefono: string | null } | null
  estacion: { id: number; nombre: string; direccion: string | null } | null
  origen: { id: number; nombre: string; direccion: string | null }
  /** `alias`: la sigla que va en grande en la guía (GUA, SEP). */
  destino: { id: number; nombre: string; direccion: string | null; alias: string | null }
  ruta: Opcion | null
  ubicacion: Opcion | null
  salida: { id: number; fecha: string; ruta: string | null; bus: string | null; estado: string } | null
  remitente: ClienteResumen
  destinatario: ClienteResumen
  receptor: ClienteResumen | null
  entregadaEn: string | null
  articulos: ArticuloEncomienda[]
  bultos: number
  descuento: Importe
  total: Importe
  estadoFacturacion: 'certificada' | 'pendiente' | 'no_aplica' | 'anulada'
  numeroAcceso: number | null
  contingencia: boolean
  factura: FacturaEncomienda | null
  clienteFactura: ClienteResumen | null
  tipoPago: string | null
  venta: number | null
  boleto: { id: number; asiento: number | null } | null
  usuario: string | null
  observacion: string | null
  motivoAnulacion: string | null
}

/** Una fila del listado. */
export interface FilaEncomienda {
  id: number
  codigo: string
  creada: string
  estado: EstadoEncomienda
  modalidad: ModalidadEncomienda
  porCobrar: boolean
  empresa: { id: number; nombre: string; alias: string | null }
  origen: Opcion
  destino: Opcion
  ubicacion: Opcion | null
  salida: { id: number; fecha: string; bus: string | null } | null
  /** La ruta por la que viaja (la elegida al registrarla). */
  ruta: Opcion | null
  remitente: ClienteResumen
  destinatario: ClienteResumen
  bultos: number
  contenido: string
  total: Importe
  estadoFacturacion: DatosEncomienda['estadoFacturacion']
  venta: number | null
  usuario: string | null
}

export interface PaginaEncomiendas {
  items: FilaEncomienda[]
  total: number
  pagina: number
  porPagina: number
}

/** Fila de «procesar por salida», con lo que significa para la estación. */
export interface FilaProceso extends FilaEncomienda {
  /** Su destino es esta estación. */
  bajaAqui: boolean
  /** Esta salida la lleva hasta su destino (si no, hará transbordo). */
  llegaADestino: boolean
}

export interface SalidaEstacion {
  id: number
  fecha: string
  /** Hora en esta estación. */
  hora: string
  ruta: string | null
  origen: string | null
  destino: string | null
  bus: string | null
  empresa: (Opcion & { alias: string | null }) | null
  estado: string
  aBordo: number
  bajanAqui: number
  porEmbarcar: number
  /** Sigue después de esta parada: se le puede subir algo aquí. */
  sigue: boolean
  /** Con `destino` en la consulta: si lleva hasta ese destino. */
  llegaADestino: boolean | null
}

export interface ProcesoSalida {
  salida: { id: number; fecha: string; ruta: string | null; bus: string | null; estado: string; empresa: string | null }
  estacion: Opcion
  paradas: Opcion[]
  pasaPorAqui: boolean
  aBordo: FilaProceso[]
  enBodega: FilaProceso[]
}

export interface Fallida {
  id: number
  codigo: string
  error: string
}

export interface ResultadoEmbarque {
  embarcadas: number[]
  fallidas: Fallida[]
}

export interface ResultadoDesembarque {
  desembarcadas: number[]
  fallidas: Fallida[]
}

export interface EntregaPedido {
  receptor?: number | null
  clienteFactura?: number | null
  tipoPago?: number | null
  moneda?: number | null
  sinFacturaElectronica?: boolean
}

/** Una que no se entregó: con `facturacion`, falló el certificador (y `recuperable` si vale reintentar). */
export interface FallidaEntrega extends Fallida {
  facturacion?: boolean
  recuperable?: boolean
}

export interface ResultadoEntrega {
  entregadas: number[]
  fallidas: FallidaEntrega[]
}

/** Un especial del catálogo, para administrarlo. */
export interface EspecialAdmin extends EspecialOpcion {
  activo: boolean
  /** Vino del legado (`app:encomienda:legado`). */
  legado: boolean
}

export interface EspecialPedido {
  nombre: string
  descripcion: string | null
  /** En quetzales. */
  precio: number
  permitePorCobrar: boolean
  activo: boolean
}
