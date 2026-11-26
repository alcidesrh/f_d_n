/** Inventario de la logística (ADR-071): lo que devuelve `/api/inventario`. Cantidades y costos llegan como texto decimal. */

export type TipoAlmacen = 'estacion' | 'taller' | 'oficina' | 'bus'
export type EstadoExistencia = 'agotado' | 'bajo_minimo' | 'por_reponer' | 'bien'
export type TipoMovimiento = 'entrada' | 'salida' | 'envio' | 'recepcion' | 'ajuste'
/** Lo que se registra a mano: la recepción sale de «Recibir» un envío. */
export type TipoRegistrable = Exclude<TipoMovimiento, 'recepcion'>

export interface Lugar {
  id: number
  nombre: string
}

export interface PuedeEnAlmacen {
  mover: boolean
  ajustar: boolean
  niveles: boolean
}

export interface Almacen {
  id: number
  nombre: string
  tipo: TipoAlmacen
  tipoEtiqueta: string
  activo: boolean
  empresa: Lugar | null
  estacion: Lugar | null
  bus: { id: number; codigo: string } | null
}

/** `GET /api/inventario/almacenes`. */
export interface AlmacenVisible extends Almacen {
  faltantes: number
  puede: PuedeEnAlmacen
}

export interface ArticuloBreve {
  id: number
  codigo: string
  nombre: string
  unidad: string
  categoria?: string | null
  activo?: boolean
}

export interface Existencia {
  id: number
  articulo: ArticuloBreve
  cantidad: string
  costoPromedio: string
  valor: string
  minimo: string
  puntoReorden: string | null
  maximo: string | null
  estado: EstadoExistencia
  reponer: string
  enCamino: string
}

/** `GET /api/inventario/almacenes/{id}/existencias`. */
export interface ExistenciasAlmacen {
  almacen: Almacen & { puede: PuedeEnAlmacen }
  filas: Existencia[]
  valor: string
}

/** `GET /api/inventario/por-reponer`. */
export interface PorReponer extends Existencia {
  almacen: Almacen
}

export interface Movimiento {
  id: number
  tipo: TipoMovimiento
  etiqueta: string
  almacen: Lugar
  articulo: ArticuloBreve
  cantidad: string
  costoUnitario: string
  saldo: string
  documento: string | null
  motivo: string | null
  destino: Lugar | null
  origen: Lugar | null
  recibidoEn: string | null
  afectacion: { id: number; descripcion: string } | null
  usuario: Lugar | null
  creadoEn: string
}

/** `GET /api/inventario/en-camino`. */
export interface EnCamino extends Movimiento {
  puedeRecibir: boolean
}

export interface PaginaMovimientos {
  filas: Movimiento[]
  total: number
  pagina: number
  porPagina: number
}

/** Lo que se escribe en el formulario de registrar, antes de mandarlo. */
export interface Borrador {
  tipo: TipoRegistrable
  articulo: number | null
  cantidad: string
  costo: string
  destino: number | null
  documento: string
  motivo: string
}

/** `POST /api/inventario/movimientos`. */
export interface Registro {
  tipo: TipoRegistrable
  almacen: number
  articulo: number
  cantidad: string
  costo?: string
  destino?: number
  documento?: string
  motivo?: string
}

/** `POST /api/inventario/almacenes/{a}/articulos/{r}/niveles`: vacío = sin poner. */
export interface Niveles {
  minimo: string
  puntoReorden: string
  maximo: string
}
