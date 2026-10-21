/**
 * Listado de boletos (espejo de `App\Venta\Boleto\Listado\FiltroBoletos`):
 * filtros del formulario → query string, etiquetas de estado y documento, y
 * qué acciones del panel admite el boleto elegido. Funciones puras.
 */
import type { BoletoFila, EstadoBoleto, OrdenBoletos, PuedeBoleto, TipoDocumento } from './types'

type Severidad = 'success' | 'info' | 'warn' | 'danger' | 'secondary' | 'contrast'

export const ESTADOS: Array<{ valor: EstadoBoleto; etiqueta: string; severidad: Severidad }> = [
  { valor: 'emitido', etiqueta: 'Emitido', severidad: 'info' },
  { valor: 'chequeado', etiqueta: 'Chequeado', severidad: 'success' },
  { valor: 'transito', etiqueta: 'En tránsito', severidad: 'warn' },
  { valor: 'finalizado', etiqueta: 'Finalizado', severidad: 'secondary' },
  { valor: 'cancelado', etiqueta: 'Cancelado', severidad: 'warn' },
  { valor: 'anulado', etiqueta: 'Anulado', severidad: 'danger' },
  { valor: 'reasignado', etiqueta: 'Reasignado', severidad: 'contrast' },
  { valor: 'pospuesto', etiqueta: 'Pospuesto', severidad: 'warn' },
]

export const etiquetaEstado = (e: string) => ESTADOS.find((x) => x.valor === e) ?? { valor: e, etiqueta: e, severidad: 'secondary' as const }

export const DOCUMENTOS: Array<{ valor: TipoDocumento; etiqueta: string }> = [
  { valor: 'factura', etiqueta: 'Factura' },
  { valor: 'pendiente', etiqueta: 'Factura pendiente' },
  { valor: 'sin_factura', etiqueta: 'Sin factura' },
  { valor: 'agencia', etiqueta: 'Agencia' },
  { valor: 'cortesia', etiqueta: 'Cortesía' },
  { valor: 'voucher', etiqueta: 'Voucher' },
]

/** "Factura - SERIE 123", "Voucher", … (la columna "Documento" del legado). */
export function documentoTexto(d: BoletoFila['documento']): string {
  const etiqueta = DOCUMENTOS.find((x) => x.valor === d.tipo)?.etiqueta ?? d.tipo
  return d.tipo === 'factura' && d.numero !== null ? `${etiqueta} - ${[d.serie, d.numero].filter(Boolean).join(' ')}` : etiqueta
}

export interface FiltroBoletos {
  /** `AAAA-MM-DD`, ambos incluidos; entra el boleto cuya salida o venta cae en el rango. */
  desde: string | null
  hasta: string | null
  id: number | null
  salida: number | null
  documento: TipoDocumento | null
  /** Vacío = todos. */
  estados: EstadoBoleto[]
  asiento: number | null
  clase: 'A' | 'B' | null
  serie: string
  factura: number | null
  /** Id de la estación donde sube. */
  origen: number | null
  /** Id de la estación donde baja. */
  destino: number | null
}

export interface Pagina {
  pagina: number
  porPagina: number
  orden: OrdenBoletos
  dir: 'asc' | 'desc'
}

/** Sin fechas el backend usa 30 días antes y después de hoy (como el legado). */
export function filtroInicial(desde: string | null = null, hasta: string | null = null): FiltroBoletos {
  return {
    desde,
    hasta,
    id: null,
    salida: null,
    documento: null,
    estados: [],
    asiento: null,
    clase: null,
    serie: '',
    factura: null,
    origen: null,
    destino: null,
  }
}

export const paginaInicial = (): Pagina => ({ pagina: 1, porPagina: 15, orden: 'id', dir: 'desc' })

/** Query string para `GET /api/boletos`; sin los valores vacíos. */
export function aQuery(f: FiltroBoletos, p: Pagina): string {
  const q = new URLSearchParams()
  const poner = (clave: string, v: string | number | null | undefined) => {
    const t = v === null || v === undefined ? '' : String(v).trim()
    if (t !== '') q.set(clave, t)
  }
  poner('desde', f.desde)
  poner('hasta', f.hasta)
  poner('id', f.id)
  poner('salida', f.salida)
  poner('documento', f.documento)
  poner('estado', f.estados.join(','))
  poner('asiento', f.asiento)
  poner('clase', f.clase)
  poner('serie', f.serie)
  poner('factura', f.factura)
  poner('origen', f.origen)
  poner('destino', f.destino)
  q.set('pagina', String(p.pagina))
  q.set('porPagina', String(p.porPagina))
  q.set('orden', p.orden)
  q.set('dir', p.dir)
  return q.toString()
}

/** Cuántos filtros (además del rango de fechas) hay aplicados. */
export function filtrosActivos(f: FiltroBoletos): number {
  const { desde: _d, hasta: _h, estados, ...resto } = f
  return Object.values(resto).filter((v) => v !== null && v !== '').length + (estados.length ? 1 : 0)
}

/** Clic en una columna ordenable: la misma alterna la dirección; otra empieza descendente. */
export function alternarOrden(p: Pagina, orden: OrdenBoletos): Pagina {
  return { ...p, pagina: 1, orden, dir: p.orden === orden && p.dir === 'desc' ? 'asc' : 'desc' }
}

export type AccionBoleto = 'consultar' | 'bitacora' | 'cancelar' | 'revertir' | 'anular' | 'chequear' | 'reasignar' | 'correo' | 'factura' | 'chat'

export interface EstadoAccion {
  /** Se muestra (las que exigen un permiso que no tiene, no). */
  visible: boolean
  habilitada: boolean
  /** Por qué no se puede, para el tooltip. */
  motivo: string | null
}

/**
 * Qué acciones del panel admite el boleto elegido. Las que cambian su
 * estado las decide el backend (`b.acciones`, el workflow del boleto,
 * ADR-037); aquí solo se suman el permiso de quien mira y las que no son
 * transiciones (correo, factura). Al operar, el backend vuelve a validar
 * lo que depende de quién y de la base (estación, asiento libre, venta
 * completa).
 */
export function disponibilidad(b: BoletoFila | null, puede: PuedeBoleto): Record<AccionBoleto, EstadoAccion> {
  const sin = (motivo: string | null, visible = true): EstadoAccion => ({ visible, habilitada: motivo === null, motivo })
  const con = (permiso: boolean, motivo: string | null) => sin(permiso ? motivo : 'Sin permiso', permiso)

  if (!b) {
    const elija = 'Elija un boleto de la lista'
    return {
      consultar: sin(elija),
      bitacora: sin(elija),
      cancelar: con(puede.cancelar, elija),
      revertir: con(puede.cancelar, elija),
      anular: con(puede.anular, elija),
      chequear: con(puede.chequear, elija),
      reasignar: con(puede.reasignar, elija),
      correo: sin(elija),
      factura: sin(elija),
      chat: sin(elija),
    }
  }

  const enProceso = b.ventaConfirmada ? null : 'La venta todavía se está procesando'
  const sinValor = b.estado === 'anulado' || b.estado === 'reasignado' ? `El boleto está ${etiquetaEstado(b.estado).etiqueta.toLowerCase()}` : null

  return {
    consultar: sin(null),
    bitacora: sin(null),
    cancelar: con(puede.cancelar, b.acciones.cancelar),
    // Revertir es parte de cancelar (mismo permiso); el backend comprueba además que el asiento siga libre.
    revertir: con(puede.cancelar, b.acciones.revertir),
    anular: con(puede.anular, b.acciones.anular),
    reasignar: con(puede.reasignar, b.acciones.reasignar),
    // La estación donde sube la comprueba el backend al chequear.
    chequear: con(puede.chequear, b.acciones.chequear),
    correo: sin(enProceso ?? sinValor),
    factura: sin(enProceso ?? sinValor),
    chat: sin(null),
  }
}
