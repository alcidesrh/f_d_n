/**
 * Reglas puras de las pantallas de encomiendas (ADR-035): etiquetas, qué
 * acción admite una encomienda y por qué no (lo decide el backend, ADR-038),
 * validación de artículos, la consulta del listado y las guías a imprimir.
 */
import type { ArticuloPedido, DatosEncomienda, EstadoEncomienda, FilaEncomienda, ModalidadEncomienda, PermisosEncomienda } from './types'

export const ESTADOS: Array<{ valor: EstadoEncomienda; etiqueta: string; inicial: string }> = [
  { valor: 'recibida', etiqueta: 'Recibida', inicial: 'R' },
  { valor: 'embarcada', etiqueta: 'Embarcada', inicial: 'E' },
  { valor: 'transito', etiqueta: 'En tránsito', inicial: 'T' },
  { valor: 'desembarcada', etiqueta: 'Desembarcada', inicial: 'D' },
  { valor: 'entregada', etiqueta: 'Entregada', inicial: 'En' },
  { valor: 'anulada', etiqueta: 'Anulada', inicial: 'A' },
]

export const MODALIDADES: Array<{ valor: ModalidadEncomienda; etiqueta: string; ayuda: string }> = [
  { valor: 'pagada', etiqueta: 'Pagada', ayuda: 'Se cobra y factura ahora' },
  { valor: 'por_cobrar', etiqueta: 'Por cobrar', ayuda: 'La paga quien la retira' },
  { valor: 'interna', etiqueta: 'Interna', ayuda: 'Envío de la empresa, sin cobro' },
  { valor: 'con_boleto', etiqueta: 'Con boleto', ayuda: 'La lleva un pasajero' },
]

export const etiquetaEstado = (e: string) => ESTADOS.find((x) => x.valor === e)?.etiqueta ?? e
export const inicialEstado = (e: string) => ESTADOS.find((x) => x.valor === e)?.inicial ?? e.charAt(0).toUpperCase()
export const etiquetaModalidad = (m: string) => MODALIDADES.find((x) => x.valor === m)?.etiqueta ?? m

export type AccionEncomienda = 'consultar' | 'embarcar' | 'desembarcar' | 'entregar' | 'anular' | 'recibo' | 'guias' | 'bitacora'

export interface EstadoAccion {
  visible: boolean
  habilitada: boolean
  /** Por qué no se puede (tooltip). */
  motivo: string | null
}

const si: EstadoAccion = { visible: true, habilitada: true, motivo: null }
const no = (motivo: string): EstadoAccion => ({ visible: true, habilitada: false, motivo })
const oculta: EstadoAccion = { visible: false, habilitada: false, motivo: null }

/**
 * Qué se puede hacer con la encomienda elegida. Las que cambian su estado
 * las decide el backend (`f.acciones`, el workflow de la encomienda,
 * ADR-038); aquí solo se suman el permiso y la estación de quien mira
 * (`estacion`, null = oficina, opera en cualquiera). Al operar, el backend
 * vuelve a exigir todo, y además la salida en que se embarca.
 */
export function disponibilidad(
  f: FilaEncomienda | null,
  puede: PermisosEncomienda,
  estacion: number | null,
): Record<AccionEncomienda, EstadoAccion> {
  const sinElegir = no('Elija una encomienda')
  if (!f) {
    return {
      consultar: sinElegir,
      embarcar: puede.embarcar ? sinElegir : oculta,
      desembarcar: puede.embarcar ? sinElegir : oculta,
      entregar: puede.entregar ? sinElegir : oculta,
      anular: puede.anular ? sinElegir : oculta,
      recibo: sinElegir,
      guias: sinElegir,
      bitacora: sinElegir,
    }
  }
  /** La estación donde se opera, si no es la de quien mira. */
  const otra = (id: number | undefined, mensaje: string) => (estacion === null || estacion === id ? null : mensaje)
  /** Con el permiso: primero lo que dice el backend, luego la estación. */
  const accion = (permiso: boolean, motivo: string | null, deOtra: string | null = null): EstadoAccion =>
    !permiso ? oculta : motivo !== null ? no(motivo) : deOtra !== null ? no(deOtra) : si

  return {
    consultar: si,
    embarcar: accion(puede.embarcar, f.acciones.embarcar, otra(f.ubicacion?.id, `Está en la bodega de ${f.ubicacion?.nombre ?? 'otra estación'}`)),
    // La estación donde se baja la comprueba el backend (una parada de su salida).
    desembarcar: accion(puede.embarcar, f.acciones.desembarcar),
    entregar: accion(puede.entregar, f.acciones.entregar, otra(f.destino.id, `La entrega la estación ${f.destino.nombre}`)),
    anular: accion(puede.anular, f.acciones.anular, otra(f.origen.id, `La anula la estación ${f.origen.nombre}`)),
    recibo: si,
    guias: f.estado === 'anulada' ? no('Está anulada') : si,
    bitacora: si,
  }
}

/**
 * Error del primer artículo incompleto (o null si todos sirven para cotizar).
 */
export function errorArticulos(articulos: ArticuloPedido[]): string | null {
  if (!articulos.length) return 'Agregue al menos un artículo.'
  for (const [i, a] of articulos.entries()) {
    const n = i + 1
    if (!Number.isInteger(a.cantidad) || a.cantidad < 1 || a.cantidad > 100) return `Artículo ${n}: la cantidad va de 1 a 100.`
    if (a.tipo === 'paquete' && !(Number(a.peso) > 0)) return `Artículo ${n}: indique el peso en libras.`
    if (a.tipo === 'especial' && !a.especial) return `Artículo ${n}: elija el tipo de especial.`
  }
  return null
}

/** Artículo nuevo vacío. */
export const articuloNuevo = (tipo: ArticuloPedido['tipo'] = 'paquete'): ArticuloPedido => ({ tipo, cantidad: 1, peso: null, especial: null, descripcion: null, alto: null, ancho: null, largo: null })

/** Lo que se manda al backend: sin los campos que no van con su tipo. */
export function limpiarArticulos(articulos: ArticuloPedido[]): ArticuloPedido[] {
  return articulos.map((a) =>
    a.tipo === 'especial'
      ? { tipo: 'especial', cantidad: a.cantidad, especial: a.especial, descripcion: a.descripcion?.trim() || null }
      : { tipo: 'paquete', cantidad: a.cantidad, peso: a.peso, descripcion: a.descripcion?.trim() || null, alto: a.alto || null, ancho: a.ancho || null, largo: a.largo || null },
  )
}

export const bultos = (articulos: Array<{ cantidad: number }>) => articulos.reduce((n, a) => n + (Number(a.cantidad) || 0), 0)

// —— Listado ——

export interface FiltroEncomiendas {
  q: string
  estados: EstadoEncomienda[]
  modalidad: ModalidadEncomienda | null
  origen: number | null
  destino: number | null
  ubicacion: number | null
  /** AAAA-MM-DD (fecha de registro). */
  desde: string | null
  hasta: string | null
}

export const filtroInicial = (): FiltroEncomiendas => ({ q: '', estados: [], modalidad: null, origen: null, destino: null, ubicacion: null, desde: null, hasta: null })

export function aQuery(f: FiltroEncomiendas, pagina: number, porPagina: number): string {
  const p = new URLSearchParams()
  if (f.q.trim()) p.set('q', f.q.trim())
  if (f.estados.length) p.set('estados', f.estados.join(','))
  if (f.modalidad) p.set('modalidad', f.modalidad)
  for (const k of ['origen', 'destino', 'ubicacion'] as const) if (f[k]) p.set(k, String(f[k]))
  if (f.desde) p.set('desde', f.desde)
  if (f.hasta) p.set('hasta', f.hasta)
  p.set('pagina', String(pagina))
  p.set('porPagina', String(porPagina))
  return p.toString()
}

/** Lo escaneado o escrito (`00000123`, `123`) como id; null si no es un código. */
export function codigoAId(texto: string): number | null {
  const t = texto.trim()
  if (!/^\d{1,10}$/.test(t)) return null
  const id = Number(t)
  return id > 0 ? id : null
}

// —— Guías ——

export interface Guia {
  /** Número de bulto (1…N). */
  numero: number
  total: number
  /** Lo que es ese bulto. */
  articulo: string
}

/** Una guía por bulto: «Bulto k de N» con el artículo al que pertenece. */
export function guias(e: Pick<DatosEncomienda, 'articulos'>): Guia[] {
  const total = bultos(e.articulos)
  const lista: Guia[] = []
  for (const a of e.articulos) {
    const nombre = a.tipo === 'especial' ? (a.especial?.nombre ?? 'Especial') : `Paquete${a.peso ? ` ${a.peso} lb` : ''}`
    for (let k = 0; k < a.cantidad; k++) lista.push({ numero: lista.length + 1, total, articulo: [nombre, a.descripcion].filter(Boolean).join(' · ') })
  }
  return lista
}

/**
 * Sigla del destino para la guía cuando la estación no tiene `alias`: con
 * tres palabras o más, sus iniciales («Cruce de Morales» → CDM); si no, las
 * tres primeras letras («Guatemala» → GUA, «Poptun, Peten» → POP).
 */
export function abreviar(nombre: string): string {
  const palabras = nombre
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .split(/[\s,.-]+/)
    .filter(Boolean)
  if (palabras.length >= 3) return palabras.slice(0, 3).map((p) => p[0]).join('').toUpperCase()
  return (palabras[0] ?? '').slice(0, 3).toUpperCase()
}

/** Lo marcado para entregar: cuántas, bultos, cuánto se cobra (centavos) y a qué destinatarios van. */
export function resumenEntrega(filas: readonly FilaEncomienda[], marcadas: readonly number[]) {
  const elegidas = filas.filter((f) => marcadas.includes(f.id))
  const porCobrar = elegidas.filter((f) => f.porCobrar)
  return {
    cantidad: elegidas.length,
    bultos: bultos(elegidas.map((f) => ({ cantidad: f.bultos }))),
    porCobrar: porCobrar.length,
    cobrar: porCobrar.reduce((n, f) => n + f.total.centavos, 0),
    destinatarios: [...new Map(elegidas.map((f) => [f.destinatario.id, f.destinatario])).values()],
  }
}

/** Días de calendario desde que se registró (0 = hoy). */
export function antiguedad(creada: string, hoy: Date = new Date()): number {
  const d = new Date(creada)
  const a = Date.UTC(d.getFullYear(), d.getMonth(), d.getDate())
  const b = Date.UTC(hoy.getFullYear(), hoy.getMonth(), hoy.getDate())
  return Math.max(0, Math.round((b - a) / 86_400_000))
}

/** Agrupa por destino, en orden alfabético; dentro, lo más antiguo primero. */
export function porDestino<T extends Pick<FilaEncomienda, 'id' | 'destino'>>(filas: readonly T[]): Array<{ destino: T['destino']; filas: T[] }> {
  const grupos = new Map<number, { destino: T['destino']; filas: T[] }>()
  for (const f of filas) {
    const g = grupos.get(f.destino.id) ?? { destino: f.destino, filas: [] as T[] }
    g.filas.push(f)
    grupos.set(f.destino.id, g)
  }
  return [...grupos.values()]
    .map((g) => ({ ...g, filas: [...g.filas].sort((a, b) => a.id - b.id) }))
    .sort((a, b) => a.destino.nombre.localeCompare(b.destino.nombre, 'es'))
}
