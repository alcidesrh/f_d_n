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

/** Cliente elegido en un filtro (por id; la etiqueta solo se muestra). */
export interface ClienteFiltro {
  id: number
  label: string
}

export interface FiltroBoletos {
  /** `AAAA-MM-DD`, ambos incluidos; entra el boleto cuya salida o venta cae en el rango. */
  desde: string | null
  hasta: string | null
  id: number | null
  reasignado: number | null
  salida: number | null
  documento: TipoDocumento | null
  /** Vacío = todos. */
  estados: EstadoBoleto[]
  asiento: number | null
  clase: 'A' | 'B' | null
  serie: string
  factura: number | null
  origen: string
  destino: string
  referencia: string
  autorizacion: string
  clienteDocumento: ClienteFiltro | null
  clienteBoleto: ClienteFiltro | null
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
    reasignado: null,
    salida: null,
    documento: null,
    estados: [],
    asiento: null,
    clase: null,
    serie: '',
    factura: null,
    origen: '',
    destino: '',
    referencia: '',
    autorizacion: '',
    clienteDocumento: null,
    clienteBoleto: null,
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
  poner('reasignado', f.reasignado)
  poner('salida', f.salida)
  poner('documento', f.documento)
  poner('estado', f.estados.join(','))
  poner('asiento', f.asiento)
  poner('clase', f.clase)
  poner('serie', f.serie)
  poner('factura', f.factura)
  poner('origen', f.origen)
  poner('destino', f.destino)
  poner('referencia', f.referencia)
  poner('autorizacion', f.autorizacion)
  poner('clienteDocumento', f.clienteDocumento?.id)
  poner('clienteBoleto', f.clienteBoleto?.id)
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

const SALIDA_CANCELABLE = ['programada', 'abordando', 'iniciada']
const SALIDA_CHEQUEABLE = ['abordando', 'iniciada']

/**
 * Qué acciones del panel admite el boleto elegido. Son las reglas del
 * backend que se pueden ver desde la fila; el backend vuelve a validarlas
 * (estación, ventana de chequeo, venta completa, etc.).
 */
export function disponibilidad(b: BoletoFila | null, puede: PuedeBoleto, ahora: Date): Record<AccionBoleto, EstadoAccion> {
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

  const estado = etiquetaEstado(b.estado).etiqueta.toLowerCase()
  const enProceso = b.ventaConfirmada ? null : 'La venta todavía se está procesando'
  const yaSalio = new Date(b.fecha).getTime() <= ahora.getTime() ? 'La salida ya pasó su hora' : null
  const soloEmitido = b.estado === 'emitido' ? null : `El boleto está ${estado}: solo se opera un boleto emitido`
  const sinValor = b.estado === 'anulado' || b.estado === 'reasignado' ? `El boleto está ${estado}` : null

  return {
    consultar: sin(null),
    bitacora: sin(null),
    cancelar: con(
      puede.cancelar,
      enProceso ??
        (['emitido', 'chequeado'].includes(b.estado) ? null : `El boleto está ${estado}: solo se cancela un boleto emitido o chequeado`) ??
        (SALIDA_CANCELABLE.includes(b.salidaEstado) ? null : `La salida está ${b.salidaEstado}`),
    ),
    // El backend comprueba además que el asiento siga libre.
    revertir: con(
      puede.cancelar,
      (b.estado === 'cancelado' ? null : `El boleto está ${estado}: solo se revierte un boleto cancelado`) ??
        (b.documento.tipo === 'cortesia' ? 'Las cortesías canceladas no se revierten' : null) ??
        (b.canal === 'agencia' ? 'Los boletos de agencia cancelados no se revierten' : null) ??
        (SALIDA_CANCELABLE.includes(b.salidaEstado) ? null : `La salida está ${b.salidaEstado}`),
    ),
    anular: con(puede.anular, enProceso ?? soloEmitido ?? yaSalio),
    reasignar: con(puede.reasignar, enProceso ?? soloEmitido ?? yaSalio),
    chequear: con(puede.chequear, enProceso ?? soloEmitido ?? (SALIDA_CHEQUEABLE.includes(b.salidaEstado) ? null : 'Se chequea con la salida abordando o iniciada')),
    correo: sin(enProceso ?? sinValor),
    factura: sin(enProceso ?? sinValor),
    chat: sin(null),
  }
}
